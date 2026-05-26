<?php

namespace App\Http\Controllers;

use App\Models\AiReceptionist;
use App\Models\AiReceptionistSession;
use App\Services\AiReceptionistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiReceptionistAgentController extends Controller
{
    public function bootstrap(Request $request, AiReceptionistService $service): JsonResponse
    {
        if ($response = $this->authorizeAgent($request)) {
            return $response;
        }

        $settings = $service->resolvedSettings(includeSecrets: true);

        return response()->json([
            'enabled' => (bool) ($settings['enabled'] ?? false),
        ]);
    }

    public function config(Request $request, AiReceptionist $ai_receptionist, AiReceptionistService $service): JsonResponse
    {
        if ($response = $this->authorizeAgent($request)) {
            return $response;
        }

        if (! ($service->resolvedSettings($ai_receptionist->domain_uuid)['enabled'] ?? true)) {
            return response()->json(['message' => 'AI receptionists are disabled for this account.'], 404);
        }

        return response()->json($service->configForReceptionist($ai_receptionist));
    }

    public function startSession(Request $request, AiReceptionist $ai_receptionist, AiReceptionistService $service): JsonResponse
    {
        if ($response = $this->authorizeAgent($request)) {
            return $response;
        }

        $payload = $request->validate([
            'freeswitch_uuid' => ['nullable', 'string', 'max:255'],
            'openai_call_id' => ['nullable', 'string', 'max:255'],
            'realtime_call_id' => ['nullable', 'string', 'max:255'],
            'sip_call_id' => ['nullable', 'string', 'max:255'],
            'caller_id_name' => ['nullable', 'string', 'max:255'],
            'caller_id_number' => ['nullable', 'string', 'max:255'],
            'destination_number' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $session = $service->startSession($ai_receptionist, $payload);

        return response()->json([
            'session_uuid' => $session->session_uuid,
            'status' => $session->status,
        ], 201);
    }

    public function resolveDestination(Request $request, AiReceptionistSession $session, AiReceptionistService $service): JsonResponse
    {
        if ($response = $this->authorizeAgent($request)) {
            return $response;
        }

        $payload = $request->validate([
            'intent' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:64'],
            'target' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($service->recordBuiltInToolRun(
            $session,
            'resolve_destination',
            $payload,
            fn () => [
                'destination' => $service->resolveDestination($session, $payload),
            ]
        ));
    }

    public function transfer(Request $request, AiReceptionistSession $session, AiReceptionistService $service): JsonResponse
    {
        if ($response = $this->authorizeAgent($request)) {
            return $response;
        }

        $payload = $request->validate([
            'intent' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:64'],
            'target' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'array'],
        ]);

        return response()->json($service->recordBuiltInToolRun(
            $session,
            'transfer_call',
            $payload,
            function () use ($service, $session, $payload) {
                $destination = $payload['destination'] ?? $service->resolveDestination($session, $payload);

                return $service->transfer($session, $destination);
            }
        ));
    }

    public function resolveRoute(Request $request, AiReceptionistSession $session, AiReceptionistService $service): JsonResponse
    {
        if ($response = $this->authorizeAgent($request)) {
            return $response;
        }

        $payload = $request->validate([
            'intent' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($service->recordBuiltInToolRun(
            $session,
            'resolve_route',
            $payload,
            fn () => $service->resolveRoute($session, $payload)
        ));
    }

    public function warmTransfer(Request $request, AiReceptionistSession $session, AiReceptionistService $service): JsonResponse
    {
        if ($response = $this->authorizeAgent($request)) {
            return $response;
        }

        $payload = $request->validate([
            'route_uuid' => ['required', 'uuid'],
            'handoff_summary' => ['required', 'string', 'max:2000'],
        ]);

        return response()->json($service->recordBuiltInToolRun(
            $session,
            'warm_transfer_call',
            $payload,
            fn () => $service->warmTransfer($session, $payload)
        ));
    }

    public function completeWarmTransfer(Request $request, AiReceptionistSession $session, AiReceptionistService $service): JsonResponse
    {
        if ($response = $this->authorizeAgent($request)) {
            return $response;
        }

        $payload = $request->validate([
            'recipient_response' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($service->recordBuiltInToolRun(
            $session,
            'complete_warm_transfer',
            $payload,
            fn () => $service->completeWarmTransfer($session, $payload)
        ));
    }

    public function cancelWarmTransfer(Request $request, AiReceptionistSession $session, AiReceptionistService $service): JsonResponse
    {
        if ($response = $this->authorizeAgent($request)) {
            return $response;
        }

        $payload = $request->validate([
            'reason' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->json($service->recordBuiltInToolRun(
            $session,
            'cancel_warm_transfer',
            $payload,
            fn () => $service->cancelWarmTransfer($session, $payload)
        ));
    }

    public function sendRouteEmail(Request $request, AiReceptionistSession $session, AiReceptionistService $service): JsonResponse
    {
        if ($response = $this->authorizeAgent($request)) {
            return $response;
        }

        $payload = $request->validate([
            'route_uuid' => ['required', 'uuid'],
            'caller_name' => ['nullable', 'string', 'max:255'],
            'caller_number' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'urgency' => ['nullable', 'string', 'max:64'],
            'transcript' => ['nullable', 'string', 'max:50000'],
        ]);

        return response()->json($service->recordBuiltInToolRun(
            $session,
            'send_route_email',
            $payload,
            fn () => $service->sendRouteEmail($session, $payload)
        ));
    }

    public function endCall(Request $request, AiReceptionistSession $session, AiReceptionistService $service): JsonResponse
    {
        if ($response = $this->authorizeAgent($request)) {
            return $response;
        }

        $payload = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->json($service->recordBuiltInToolRun(
            $session,
            'end_call',
            $payload,
            fn () => $service->endCall($session, $payload)
        ));
    }

    public function runTool(Request $request, AiReceptionistSession $session, AiReceptionistService $service): JsonResponse
    {
        if ($response = $this->authorizeAgent($request)) {
            return $response;
        }

        $payload = $request->validate([
            'tool_name' => ['required', 'string', 'max:255'],
            'payload' => ['nullable', 'array'],
        ]);

        return response()->json($service->executeHttpTool(
            $session,
            $payload['tool_name'],
            $payload['payload'] ?? []
        ));
    }

    public function endSession(Request $request, AiReceptionistSession $session, AiReceptionistService $service): JsonResponse
    {
        if ($response = $this->authorizeAgent($request)) {
            return $response;
        }

        $payload = $request->validate([
            'status' => ['nullable', 'string', 'max:64'],
            'transcript' => ['nullable', 'string'],
            'summary' => ['nullable', 'array'],
            'error_message' => ['nullable', 'string'],
        ]);

        $session = $service->endSession($session, $payload);

        return response()->json([
            'session_uuid' => $session->session_uuid,
            'status' => $session->status,
        ]);
    }

    private function authorizeAgent(Request $request): ?JsonResponse
    {
        $configuredToken = (string) config('services.ai_receptionist.agent_token');
        $incomingToken = (string) $request->bearerToken();

        if ($configuredToken === '' || ! hash_equals($configuredToken, $incomingToken)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return null;
    }
}
