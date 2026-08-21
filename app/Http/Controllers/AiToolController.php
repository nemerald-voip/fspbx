<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvokeAiSendEmailToolRequest;
use App\Models\AiAgent;
use App\Services\AiTools\AiSendEmailToolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AiToolController extends Controller
{
    public function sendEmail(InvokeAiSendEmailToolRequest $request, AiSendEmailToolService $service): JsonResponse
    {
        $validated = $request->validated();
        $agentUuid = $this->agentUuid($validated['call']['custom_sip_headers']);

        if (! $agentUuid || ! Str::isUuid($agentUuid)) {
            return response()->json(['success' => false, 'message' => 'The originating FS PBX AI agent could not be verified.'], 403);
        }

        $agent = AiAgent::query()
            ->whereKey($agentUuid)
            ->where('provider', 'retell')
            ->where('inbound_agent_id', $validated['call']['agent_id'])
            ->where('enabled', true)
            ->where('provisioning_status', 'synced')
            ->first();

        if (! $agent) {
            return response()->json(['success' => false, 'message' => 'The Retell agent is not authorized for this FS PBX destination.'], 403);
        }

        $result = $service->queue($agent, $validated['call']['call_id'], $validated['args']);

        return response()->json([
            'success' => true,
            'message' => $result['queued']
                ? 'The email has been queued for delivery.'
                : 'This email request was already accepted.',
        ]);
    }

    private function agentUuid(array $headers): ?string
    {
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'x-fspbx-agent-uuid') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
