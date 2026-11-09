<?php

namespace App\Services\Messaging;

use App\Models\Messages;
use App\Services\MessageMediaObjectStorageService;
use Spatie\WebhookServer\WebhookCall;

class MessageWebhookDeliveryService
{
    public function __construct(
        private MessageWebhookSettings $settings,
        private MessageMediaObjectStorageService $mediaStorage,
    ) {}

    public function dispatch(Messages $message): void
    {
        if (! $message->isWebhookInbound()
            || in_array(data_get($message->delivery_meta, 'webhook.status'), ['queued', 'delivered', 'failed'], true)) {
            return;
        }

        $account = $this->settings->account($message->domain_uuid);
        if (! $account || ! filled($account->signing_secret)) {
            throw new \RuntimeException('The messaging webhook signing secret is unavailable.');
        }

        $eventId = data_get($message->delivery_meta, 'webhook.event_id');
        $url = data_get($message->delivery_meta, 'inbound.endpoint_url');
        $brand = config('webhook-server.brand', 'FS-PBX');
        $call = WebhookCall::create()
            ->url($url)
            ->payload($this->payload($message))
            ->useSecret($account->signing_secret)
            ->useTimestamp()
            ->uuid($eventId)
            ->withHeaders([
                'User-Agent' => "{$brand}-Message-Webhooks/1.0",
                "X-{$brand}-Event-ID" => $eventId,
                "X-{$brand}-Event-Type" => 'message.received',
            ])
            ->meta([
                'webhook_type' => 'message',
                'domain_uuid' => $message->domain_uuid,
                'message_uuid' => $message->message_uuid,
                'event_id' => $eventId,
            ])
            ->withTags(['message-webhook', 'domain:'.$message->domain_uuid, 'event:message.received']);

        // Callers invoke this after the message transaction commits. Force the
        // PendingDispatch destructor before marking queued so queue failures
        // leave a pending event that an upstream retry can safely dispatch.
        $pending = $call->dispatch();
        unset($pending);
        Messages::whereKey($message->message_uuid)->where('domain_uuid', $message->domain_uuid)
            ->where('delivery_meta->webhook->status', 'pending')
            ->update(['delivery_meta->webhook->status' => 'queued']);
    }

    public function payload(Messages $message): array
    {
        $media = [];
        foreach ($message->mediaItems() as $item) {
            if (empty($item['bucket']) || empty($item['object_key'])) {
                throw new \RuntimeException('An inbound message attachment is unavailable for webhook delivery.');
            }
            $media[] = [
                'url' => $this->mediaStorage->generateTemporaryDownloadUrl(
                    domainUuid: $message->domain_uuid,
                    bucket: $item['bucket'],
                    objectKey: $item['object_key'],
                    expires: '+24 hours',
                    mimeType: $item['mime_type'] ?? null,
                    downloadName: $item['original_name'] ?? $item['stored_name'] ?? null,
                ),
                'content_type' => $item['mime_type'] ?? null,
                'size' => $item['size'] ?? null,
            ];
        }

        $payload = [
            'id' => $message->message_uuid,
            'record_type' => 'message',
            'direction' => 'inbound',
            'type' => strtoupper($message->type),
            'from' => ['phone_number' => $message->source],
            'to' => array_map(fn ($number) => ['phone_number' => $number],
                data_get($message->delivery_meta, 'inbound.recipients', [$message->destination])),
            'text' => $message->message,
            'media' => $media,
            'received_at' => $message->created_at->toIso8601String(),
        ];
        if ($message->message_group_uuid) {
            $payload['group_id'] = $message->message_group_uuid;
            $payload['participants'] = data_get($message->delivery_meta, 'inbound.participants', []);
        }
        $expected = data_get($message->delivery_meta, 'provider.expected_media_count');
        if ($expected !== null && $expected > count($media)) {
            $payload['errors'] = [['code' => 'media_unavailable', 'detail' => 'Some attachments could not be retrieved.']];
        }

        return ['data' => [
            'event_type' => 'message.received',
            'id' => data_get($message->delivery_meta, 'webhook.event_id'),
            'occurred_at' => $message->created_at->toIso8601String(),
            'record_type' => 'event',
            'payload' => $payload,
        ]];
    }
}
