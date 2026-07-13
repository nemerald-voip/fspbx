<?php

namespace App\Services\CallWebhooks;

use App\Models\CallWebhookSubscription;
use Illuminate\Support\Facades\Cache;
use Spatie\WebhookServer\WebhookCall;

class CallWebhookDeliveryService
{
    public function dispatch(CallWebhookSubscription $subscription, array $payload): bool
    {
        $eventType = (string) ($payload['type'] ?? '');
        if ($eventType !== 'call.test' && ! $subscription->accepts($eventType)) {
            return false;
        }

        $this->call($subscription, $payload)->dispatch();

        return true;
    }

    public function test(CallWebhookSubscription $subscription, array $payload): array
    {
        $eventId = (string) $payload['id'];
        Cache::forget($this->testResultKey($eventId));

        $this->call($subscription, $payload, true)
            ->maximumTries(1)
            ->dispatchSync();

        return Cache::pull($this->testResultKey($eventId), [
            'successful' => false,
            'status' => null,
            'message' => 'The webhook test did not return a delivery result.',
        ]);
    }

    public function storeTestResult(string $eventId, array $result): void
    {
        Cache::put($this->testResultKey($eventId), $result, now()->addMinute());
    }

    private function call(
        CallWebhookSubscription $subscription,
        array $payload,
        bool $isTest = false
    ): WebhookCall {
        $eventId = (string) $payload['id'];
        $eventType = (string) $payload['type'];
        $target = data_get($payload, 'data.target', []);

        return WebhookCall::create()
            ->url($subscription->endpoint_url)
            ->payload($payload)
            ->useSecret((string) $subscription->signing_secret)
            ->useTimestamp()
            ->uuid($eventId)
            ->withHeaders([
                'X-FS-PBX-Event-ID' => $eventId,
                'X-FS-PBX-Event-Type' => $eventType,
            ])
            ->meta([
                'is_test' => $isTest,
                'domain_uuid' => $subscription->domain_uuid,
                'subscription_uuid' => $subscription->call_webhook_uuid,
                'event_id' => $eventId,
                'event_type' => $eventType,
                'target_type' => $target['type'] ?? null,
                'target_uuid' => $target['call_center_agent_uuid']
                    ?? $target['extension_uuid']
                    ?? null,
            ])
            ->withTags([
                'call-webhook',
                'domain:' . $subscription->domain_uuid,
                'event:' . $eventType,
            ]);
    }

    private function testResultKey(string $eventId): string
    {
        return "call_webhook:test_result:{$eventId}";
    }
}
