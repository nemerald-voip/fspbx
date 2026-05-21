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
            'agent_runtime' => $settings['agent_runtime'] ?? 'local_worker',
            'livekit_url' => $settings['livekit_url'] ?? null,
            'livekit_api_key' => $settings['livekit_api_key'] ?? null,
            'livekit_api_secret' => $settings['livekit_api_secret'] ?? null,
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
            'livekit_room' => ['nullable', 'string', 'max:255'],
            'livekit_participant' => ['nullable', 'string', 'max:255'],
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

        return response()->json([
            'destination' => $service->resolveDestination($session, $payload),
        ]);
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

        $destination = $payload['destination'] ?? $service->resolveDestination($session, $payload);

        return response()->json($service->transfer($session, $destination));
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
