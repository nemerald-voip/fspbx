<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCallWebhookRequest;
use App\Models\CallWebhookSubscription;
use App\Models\Extensions;
use App\Services\CallWebhooks\CallWebhookDeliveryService;
use App\Services\CallWebhooks\CallWebhookSubscriptionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CallWebhookController extends Controller
{
    public function show(): JsonResponse
    {
        if (! userCheckPermission('call_webhook_view')) {
            return $this->denied();
        }

        $subscription = $this->subscription();

        return response()->json([
            'configuration' => [
                'exists' => (bool) $subscription,
                'endpoint_url' => $subscription?->endpoint_url ?? '',
                'enabled' => $subscription?->enabled ?? true,
                'events' => $subscription?->events ?? CallWebhookSubscription::EVENTS,
                'masked_secret' => $subscription?->maskedSecret(),
            ],
        ]);
    }

    public function save(
        SaveCallWebhookRequest $request,
        CallWebhookSubscriptionRegistry $subscriptionRegistry
    ): JsonResponse
    {
        $subscription = $this->subscription();
        $created = ! $subscription;
        $secret = $created ? bin2hex(random_bytes(32)) : null;

        $subscription ??= new CallWebhookSubscription([
            'domain_uuid' => session('domain_uuid'),
            'signing_secret' => $secret,
        ]);
        $subscription->fill($request->validated());
        $subscription->save();
        $subscriptionRegistry->invalidate();

        return response()->json([
            'messages' => ['success' => [$created
                ? 'Call webhook configured successfully.'
                : 'Call webhook updated successfully.']],
            'created' => $created,
            'secret' => $secret,
            'masked_secret' => $subscription->maskedSecret(),
        ], $created ? 201 : 200);
    }

    public function rotateSecret(CallWebhookSubscriptionRegistry $subscriptionRegistry): JsonResponse
    {
        if (! userCheckPermission('call_webhook_update')) {
            return $this->denied();
        }

        $subscription = $this->subscriptionOrFail();
        $secret = bin2hex(random_bytes(32));
        $subscription->signing_secret = $secret;
        $subscription->save();
        $subscriptionRegistry->invalidate();

        return response()->json([
            'messages' => ['success' => ['Signing secret rotated successfully.']],
            'secret' => $secret,
            'masked_secret' => $subscription->maskedSecret(),
        ]);
    }

    public function test(CallWebhookDeliveryService $delivery): JsonResponse
    {
        if (! userCheckPermission('call_webhook_test')) {
            return $this->denied();
        }

        $result = $delivery->test($this->subscriptionOrFail(), $this->testPayload());

        if (! ($result['successful'] ?? false)) {
            return response()->json([
                'messages' => ['error' => [$result['message'] ?? 'The test webhook failed.']],
                'status' => $result['status'] ?? null,
            ], 422);
        }

        return response()->json([
            'messages' => ['success' => ['Test webhook delivered successfully.']],
            'status' => $result['status'] ?? null,
        ]);
    }

    public function destroy(CallWebhookSubscriptionRegistry $subscriptionRegistry): JsonResponse
    {
        if (! userCheckPermission('call_webhook_delete')) {
            return $this->denied();
        }

        $this->subscriptionOrFail()->delete();
        $subscriptionRegistry->invalidate();

        return response()->json([
            'messages' => ['success' => ['Call webhook configuration deleted.']],
        ]);
    }

    private function subscription(): ?CallWebhookSubscription
    {
        return CallWebhookSubscription::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->first();
    }

    private function subscriptionOrFail(): CallWebhookSubscription
    {
        return CallWebhookSubscription::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->firstOrFail();
    }

    private function testPayload(): array
    {
        $extension = Extensions::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('enabled', 'true')
            ->orderBy('extension')
            ->first(['extension_uuid', 'extension']);

        return [
            'id' => (string) Str::uuid(),
            'type' => 'call.test',
            'occurred_at' => now('UTC')->format('Y-m-d\TH:i:s.v\Z'),
            'data' => [
                'interaction_id' => (string) Str::uuid(),
                'channel_uuid' => null,
                'domain_uuid' => session('domain_uuid'),
                'direction' => 'inbound',
                'caller' => ['name' => 'Webhook Test', 'number' => '12025550100'],
                'destination_number' => $extension?->extension ?? '100',
                'target' => [
                    'type' => 'extension',
                    'extension_uuid' => $extension?->extension_uuid,
                    'extension' => $extension?->extension ?? '100',
                    'call_center_agent_uuid' => null,
                    'agent_id' => null,
                    'agent_name' => null,
                    'call_center_queue_uuid' => null,
                    'queue_extension' => null,
                    'queue_name' => null,
                ],
                'state' => [
                    'answered_at' => null,
                    'ended_at' => null,
                    'outcome' => null,
                    'hangup_cause' => null,
                ],
            ],
        ];
    }

    private function denied(): JsonResponse
    {
        return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
    }
}
