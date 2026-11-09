<?php

namespace App\Listeners;

use App\Services\OutboundWebhookHistory;
use Spatie\WebhookServer\Events\DispatchingWebhookCallEvent;
use Spatie\WebhookServer\Events\WebhookCallEvent;

class RecordOutboundWebhookHistory
{
    public function handle(DispatchingWebhookCallEvent|WebhookCallEvent $event): void
    {
        // Observability must never turn a successful HTTP request into a retry.
        try {
            app(OutboundWebhookHistory::class)->record($event);
        } catch (\Throwable $exception) {
            logger()->warning('Unable to record outbound webhook history.', [
                'event_id' => $event->uuid,
                'error_type' => $exception::class,
            ]);
        }
    }
}
