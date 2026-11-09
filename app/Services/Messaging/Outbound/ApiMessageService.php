<?php

namespace App\Services\Messaging\Outbound;

use App\Exceptions\ApiException;
use App\Http\Requests\Api\V1\SendMessageRequest;
use App\Jobs\PrepareApiMessage;
use App\Jobs\SendOutboundSMSMessage;
use App\Models\Domain;
use App\Models\MessageApiRequest;
use App\Models\MessageSetting;
use App\Models\Messages;
use App\Services\CallWebhooks\PublicWebhookUrlValidator;
use App\Services\Messaging\MessageCarrierCapabilities;
use App\Services\Messaging\MessageGroupService;
use App\Services\Messaging\MessageWebhookSettings;
use App\Services\Messaging\Outbound\Data\CreateOutboundMessageData;
use App\Services\Messaging\OutboundPhotoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class ApiMessageService
{
    public function submit(SendMessageRequest $request): array
    {
        $input = $request->validated();
        $domain = (string) $request->route('domain_uuid');
        $operation = $request->isGroup() ? 'group_mms' : 'send';
        $key = $request->header('Idempotency-Key');
        $fingerprint = hash('sha256', json_encode([
            $input['from'], $input['to'], $input['text'] ?? '', $input['media_urls'] ?? [],
            $input['type'] ?? ($request->isGroup() || !empty($input['media_urls']) ? 'MMS' : 'SMS'),
        ], JSON_THROW_ON_ERROR));

        $lookup = fn () => $key === null ? null : MessageApiRequest::where('domain_uuid', $domain)
            ->where('user_uuid', $request->user()->getKey())->where('operation', $operation)
            ->where('key_hash', hash('sha256', $key))->first();
        $response = $this->replay($lookup(), $fingerprint);
        // DNS resolution must not hold an account/database lock. A replay also
        // remains available when an original attachment URL has since expired.
        if ($response === null) {
            foreach ($input['media_urls'] ?? [] as $url) {
                try {
                    app(PublicWebhookUrlValidator::class)->validateAndResolve($url);
                } catch (\InvalidArgumentException) {
                    throw ValidationException::withMessages(['media_urls' => ['Media URLs must use HTTPS and resolve only to public addresses, without embedded credentials.']]);
                }
            }
        }
        $response ??= DB::transaction(function () use ($request, $input, $domain, $operation, $key, $fingerprint, $lookup) {
            // Short account lock also serializes first-use signing-secret creation.
            Domain::whereKey($domain)->lockForUpdate()->firstOrFail();
            $existing = $lookup();
            if ($replay = $this->replay($existing, $fingerprint)) return $replay;
            $existing?->delete();

            $source = $this->number($input['from'], 'from');
            $number = MessageSetting::where('domain_uuid', $domain)->where('destination', $source)
                ->where('enabled', 'true')->first();
            if (! $number || ! isset(config('messaging.outbound_providers')[$number->carrier])) {
                throw ValidationException::withMessages(['from' => ['Choose an enabled messaging number with a configured provider in this account.']]);
            }
            $recipients = $request->isGroup()
                ? array_values(array_unique(array_map(fn ($n) => $this->number($n, 'to'), $input['to'])))
                : [$this->number($input['to'], 'to')];
            $group = null;
            if ($request->isGroup()) {
                $recipients = array_values(array_diff($recipients, [$source]));
                sort($recipients, SORT_STRING);
                if (count($recipients) < 2) {
                    throw ValidationException::withMessages(['to' => ['A group requires at least two distinct external recipients.']]);
                }
                try {
                    app(MessageCarrierCapabilities::class)->validateGroup($number->carrier, $source, $recipients);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['to' => $exception->errors()['recipients']]);
                }
                $group = app(MessageGroupService::class)->findOrCreate($domain, $source, $recipients);
            }
            $urls = $input['media_urls'] ?? [];
            $limit = match ($number->carrier) {
                'clicksend', 'thinq', 'fibernetics' => 1,
                'voipms' => 3,
                default => 10,
            };
            if (count($urls) > $limit) {
                throw ValidationException::withMessages(['media_urls' => ["This provider supports at most {$limit} attachment(s)."]]);
            }
            $route = app(MessageWebhookSettings::class)->resolve($number);
            $message = app(CreateOutboundMessageService::class)->create(new CreateOutboundMessageData(
                domainUuid: $domain, extensionUuid: null, source: $source, destination: $recipients[0],
                message: $input['text'] ?? '', origin: 'api', carrier: $number->carrier,
                messageGroupUuid: $group?->getKey(), dispatch: false,
                type: $group || $urls ? 'mms' : 'sms',
                meta: [
                    'requested_by_user_uuid' => (string) $request->user()->getKey(),
                    'api_token_id' => $request->user()->currentAccessToken()?->getKey(),
                    'api' => [
                        'number_uuid' => $number->getKey(), 'endpoint_url' => $route['endpoint_url'] ?? null,
                        'media_urls' => $urls, 'media_prepared' => false,
                        'recipients' => array_fill_keys($recipients, ['status' => 'queued']),
                    ],
                ],
            ));
            $payload = app(ApiMessageStatus::class)->payload($message);
            $payload['media'] = array_map(fn ($url) => ['url' => $url, 'content_type' => null, 'size' => null], $urls);
            $response = ['data' => $payload];
            if ($key !== null) {
                MessageApiRequest::create([
                    'domain_uuid' => $domain, 'user_uuid' => $request->user()->getKey(), 'operation' => $operation,
                    'key_hash' => hash('sha256', $key), 'fingerprint' => $fingerprint,
                    'message_uuid' => $message->getKey(), 'response' => $response, 'expires_at' => now()->addDay(),
                ]);
            }
            return $response;
        });
        try {
            // Replays repair a failed queue handoff; workers guard the carrier boundary.
            $pending = PrepareApiMessage::dispatch($response['data']['id'])->onConnection('redis')->onQueue('messages')->afterCommit();
            unset($pending);
        } catch (\Throwable) {
            throw new ApiException(503, 'api_error', 'Unable to queue the message. Retry with the same Idempotency-Key.', 'queue_unavailable');
        }
        return $response;
    }

    private function replay(?MessageApiRequest $existing, string $fingerprint): ?array
    {
        if (! $existing || ! $existing->expires_at->isFuture()) return null;
        if (! hash_equals($existing->fingerprint, $fingerprint)) {
            throw new ApiException(409, 'invalid_request_error', 'Idempotency-Key was already used with a different request.', 'idempotency_conflict', 'Idempotency-Key');
        }
        return $existing->response;
    }

    private function number(string $input, string $field): string
    {
        try {
            $phone = PhoneNumberUtil::getInstance();
            $parsed = $phone->parse($input);
            $formatted = $phone->format($parsed, PhoneNumberFormat::E164);
            if (! $phone->isValidNumber($parsed) || $formatted !== $input) throw new \InvalidArgumentException;
            return $formatted;
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => ['Enter a valid phone number in E.164 format, for example +12025550100.']]);
        }
    }

    public function prepare(string $id): void
    {
        $message = Messages::find($id);
        if (! $message?->isApiOutbound()) return;
        $status = app(ApiMessageStatus::class);
        if (data_get($message->delivery_meta, 'outbound.meta.api.attempt_started_at')
            || $status->isFinal($message)) {
            if (!data_get($message->delivery_meta, 'outbound.meta.api.attempt_completed_at') && !$status->isFinal($message)) {
                $pending = SendOutboundSMSMessage::dispatch($id)->onConnection('redis')->onQueue('messages')->afterCommit();
                unset($pending);
            }
            $status->dispatchEvents($message);
            return;
        }
        if (! data_get($message->delivery_meta, 'outbound.meta.api.media_prepared')) {
            app(ApiMessageMedia::class)->prepare($message);
            app(OutboundPhotoService::class)->prepare($message->fresh());
            DB::transaction(function () use ($id) {
                $message = Messages::whereKey($id)->lockForUpdate()->firstOrFail();
                $meta = $message->delivery_meta;
                data_set($meta, 'outbound.meta.api.media_prepared', true);
                $message->delivery_meta = $meta;
                $message->saveQuietly();
            });
        }
        $pending = SendOutboundSMSMessage::dispatch($id)->onConnection('redis')->onQueue('messages')->afterCommit();
        unset($pending);
    }
}
