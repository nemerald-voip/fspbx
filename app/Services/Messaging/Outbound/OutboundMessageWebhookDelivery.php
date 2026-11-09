<?php

namespace App\Services\Messaging\Outbound;

use App\Models\OutboundMessageWebhookEvent;
use App\Services\MessageMediaObjectStorageService;
use App\Services\Messaging\MessageWebhookSettings;
use Illuminate\Support\Facades\DB;
use Spatie\WebhookServer\WebhookCall;

class OutboundMessageWebhookDelivery
{
    public function dispatch(string $id): void
    {
        $event = DB::transaction(function () use ($id) {
            $event = OutboundMessageWebhookEvent::whereKey($id)->lockForUpdate()->first();
            if (! $event || $event->status !== 'pending') return null;
            if (! $event->prepared) {
                $payload = $event->payload;
                $media = [];
                foreach ($event->media_items as $item) {
                    $media[] = [
                        'url' => app(MessageMediaObjectStorageService::class)->generateTemporaryDownloadUrl(
                            domainUuid: $event->domain_uuid, bucket: $item['bucket'], objectKey: $item['object_key'],
                            expires: '+24 hours', mimeType: $item['mime_type'] ?? null,
                            downloadName: $item['original_name'] ?? $item['stored_name'] ?? null,
                        ),
                        'content_type' => $item['mime_type'] ?? null, 'size' => $item['size'] ?? null,
                    ];
                }
                $payload['data']['payload']['media'] = $media;
                $event->update(['payload' => $payload, 'prepared' => true]);
            }
            return $event;
        });
        if (! $event) return;
        $account = app(MessageWebhookSettings::class)->account($event->domain_uuid);
        if (! $account || ! filled($account->signing_secret)) throw new \RuntimeException('The messaging webhook signing secret is unavailable.');
        $brand = config('webhook-server.brand', 'FS-PBX');
        $pending = WebhookCall::create()->url($event->endpoint_url)->payload($event->payload)
            ->useSecret($account->signing_secret)->useTimestamp()->uuid($event->getKey())
            ->withHeaders([
                'User-Agent' => "{$brand}-Message-Webhooks/1.0",
                "X-{$brand}-Event-ID" => $event->getKey(), "X-{$brand}-Event-Type" => $event->event_type,
            ])->meta([
                'webhook_type' => 'message', 'outbound_event_uuid' => $event->getKey(),
                'domain_uuid' => $event->domain_uuid, 'message_uuid' => $event->message_uuid, 'event_id' => $event->getKey(),
            ])->withTags(['message-webhook', 'domain:'.$event->domain_uuid, 'event:'.$event->event_type])->dispatch();
        unset($pending);
        OutboundMessageWebhookEvent::whereKey($id)->where('status', 'pending')->update(['status' => 'queued']);
    }
}
