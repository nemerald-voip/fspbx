<?php

namespace App\Services\Messaging;

use App\Models\DomainSettings;
use App\Models\Extensions;
use App\Models\Messages;
use App\Models\RingotelConversation;
use App\Models\SmsDestinations;
use App\Models\WhCall;
use App\Services\RingotelApiService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use libphonenumber\PhoneNumberFormat;
use RuntimeException;

class RingotelConversationService
{
    public function __construct(protected RingotelApiService $api) {}

    /** Resolve locally: customers without Ringotel never require an API call. */
    public function context(Messages $message): ?array
    {
        $extension = Extensions::query()->without('advSettings')->with('mobile_app')
            ->where('domain_uuid', $message->domain_uuid)
            ->where('extension_uuid', $message->extension_uuid)->first();

        if (!$extension?->mobile_app || !$extension->mobile_app->user_id) {
            return null;
        }

        $orgId = DomainSettings::query()->where('domain_uuid', $message->domain_uuid)
            ->where('domain_setting_category', 'app shell')
            ->where('domain_setting_subcategory', 'org_id')->value('domain_setting_value');
        if (!$orgId) {
            return null;
        }

        $country = get_domain_setting('country', $message->domain_uuid) ?? 'US';
        $local = $message->direction === 'in' ? $message->destination : $message->source;
        $remote = $message->direction === 'in' ? $message->source : $message->destination;
        $local = $this->normalize($local, $country);
        $remote = $this->normalize($remote, $country);

        // Match within this account and extension, including legacy number formats.
        $configured = in_array($local, app(MessageParticipantService::class)
            ->numbers($message->domain_uuid, $extension->extension_uuid), true);
        if (!$configured) {
            return null;
        }

        $values = [
            'domain_uuid' => $message->domain_uuid,
            'extension_uuid' => $extension->extension_uuid,
            'org_id' => (string) $orgId,
            'user_id' => (string) $extension->mobile_app->user_id,
            'local_number' => $local,
            'remote_number' => $remote,
        ];

        return $values + [
            'ringotel_conversation_uuid' => self::uuidForKey(hash('sha256', json_encode(array_values($values)))),
            'conversation_key' => hash('sha256', json_encode(array_values($values))),
            'extension' => (string) $extension->extension,
            'country' => $country,
        ];
    }

    public static function uuidForKey(string $key): string
    {
        return \Ramsey\Uuid\Uuid::uuid5(\Ramsey\Uuid\Uuid::NAMESPACE_URL, 'fspbx:ringotel:conversation:'.$key)->toString();
    }

    public function normalize(string $number, string $country): string
    {
        return formatPhoneNumber($number, $country, PhoneNumberFormat::E164);
    }

    public function contexts(Messages $message): array
    {
        $local = $message->direction === 'in' ? $message->destination : $message->source;
        $contexts = [];
        foreach (app(MessageParticipantService::class)->members($message->domain_uuid, $local) as $member) {
            // Ringotel already holds the author's original; copy only to colleagues.
            if ($message->direction === 'out' && data_get($message->delivery_meta, 'outbound.origin') === 'ringotel'
                && $member->extension_uuid === $message->extension_uuid) {
                continue;
            }
            $recipients = $message->direction === 'out' && $message->message_group_uuid
                ? $message->group->recipients : [$message->destination];
            foreach ($recipients as $recipient) {
                $target = clone $message;
                $target->extension_uuid = $member->extension_uuid;
                if ($message->direction === 'out') $target->destination = $recipient;
                if ($context = $this->context($target)) {
                    $contexts[$context['ringotel_conversation_uuid']] = $context;
                }
            }
        }
        return $contexts;
    }

    public function author(Messages $message): string
    {
        $extension = Extensions::without('advSettings')->where('domain_uuid', $message->domain_uuid)
            ->where('extension_uuid', $message->extension_uuid)->value('extension');
        if (!$extension) {
            throw new RuntimeException('The sending extension no longer exists in this account.');
        }
        return (string) $extension;
    }

    /** Caller holds the conversation lock, including while learning webhook IDs. */
    public function conversation(array $context): RingotelConversation
    {
        $attributes = array_diff_key($context, array_flip(['extension', 'country']));
        return RingotelConversation::firstOrCreate(['ringotel_conversation_uuid' => $context['ringotel_conversation_uuid']],
            $attributes + ['remote_identifier' => $context['remote_number']]);
    }

    public function observe(array $context, array $params, Carbon $receivedAt): void
    {
        if (empty($params['sessionid']) || (string) ($params['orgid'] ?? '') !== $context['org_id']
            || (string) ($params['from'] ?? '') !== $context['extension']
            || (string) ($params['userid'] ?? '') !== $context['user_id']
            || $this->normalize((string) ($params['to'] ?? ''), $context['country']) !== $context['remote_number']) {
            return;
        }

        $conversation = $this->conversation($context);
        // A delayed webhook must not replace a session learned from a newer event.
        if ($conversation->observed_at && $conversation->observed_at->greaterThanOrEqualTo($receivedAt)) {
            return;
        }
        $conversation->update([
            'session_id' => $params['sessionid'],
            'remote_identifier' => $params['to'],
            'observed_at' => $receivedAt,
        ]);
    }

    public function remember(Messages $message): void
    {
        $context = $this->context($message);
        if (!$context) {
            return;
        }
        Cache::store('redis')->lock('ringotel:conversation:'.$context['ringotel_conversation_uuid'], 180)->block(1,
            fn () => $this->observeStoredMessage($context, $message));
    }

    protected function observeStoredMessage(array $context, Messages $message): void
    {
        $meta = data_get($message->delivery_meta, 'outbound.meta', []);
        $this->observe($context, [
            'orgid' => $meta['ringotel_orgid'] ?? '',
            'userid' => $meta['ringotel_userid'] ?? '',
            'from' => $context['extension'],
            'to' => $meta['ringotel_remote_identifier'] ?? '',
            'sessionid' => $meta['ringotel_sessionid'] ?? null,
        ], Carbon::parse($meta['ringotel_received_at'] ?? $message->created_at));
    }

    public function resolve(Messages $message, array $context): RingotelConversation
    {
        $conversation = $this->conversation($context);

        // Message metadata survives webhook retention and recovers a failed remember().
        $known = Messages::query()->where('domain_uuid', $context['domain_uuid'])
            ->where('extension_uuid', $context['extension_uuid'])
            ->where('source', $context['local_number'])->where('destination', $context['remote_number'])
            ->where('delivery_meta->outbound->meta->ringotel_orgid', $context['org_id'])
            ->where('delivery_meta->outbound->meta->ringotel_userid', $context['user_id'])
            ->whereNotNull('delivery_meta->outbound->meta->ringotel_sessionid')
            ->orderByDesc('delivery_meta->outbound->meta->ringotel_received_at')->first();
        if ($known) {
            $this->observeStoredMessage($context, $known);
            $conversation->refresh();
        }
        if ($conversation->session_id) {
            return $conversation;
        }

        // Retained payloads are the source for sessions discarded by the old handler.
        // Normalize in PHP: Ringotel may supply national numbers while Messages uses E.164.
        $webhooks = WhCall::query()->where('name', 'ringotel_messaging')
            ->where('payload->method', 'message')
            ->where('payload->params->orgid', $context['org_id'])
            ->where('payload->params->from', $context['extension'])
            ->where('payload->params->userid', $context['user_id'])
            ->latest('created_at')->cursor();
        foreach ($webhooks as $webhook) {
            $params = $webhook->payload['params'] ?? [];
            if (!empty($params['sessionid'])
                && $this->normalize((string) ($params['to'] ?? ''), $context['country']) === $context['remote_number']) {
                $this->observe($context, $params, $webhook->created_at);
                return $conversation->fresh();
            }
        }

        // A known legacy conversation cannot be recovered by creating an empty session:
        // live testing showed that this can return a different, concurrently active session.
        $legacyMessages = Messages::query()->where('domain_uuid', $context['domain_uuid'])
            ->where(fn ($query) => $query->where('extension_uuid', $context['extension_uuid'])->orWhereNull('extension_uuid'))
            ->whereNull('delivery_meta->ringotel_tracking')
            ->select(['source', 'destination', 'direction'])->cursor();
        foreach ($legacyMessages as $legacy) {
            $local = $legacy->direction === 'in' ? $legacy->destination : $legacy->source;
            $remote = $legacy->direction === 'in' ? $legacy->source : $legacy->destination;
            if ($this->normalize($local, $context['country']) === $context['local_number']
                && $this->normalize($remote, $context['country']) === $context['remote_number']) {
                throw new RuntimeException('Existing conversation has no retained Ringotel session. Bind a verified session with ringotel:sync-message --session.');
            }
        }

        $result = $this->api->message([
            'orgid' => $context['org_id'],
            'from' => $context['remote_number'],
            'to' => $context['extension'],
        ]);
        if (empty($result['sessionid'])) {
            throw new RuntimeException('Ringotel did not return a conversation session ID.');
        }
        $conversation->update(['session_id' => $result['sessionid'], 'observed_at' => now()]);
        return $conversation;
    }
}
