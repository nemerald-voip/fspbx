<?php

namespace App\Jobs;

use App\Services\Messaging\Outbound\OutboundMessageWebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class DeliverOutboundMessageWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 10;
    public $timeout = 30;
    public $backoff = 30;

    public function __construct(public string $eventUuid) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('outbound-webhook:'.$this->eventUuid))->releaseAfter(15)->expireAfter(60)];
    }

    public function handle(OutboundMessageWebhookDelivery $delivery): void
    {
        $delivery->dispatch($this->eventUuid);
    }

    public function failed(?\Throwable $exception): void
    {
        // Leave pending for a queue retry; never classify this as a carrier failure.
        logger()->warning('Unable to queue outbound message webhook.', ['event_id' => $this->eventUuid]);
    }
}
