<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;

class LogCallWebhookFailure
{
    public function handle(WebhookCallFailedEvent|FinalWebhookCallFailedEvent $event): void
    {
        if (($event->meta['is_test'] ?? false) === true) {
            return;
        }

        $context = [
            'domain_uuid' => $event->meta['domain_uuid'] ?? null,
            'subscription_uuid' => $event->meta['subscription_uuid'] ?? null,
            'event_id' => $event->meta['event_id'] ?? $event->uuid,
            'event_type' => $event->meta['event_type'] ?? null,
            'target_type' => $event->meta['target_type'] ?? null,
            'target_uuid' => $event->meta['target_uuid'] ?? null,
            'attempt' => $event->attempt,
            'status' => $event->response?->getStatusCode(),
            'error_type' => $event->errorType,
            'error' => $event->errorMessage,
        ];

        if ($event instanceof FinalWebhookCallFailedEvent) {
            Log::error('Call webhook delivery exhausted all attempts.', $context);
            return;
        }

        Log::warning('Call webhook delivery failed and will be retried when attempts remain.', $context);
    }
}
