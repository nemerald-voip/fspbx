<?php

namespace App\Listeners;

use App\Services\CallWebhooks\CallWebhookDeliveryService;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;

class RecordCallWebhookTestResult
{
    public function __construct(private CallWebhookDeliveryService $deliveryService)
    {
    }

    public function handle(
        WebhookCallSucceededEvent|WebhookCallFailedEvent|FinalWebhookCallFailedEvent $event
    ): void {
        if (($event->meta['is_test'] ?? false) !== true) {
            return;
        }

        $successful = $event instanceof WebhookCallSucceededEvent;
        $status = $event->response?->getStatusCode();

        $this->deliveryService->storeTestResult($event->uuid, [
            'successful' => $successful,
            'status' => $status,
            'message' => $successful
                ? __('Webhook test delivered successfully (HTTP :status).', ['status' => $status])
                : ($event->errorMessage ?: ($status ? __('Webhook endpoint returned HTTP :status.', ['status' => $status]) : __('Webhook test failed.'))),
        ]);
    }
}
