<?php

namespace App\Listeners;

use App\Models\Messages;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;

class RecordMessageWebhookResult
{
    public function handle(WebhookCallSucceededEvent|WebhookCallFailedEvent|FinalWebhookCallFailedEvent $event): void
    {
        if (($event->meta['webhook_type'] ?? null) !== 'message') {
            return;
        }
        $status = $event instanceof WebhookCallSucceededEvent ? 'delivered'
            : ($event instanceof FinalWebhookCallFailedEvent ? 'failed' : 'queued');
        (new Messages())->getConnection()->transaction(function () use ($event, $status) {
            $message = Messages::whereKey($event->meta['message_uuid'])
                ->where('domain_uuid', $event->meta['domain_uuid'])
                ->where('delivery_meta->webhook->event_id', $event->uuid)
                ->where('delivery_meta->webhook->status', '!=', 'delivered')
                ->lockForUpdate()->first();
            if (! $message) {
                return;
            }

            // PostgreSQL requires one assignment to delivery_meta. Lock the
            // current row so merging the result preserves concurrent metadata.
            $meta = $message->delivery_meta;
            $meta['webhook'] = array_replace($meta['webhook'] ?? [], [
                'status' => $status,
                'attempts' => $event->attempt,
                'http_status' => $event->response?->getStatusCode(),
                'attempted_at' => now()->toIso8601String(),
            ]);
            $message->delivery_meta = $meta;
            $message->saveQuietly();
        });
        if ($event instanceof FinalWebhookCallFailedEvent) {
            logger()->warning('Message webhook delivery exhausted all attempts.', [
                'domain_uuid' => $event->meta['domain_uuid'],
                'message_uuid' => $event->meta['message_uuid'],
                'event_id' => $event->uuid,
                'status' => $event->response?->getStatusCode(),
                'error_type' => $event->errorType,
            ]);
        }
    }
}
