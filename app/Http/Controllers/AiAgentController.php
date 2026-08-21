<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAiAgentRequest;
use App\Http\Requests\SyncAiProviderToolsRequest;
use App\Http\Requests\UpdateAiAgentRequest;
use App\Jobs\QueueAiProviderToolSyncs;
use App\Models\AiAgent;
use App\Models\Domain;
use App\Services\AiAgentService;
use App\Services\AiProviderRegistry;
use App\Services\AiTools\AiProviderToolSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

class AiAgentController extends Controller
{
    public function index()
    {
        if (! $this->allowed('ai_agent_view')) {
            return redirect('/');
        }

        return Inertia::render('AiAgents', [
            'routes' => [
                'data' => route('ai-agents.data'),
                'store' => route('ai-agents.store'),
                'item_options' => route('ai-agents.item-options'),
                'integration' => route('ai-agents.integration.show'),
                'integration_update' => route('ai-agents.integration.update'),
                'integration_test' => route('ai-agents.integration.test'),
                'provider_agents' => route('ai-agents.provider-agents'),
                'tool_status' => route('ai-agents.tool-status'),
                'sync_tools' => route('ai-agents.sync-tools'),
            ],
            'permissions' => [
                'create' => $this->allowed('ai_agent_create'),
                'update' => $this->allowed('ai_agent_update'),
                'destroy' => $this->allowed('ai_agent_delete'),
                'manage_domain' => $this->allowed('ai_agent_manage_domain'),
                'manage_provider' => $this->allowed('ai_agent_manage_provider'),
                'manage_integration' => $this->allowed('ai_agent_manage_integration'),
                'sync_tools' => $this->allowed('ai_agent_manage_integration'),
            ],
        ]);
    }

    public function data(Request $request)
    {
        abort_unless($this->allowed('ai_agent_view'), 403);

        return QueryBuilder::for(AiAgent::class)
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $value = trim((string) $value);
                    if ($value !== '') {
                        $query->where(function ($query) use ($value) {
                            $query->where('name', 'ilike', "%{$value}%")
                                ->orWhere('extension', 'ilike', "%{$value}%")
                                ->orWhere('ai_agent_uuid', 'ilike', "%{$value}%")
                                ->orWhere('inbound_agent_name', 'ilike', "%{$value}%")
                                ->orWhere('outbound_agent_name', 'ilike', "%{$value}%");
                        });
                    }
                }),
            ])
            ->allowedSorts(['name', 'extension', 'enabled', 'provisioning_status', 'last_synced_at'])
            ->defaultSort('name')
            ->paginate(50);
    }

    public function itemOptions(Request $request, AiProviderRegistry $providers): JsonResponse
    {
        abort_unless($this->allowed($request->filled('item_uuid') ? 'ai_agent_update' : 'ai_agent_create'), 403);
        $canManageDomain = $this->allowed('ai_agent_manage_domain');
        $canManageProvider = $this->allowed('ai_agent_manage_provider');

        $agent = $request->filled('item_uuid')
            ? AiAgent::query()->findOrFail((string) $request->string('item_uuid'))
            : new AiAgent([
                'domain_uuid' => session('domain_uuid'),
                'recording_policy' => 'inherit',
                'enabled' => true,
                'provider' => $providers->names()[0] ?? null,
            ]);

        if (! $agent->exists) {
            $agent->extension = $agent->generateUniqueSequenceNumber($agent->domain_uuid);
        }

        return response()->json([
            'item' => $agent,
            'domains' => $canManageDomain
                ? Domain::query()
                    ->where('domain_enabled', 'true')
                    ->orderByRaw('coalesce(domain_description, domain_name)')
                    ->get(['domain_uuid', 'domain_name', 'domain_description'])
                    ->map(fn (Domain $domain) => [
                        'value' => $domain->domain_uuid,
                        'label' => $domain->domain_description ?: $domain->domain_name,
                    ])
                : [],
            'providers' => $canManageProvider ? $providers->options() : [],
            'routes' => [
                'submit' => $agent->exists
                    ? route('ai-agents.update', $agent)
                    : route('ai-agents.store'),
            ],
        ]);
    }

    public function toolStatus(AiProviderToolSyncService $syncs): JsonResponse
    {
        abort_unless($this->allowed('ai_agent_view'), 403);

        return response()->json(['tools' => $syncs->summary()]);
    }

    public function syncTools(SyncAiProviderToolsRequest $request): JsonResponse
    {
        QueueAiProviderToolSyncs::dispatch(
            (bool) $request->validated('force', false),
            'manual',
        );

        return response()->json([
            'messages' => ['success' => [__('AI provider tool synchronization queued.')]],
        ]);
    }

    public function store(StoreAiAgentRequest $request, AiAgentService $service): JsonResponse
    {
        try {
            $agent = $service->create($request->validated());

            return response()->json(['item' => $agent, 'messages' => ['success' => [__('AI agent created and synchronized.')]]], 201);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['messages' => ['error' => [__('The AI agent was saved, but provider synchronization failed: :message', ['message' => $exception->getMessage()])]]], 502);
        }
    }

    public function update(UpdateAiAgentRequest $request, AiAgent $ai_agent, AiAgentService $service): JsonResponse
    {
        try {
            return response()->json(['item' => $service->update($ai_agent, $request->validated()), 'messages' => ['success' => [__('AI agent updated.')]]]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['messages' => ['error' => [__('Provider synchronization failed: :message', ['message' => $exception->getMessage()])]]], 502);
        }
    }

    public function toggle(AiAgent $ai_agent, AiAgentService $service): JsonResponse
    {
        abort_unless($this->allowed('ai_agent_update'), 403);

        return $this->agentAction(fn () => $service->toggle($ai_agent), __('AI agent status updated.'));
    }

    public function retry(AiAgent $ai_agent, AiAgentService $service): JsonResponse
    {
        abort_unless($this->allowed('ai_agent_update'), 403);

        return $this->agentAction(fn () => $service->retry($ai_agent), __('AI agent synchronized.'));
    }

    public function refresh(AiAgent $ai_agent, AiAgentService $service): JsonResponse
    {
        abort_unless($this->allowed('ai_agent_update'), 403);

        return $this->agentAction(fn () => $service->refresh($ai_agent), __('Provider status refreshed.'));
    }

    public function destroy(AiAgent $ai_agent, AiAgentService $service): JsonResponse
    {
        abort_unless($this->allowed('ai_agent_delete'), 403);

        try {
            $service->delete($ai_agent);

            return response()->json(['messages' => ['success' => [__('AI agent deleted from the provider and FS PBX.')]]]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['messages' => ['error' => [__('Provider deletion failed. The local AI agent was not deleted: :message', ['message' => $exception->getMessage()])]]], 502);
        }
    }

    private function agentAction(callable $action, string $message): JsonResponse
    {
        try {
            return response()->json(['item' => $action(), 'messages' => ['success' => [$message]]]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['messages' => ['error' => [$exception->getMessage()]]], 502);
        }
    }

    private function allowed(string $permission): bool
    {
        return isSuperAdmin() && userCheckPermission($permission);
    }
}
