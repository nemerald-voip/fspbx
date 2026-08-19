<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAiProviderIntegrationRequest;
use App\Services\AiProviderIntegrationService;
use App\Services\AiProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AiProviderIntegrationController extends Controller
{
    public function show(AiProviderIntegrationService $service): JsonResponse
    {
        $this->authorizeRequest();
        $integration = $service->retell();

        return response()->json(['integration' => $this->payload($integration)]);
    }

    public function update(StoreAiProviderIntegrationRequest $request, AiProviderIntegrationService $service): JsonResponse
    {
        try {
            $integration = $service->save($request->validated());

            return response()->json([
                'integration' => $this->payload($integration),
                'messages' => ['success' => [__('Retell integration saved and provider ACL synchronized.')]],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['messages' => ['error' => [$exception->getMessage()]]], 500);
        }
    }

    public function test(
        StoreAiProviderIntegrationRequest $request,
        AiProviderIntegrationService $service,
        AiProviderRegistry $providers,
    ): JsonResponse
    {
        $validated = $request->validated();
        $storedIntegration = $service->retell();
        $integration = $storedIntegration->replicate();
        $integration->provider = $storedIntegration->provider;

        if (filled($validated['api_key'] ?? null)) {
            $integration->api_key = trim($validated['api_key']);
        }

        try {
            $providers->client($integration->provider)->test($integration);

            return response()->json(['messages' => ['success' => [__('Retell API connection succeeded.')]]]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'messages' => [
                    'error' => [__('Retell API connection failed: :message', ['message' => $exception->getMessage()])],
                ],
            ], 502);
        }
    }

    public function providerAgents(Request $request, AiProviderIntegrationService $service, AiProviderRegistry $providers): JsonResponse
    {
        $this->authorizeRequest();

        try {
            $provider = strtolower(trim((string) $request->input('provider', $providers->names()[0] ?? '')));

            return response()->json([
                'agents' => $providers->client($provider)->listAgents($service->integration($provider)),
            ]);
        } catch (Throwable $exception) {
            return response()->json(['messages' => ['error' => [$exception->getMessage()]]], 502);
        }
    }

    private function payload($integration): array
    {
        return [
            'provider' => 'retell',
            'has_api_key' => $integration->hasApiKey(),
            'public_sip_host' => $integration->public_sip_host ?: $this->appHost(),
            'provider_cidrs' => $integration->provider_cidrs ?: AiProviderIntegrationService::RETELL_CIDRS,
            'enabled' => $integration->enabled,
        ];
    }

    private function appHost(): ?string
    {
        $appUrl = trim((string) config('app.url'));

        if ($appUrl === '') {
            return null;
        }

        $host = parse_url($appUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            $host = parse_url('https://' . ltrim($appUrl, '/'), PHP_URL_HOST);
        }

        return is_string($host) && $host !== '' ? $host : null;
    }

    private function authorizeRequest(): void
    {
        abort_unless(isSuperAdmin() && userCheckPermission('ai_agent_manage_integration'), 403);
    }
}
