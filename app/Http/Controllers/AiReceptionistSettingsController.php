<?php

namespace App\Http\Controllers;

use App\Services\AiReceptionistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\Process\Process;

class AiReceptionistSettingsController extends Controller
{
    private const SUPERVISOR_PROGRAM = 'ai-receptionist-agent';
    private const AGENT_VENV_PYTHON = '/opt/fspbx/ai-receptionist-agent/.venv/bin/python';

    public function show(Request $request, AiReceptionistService $service): JsonResponse
    {
        $validated = $request->validate([
            'domain_uuid' => ['nullable', 'uuid'],
        ]);

        if (! $this->canManageScope($validated['domain_uuid'] ?? null)) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return response()->json($service->freshResolvedSettings($validated['domain_uuid'] ?? null, includeSecrets: true));
    }

    public function store(Request $request, AiReceptionistService $service): JsonResponse
    {
        $validated = $request->validate([
            'domain_uuid' => ['nullable', 'uuid'],
            'enabled' => ['required', 'boolean'],
            'default_engine' => ['nullable', Rule::in(array_keys(AiReceptionistService::ENGINES))],
            'agent_runtime' => ['nullable', Rule::in(array_keys(AiReceptionistService::AGENT_RUNTIMES))],
            'livekit_url' => ['nullable', 'string', 'max:1024'],
            'livekit_api_key' => ['nullable', 'string', 'max:1024'],
            'livekit_api_secret' => ['nullable', 'string', 'max:4096'],
            'provider_config' => ['nullable', 'array'],
        ]);

        $domainUuid = $validated['domain_uuid'] ?? null;

        if (! $this->canManageScope($domainUuid)) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $service->saveSettings($validated, $domainUuid);

        return response()->json([
            'messages' => ['success' => ['AI receptionist settings saved.']],
        ], 201);
    }

    public function destroy(Request $request, AiReceptionistService $service): JsonResponse
    {
        $validated = $request->validate([
            'domain_uuid' => ['required', 'uuid'],
        ]);

        if (! $this->canManageScope($validated['domain_uuid'])) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $deleted = $service->deleteSettingsOverride($validated['domain_uuid']);

        return response()->json([
            'messages' => ['success' => [
                $deleted ? 'Reverted to defaults.' : 'No custom options found; already using defaults.',
            ]],
        ]);
    }

    public function serviceStatus(AiReceptionistService $service): JsonResponse
    {
        if (! userCheckPermission('ai_receptionist_settings')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return response()->json($this->serviceStatusPayload($service));
    }

    public function serviceControl(Request $request, AiReceptionistService $service): JsonResponse
    {
        if (! userCheckPermission('ai_receptionist_settings')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['start', 'stop', 'restart'])],
        ]);

        $settings = $service->freshResolvedSettings(includeSecrets: true);
        if (! $service->usesLocalAgentRuntime($settings)) {
            return response()->json([
                'messages' => ['error' => ['The AI Receptionist agent is configured to run outside this server.']],
                'service' => $this->serviceStatusPayload($service),
            ], 409);
        }

        $readinessErrors = $this->agentReadinessErrors($service);
        if ($validated['action'] !== 'stop' && $readinessErrors !== []) {
            return response()->json([
                'messages' => ['error' => ['AI Receptionist agent is not ready to start.']],
                'readiness_errors' => $readinessErrors,
                'service' => $this->serviceStatusPayload($service, $readinessErrors),
            ], 422);
        }

        $result = $this->runSupervisor([$validated['action'], self::SUPERVISOR_PROGRAM]);
        $status = $this->serviceStatusPayload($service);

        return response()->json([
            'messages' => [
                $result['success'] ? 'success' : 'error' => [
                    $result['success']
                        ? 'AI Receptionist agent command submitted.'
                        : 'AI Receptionist agent command failed.',
                ],
            ],
            'output' => $result['output'],
            'service' => $status,
        ], $result['success'] ? 200 : 409);
    }

    private function canManageScope(?string $domainUuid): bool
    {
        if (blank($domainUuid)) {
            return userCheckPermission('ai_receptionist_settings');
        }

        return userCheckPermission('account_settings_list_view')
            && $domainUuid === session('domain_uuid');
    }

    private function serviceStatusPayload(AiReceptionistService $service, ?array $readinessErrors = null): array
    {
        $settings = $service->freshResolvedSettings(includeSecrets: true);
        $runtime = $settings['agent_runtime'] ?? 'local_worker';

        if (! $service->usesLocalAgentRuntime($settings)) {
            $readinessErrors ??= $this->agentReadinessErrors($service);

            return [
                'status' => 'external',
                'raw' => AiReceptionistService::AGENT_RUNTIMES[$runtime] ?? 'External agent runtime',
                'ready' => $readinessErrors === [],
                'readiness_errors' => $readinessErrors,
                'agent_runtime' => $runtime,
                'agent_runtime_label' => AiReceptionistService::AGENT_RUNTIMES[$runtime] ?? $runtime,
                'livekit_hosting' => $settings['livekit_hosting'] ?? null,
            ];
        }

        $result = $this->runSupervisor(['status', self::SUPERVISOR_PROGRAM]);
        $raw = trim($result['output']);
        $readinessErrors ??= $this->agentReadinessErrors($service);

        return [
            'status' => $this->parseSupervisorStatus($raw, $result['success']),
            'raw' => $raw,
            'ready' => $readinessErrors === [],
            'readiness_errors' => $readinessErrors,
            'agent_runtime' => $runtime,
            'agent_runtime_label' => AiReceptionistService::AGENT_RUNTIMES[$runtime] ?? $runtime,
            'livekit_hosting' => $settings['livekit_hosting'] ?? null,
        ];
    }

    private function agentReadinessErrors(AiReceptionistService $service): array
    {
        $errors = [];
        $settings = $service->freshResolvedSettings(includeSecrets: true);

        if ($service->usesLocalAgentRuntime($settings) && ! is_file(self::AGENT_VENV_PYTHON)) {
            $errors[] = 'Python virtual environment is missing at /opt/fspbx/ai-receptionist-agent/.venv.';
        } elseif ($service->usesLocalAgentRuntime($settings) && ! $this->commandSuccessful([self::AGENT_VENV_PYTHON, '-B', '-c', 'import livekit'])) {
            $errors[] = 'Python dependencies are not installed.';
        }

        if (blank(config('services.ai_receptionist.agent_token'))) {
            $errors[] = 'AI_RECEPTIONIST_AGENT_TOKEN is not configured.';
        }

        if (! ($settings['enabled'] ?? false)) {
            $errors[] = 'AI Receptionist system settings are not enabled.';
        }

        $requiresStoredLiveKitCredentials = in_array(
            $settings['agent_runtime'] ?? 'local_worker',
            ['local_worker', 'external_worker'],
            true
        );

        if ($requiresStoredLiveKitCredentials && (
            blank($settings['livekit_url'] ?? null)
                || blank($settings['livekit_api_key'] ?? null)
                || blank($settings['livekit_api_secret'] ?? null)
        )) {
            $errors[] = 'LiveKit URL, API key, and API secret must be saved in system settings.';
        } elseif (! $requiresStoredLiveKitCredentials && blank($settings['livekit_url'] ?? null)) {
            $errors[] = 'LiveKit URL must be saved in system settings.';
        }

        return $errors;
    }

    private function parseSupervisorStatus(string $output, bool $commandSucceeded): string
    {
        if (! $commandSucceeded) {
            return str_contains(strtolower($output), 'no such process') ? 'not_installed' : 'unknown';
        }

        return match (true) {
            str_contains($output, 'RUNNING') => 'running',
            str_contains($output, 'STOPPED') => 'stopped',
            str_contains($output, 'STARTING') => 'starting',
            str_contains($output, 'BACKOFF') => 'backoff',
            str_contains($output, 'FATAL') => 'fatal',
            str_contains($output, 'EXITED') => 'exited',
            default => 'unknown',
        };
    }

    private function runSupervisor(array $arguments): array
    {
        $commands = [
            array_merge(['supervisorctl'], $arguments),
            array_merge(['/usr/bin/supervisorctl'], $arguments),
            array_merge(['sudo', '-n', 'supervisorctl'], $arguments),
            array_merge(['sudo', '-n', '/usr/bin/supervisorctl'], $arguments),
        ];

        $lastOutput = '';

        foreach ($commands as $command) {
            $process = new Process($command);
            $process->setTimeout(30);
            $process->run();

            $lastOutput = trim($process->getOutput() . "\n" . $process->getErrorOutput());

            if ($process->isSuccessful()) {
                return ['success' => true, 'output' => $lastOutput];
            }
        }

        return ['success' => false, 'output' => $lastOutput];
    }

    private function commandSuccessful(array $command): bool
    {
        $process = new Process($command);
        $process->setTimeout(30);
        $process->run();

        return $process->isSuccessful();
    }
}
