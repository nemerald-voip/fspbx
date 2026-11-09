<?php

namespace App\Services\Messaging\Outbound;

use App\Jobs\DeliverOutboundMessageWebhook;
use App\Models\MessageSetting;
use App\Models\Messages;
use App\Models\OutboundMessageWebhookEvent;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiMessageStatus
{
    private const PATH = 'outbound.meta.api';
    private const FINAL = ['delivered', 'sending_failed', 'delivery_failed', 'delivery_unconfirmed'];

    public function isFinal(Messages $message): bool
    {
        $recipients = data_get($message->delivery_meta, self::PATH.'.recipients', []);
        return $recipients !== [] && collect($recipients)->every(fn ($r) => in_array($r['status'], self::FINAL, true));
    }

    public function payload(Messages $message): array
    {
        $api = data_get($message->delivery_meta, self::PATH, []);
        $recipients = $api['recipients'] ?? [];
        $payload = [
            'id' => $message->getKey(), 'record_type' => 'message', 'direction' => 'outbound',
            'type' => strtoupper($message->type), 'from' => ['phone_number' => $message->source],
            'to' => collect($recipients)->map(fn ($value, $number) => [
                'phone_number' => $number, 'status' => $value['status'],
            ])->values()->all(),
            'text' => $message->message, 'media' => [],
            'received_at' => $message->created_at->toIso8601String(),
            'sent_at' => $api['sent_at'] ?? null, 'completed_at' => $api['completed_at'] ?? null,
            'errors' => collect($recipients)->filter(fn ($r) => isset($r['error']))
                ->map(fn ($r, $number) => $r['error'] + ['source' => ['recipient' => $number]])->values()->all(),
        ];
        if ($message->message_group_uuid) {
            $payload['group_id'] = $message->message_group_uuid;
            $payload['participants'] = array_merge([$message->source], array_keys($recipients));
        }
        return $payload;
    }

    public function send(string $id, OutboundProviderFactory $factory): void
    {
        $message = DB::transaction(function () use ($id) {
            $message = Messages::whereKey($id)->lockForUpdate()->first();
            if (! $message?->isApiOutbound()) return null;
            $api = data_get($message->delivery_meta, self::PATH);
            if (! ($api['media_prepared'] ?? false) || isset($api['attempt_completed_at']) || $this->isFinal($message)) return null;
            if (isset($api['attempt_started_at'])) {
                // An interrupted submission is failed. Repeated jobs do not create a new send.
                $this->setRecipients($message, 'sending_failed', code: 'submission_failed');
                $meta = $message->delivery_meta;
                data_set($meta, self::PATH.'.attempt_completed_at', now()->toIso8601String());
                $message->delivery_meta = $meta;
                $this->saveState($message);
                return null;
            }
            $number = MessageSetting::whereKey($api['number_uuid'])->where('domain_uuid', $message->domain_uuid)
                ->where('destination', $message->source)->where('enabled', 'true')->first();
            if (! $number) {
                $this->setRecipients($message, 'sending_failed', code: 'sender_unavailable');
                $this->saveState($message);
                return null;
            }
            $meta = $message->delivery_meta;
            data_set($meta, self::PATH.'.attempt_started_at', now()->toIso8601String());
            $message->delivery_meta = $meta;
            $message->saveQuietly();
            return $message;
        });
        if (! $message) {
            if ($existing = Messages::find($id)) $this->dispatchEvents($existing);
            return;
        }
        $carrier = data_get($message->delivery_meta, 'outbound.provider.name');
        try {
            $result = $factory->make($carrier)->send($message);
        } catch (\Throwable $exception) {
            $result = new OutboundSendResultData(false, 'failed', error: $exception->getMessage(),
                providerResponse: ['exception' => $exception->getMessage()]);
        }
        $this->sentResult($id, $result);
    }

    public function sentResult(string $id, OutboundSendResultData $result): void
    {
        DB::transaction(function () use ($id, $result) {
            $message = Messages::whereKey($id)->lockForUpdate()->firstOrFail();
            $meta = $message->delivery_meta;
            data_set($meta, self::PATH.'.attempt_completed_at', now()->toIso8601String());
            data_set($meta, 'outbound.provider.response', $result->providerResponse);
            data_set($meta, 'outbound.provider.error', $result->error);
            if ($result->providerReferenceId) {
                $message->reference_id = $result->providerReferenceId;
                data_set($meta, 'outbound.provider.reference_id', $result->providerReferenceId);
            }
            $message->delivery_meta = $meta;
            if ($result->success) {
                $this->accepted($message);
                $this->setRecipients($message, $result->status === 'delivered' ? 'delivered' : 'sent');
            } else {
                $this->setRecipients($message, 'sending_failed',
                    code: isset($result->providerResponse['exception']) ? 'submission_failed' : 'provider_rejected');
            }
            $this->saveState($message);
        });
        $this->dispatchEvents(Messages::findOrFail($id));
    }

    public function failPreparation(string $id): void
    {
        DB::transaction(function () use ($id) {
            $message = Messages::whereKey($id)->lockForUpdate()->first();
            if (! $message?->isApiOutbound() || data_get($message->delivery_meta, self::PATH.'.attempt_started_at')) return;
            $this->setRecipients($message, 'sending_failed', code: 'preparation_failed');
            $this->saveState($message);
        });
        if ($message = Messages::find($id)) $this->dispatchEvents($message);
    }

    public function failSend(string $id): void
    {
        DB::transaction(function () use ($id) {
            $message = Messages::whereKey($id)->lockForUpdate()->first();
            if (!$message?->isApiOutbound() || data_get($message->delivery_meta, self::PATH.'.attempt_completed_at') || $this->isFinal($message)) return;
            $started = data_get($message->delivery_meta, self::PATH.'.attempt_started_at');
            $this->setRecipients($message, 'sending_failed', code: $started ? 'submission_failed' : 'preparation_failed');
            $meta = $message->delivery_meta;
            data_set($meta, self::PATH.'.attempt_completed_at', now()->toIso8601String());
            $message->delivery_meta = $meta;
            $this->saveState($message);
        });
        if ($message = Messages::find($id)) $this->dispatchEvents($message);
    }

    /** Return true when this receipt is handled by the outbound API. */
    public function receipt(array $receipt): bool
    {
        $provider = $receipt['provider'];
        $reference = $receipt['referenceId'];
        if ($reference === '') return false;
        $query = Messages::where('direction', 'out')->where('delivery_meta->outbound->provider->name', $provider)
            ->where('reference_id', $reference);
        $matches = $query->limit(2)->get();
        if ($matches->count() > 1) {
            logger()->warning('Ambiguous messaging receipt ignored.', ['provider' => $provider]);
            return true;
        }
        $message = $matches->first();
        if (! $message?->isApiOutbound()) return false;
        DB::transaction(function () use ($message, $receipt) {
            $current = Messages::whereKey($message->getKey())->where('domain_uuid', $message->domain_uuid)->lockForUpdate()->firstOrFail();
            $raw = strtolower($receipt['rawStatus'] ?? $receipt['status']);
            if ($receipt['provider'] === 'twilio' && $raw === 'failed') $raw = 'sending_failed';
            $status = match ($raw) {
                'delivered', 'delivery_report', 'success', 'successful' => 'delivered',
                'delivery_unconfirmed', 'unknown', 'dlr_timeout', 'gw_timeout' => 'delivery_unconfirmed',
                'sending_failed' => 'sending_failed',
                'failed', 'rejected', 'undelivered', 'delivery_failed', 'canceled' => 'delivery_failed',
                'sent', 'accepted', 'queued', 'sending' => 'sent',
                default => null,
            };
            if ($status === null) return;
            $recipients = data_get($current->delivery_meta, self::PATH.'.recipients', []);
            if (!empty($receipt['recipient']) && !array_key_exists($receipt['recipient'], $recipients)) return;
            $changed = $this->setRecipients($current, $status, $receipt['recipient'] ?? null,
                $receipt['occurredAt'] ?? null, in_array($status, ['sent', 'delivered'], true) ? null : $status);
            if (! $changed) return;
            $meta = $current->delivery_meta;
            data_set($meta, 'outbound.provider.last_event', $receipt['providerEvent'] ?? null);
            data_set($meta, 'outbound.provider.updated_at', now()->toIso8601String());
            data_set($meta, 'outbound.provider.error', $receipt['description'] ?? null);
            $current->delivery_meta = $meta;
            if ($status !== 'sending_failed') $this->accepted($current);
            $this->saveState($current);
        });
        $this->dispatchEvents($message->fresh());
        return true;
    }

    private function accepted(Messages $message): void
    {
        $meta = $message->delivery_meta;
        $at = data_get($meta, self::PATH.'.sent_at') ?: now()->toIso8601String();
        data_set($meta, self::PATH.'.sent_at', $at);
        data_set($meta, 'outbound.provider.accepted_at', $at);
        $message->delivery_meta = $meta;
    }

    private function setRecipients(Messages $message, string $status, ?string $recipient = null, ?string $occurredAt = null, ?string $code = null): bool
    {
        $meta = $message->delivery_meta;
        $recipients = data_get($meta, self::PATH.'.recipients', []);
        try { $time = $occurredAt ? Carbon::parse($occurredAt)->toIso8601String() : null; }
        catch (\Throwable) { $time = null; }
        $changed = false;
        foreach ($recipients as $number => $previous) {
            if ($recipient !== null && $recipient !== $number) continue;
            if ($time && !empty($previous['occurred_at']) && Carbon::parse($time)->lt(Carbon::parse($previous['occurred_at']))) continue;
            if ($previous['status'] === $status) continue;
            // Authoritative terminal results never regress; unknown can be corrected by a receipt.
            if (in_array($previous['status'], ['delivered', 'sending_failed', 'delivery_failed'], true)) continue;
            if ($previous['status'] === 'delivery_unconfirmed' && !in_array($status, ['delivered', 'delivery_failed', 'sending_failed'], true)) continue;
            $value = ['status' => $status, 'occurred_at' => $time];
            if ($code) $value['error'] = ['code' => $code, 'detail' => match ($code) {
                'preparation_failed' => 'Message preparation failed. No message was submitted to the provider.',
                'sender_unavailable' => 'The sending number is no longer enabled in this account.',
                'submission_failed' => 'Message submission failed. Check administrative message logs for details.',
                'delivery_unconfirmed' => 'The provider reported an unconfirmed delivery outcome.',
                default => 'The provider reported a messaging failure. Check administrative message logs for details.',
            }];
            $recipients[$number] = $value;
            $changed = true;
        }
        data_set($meta, self::PATH.'.recipients', $recipients);
        $message->delivery_meta = $meta;
        return $changed;
    }

    private function saveState(Messages $message): void
    {
        $meta = $message->delivery_meta;
        $states = array_column(data_get($meta, self::PATH.'.recipients', []), 'status');
        $final = $this->isFinal($message);
        if ($final) data_set($meta, self::PATH.'.completed_at', now()->toIso8601String());
        $message->status = $final
            ? (count(array_unique($states)) === 1 && $states[0] === 'delivered' ? 'delivered' : 'failed')
            : (data_get($meta, self::PATH.'.sent_at') ? 'sent' : 'queued');
        data_set($meta, 'outbound.provider.status', count(array_unique($states)) === 1 ? $states[0] : $message->status);
        $message->delivery_meta = $meta;
        $message->saveQuietly();
        if (data_get($meta, self::PATH.'.sent_at')) $this->recordEvent($message, 'message.sent');
        if ($final) $this->recordEvent($message, 'message.finalized');
    }

    private function recordEvent(Messages $message, string $type): void
    {
        $url = data_get($message->delivery_meta, self::PATH.'.endpoint_url');
        if (! $url) return;
        $payload = $this->payload($message);
        if ($type === 'message.sent') {
            $payload['to'] = array_map(fn ($r) => ['phone_number' => $r['phone_number'], 'status' => 'sent'], $payload['to']);
            $payload['errors'] = [];
            $payload['completed_at'] = null;
        }
        $fingerprint = hash('sha256', json_encode([$payload['to'], $payload['errors']]));
        $id = (string) Str::uuid();
        OutboundMessageWebhookEvent::firstOrCreate([
            'message_uuid' => $message->getKey(), 'event_type' => $type, 'fingerprint' => $fingerprint,
        ], [
            'outbound_message_webhook_event_uuid' => $id, 'domain_uuid' => $message->domain_uuid, 'endpoint_url' => $url,
            'media_items' => $message->mediaItems(),
            'payload' => ['data' => ['id' => $id, 'record_type' => 'event', 'event_type' => $type,
                'occurred_at' => $type === 'message.sent' ? $payload['sent_at'] : $payload['completed_at'], 'payload' => $payload]],
        ]);
    }

    public function dispatchEvents(Messages $message): void
    {
        if (! $message->isApiOutbound()) return;
        foreach (OutboundMessageWebhookEvent::where('message_uuid', $message->getKey())->where('domain_uuid', $message->domain_uuid)
            ->where('status', 'pending')->orderBy('created_at')->get() as $event) {
            // A dispatch exception retries the processing job, never the carrier request.
            $pending = DeliverOutboundMessageWebhook::dispatch($event->getKey())
                ->onConnection(config('webhook-server.connection', 'redis'))
                ->onQueue(config('webhook-server.queue', 'webhooks'))->afterCommit();
            unset($pending);
        }
    }
}
