<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBasicQueueAgentRequest;
use App\Http\Requests\StoreBasicQueueRequest;
use App\Http\Requests\UpdateBasicQueueAgentRequest;
use App\Http\Requests\UpdateBasicQueueRequest;
use App\Models\CallCenterAgents;
use App\Models\CallCenterQueues;
use App\Models\Extensions;
use App\Services\BasicQueueService;
use App\Services\CallRoutingOptionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BasicQueueController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (! userCheckPermission('call_center_queue_view')) {
            return redirect('/');
        }

        return Inertia::render('BasicQueue', [
            'routes' => [
                'current_page' => route('basic-queues.index'),
                'queue_data' => route('basic-queues.queues.data'),
                'queue_store' => route('basic-queues.queues.store'),
                'queue_item_options' => route('basic-queues.queues.item.options'),
                'get_routing_options' => route('routing.options'),
                'queue_select_all' => route('basic-queues.queues.select.all'),
                'queue_bulk_delete' => route('basic-queues.queues.bulk.delete'),
                'agent_data' => route('basic-queues.agents.data'),
                'agent_store' => route('basic-queues.agents.store'),
                'agent_item_options' => route('basic-queues.agents.item.options'),
                'agent_select_all' => route('basic-queues.agents.select.all'),
                'agent_bulk_delete' => route('basic-queues.agents.bulk.delete'),
                'agent_status' => route('basic-queues.agents.status'),
                'wallboard' => '/app/call_center_wallboard/call_center_wallboard.php',
                'queue_import' => '/app/call_center_imports/call_center_imports.php?import_type=call_center_queues',
                'agent_import' => '/app/call_center_imports/call_center_imports.php?import_type=call_center_agents',
            ],
            'permissions' => [
                'queues' => [
                    'create' => userCheckPermission('call_center_queue_add'),
                    'update' => userCheckPermission('call_center_queue_edit'),
                    'destroy' => userCheckPermission('call_center_queue_delete'),
                    'view_all' => userCheckPermission('call_center_all'),
                    'imports' => userCheckPermission('call_center_imports'),
                    'wallboard' => userCheckPermission('call_center_wallboard'),
                ],
                'agents' => [
                    'view' => userCheckPermission('call_center_agent_view'),
                    'create' => userCheckPermission('call_center_agent_add'),
                    'update' => userCheckPermission('call_center_agent_edit'),
                    'destroy' => userCheckPermission('call_center_agent_delete'),
                    'imports' => userCheckPermission('call_center_imports'),
                ],
            ],
        ]);
    }

    public function storeQueue(StoreBasicQueueRequest $request, BasicQueueService $service): JsonResponse
    {
        $queue = $service->saveQueue($request->validated());

        return response()->json([
            'messages' => ['success' => ['Basic queue created.']],
            'call_center_queue_uuid' => $queue->call_center_queue_uuid,
        ], 201);
    }

    public function updateQueue(UpdateBasicQueueRequest $request, CallCenterQueues $queue, BasicQueueService $service): JsonResponse
    {
        if ($queue->domain_uuid !== session('domain_uuid')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $service->saveQueue($request->validated(), $queue);

        return response()->json([
            'messages' => ['success' => ['Basic queue updated.']],
        ]);
    }

    public function storeAgent(StoreBasicQueueAgentRequest $request, BasicQueueService $service): JsonResponse
    {
        $agent = $service->saveAgent($request->validated());

        return response()->json([
            'messages' => ['success' => ['Agent created.']],
            'call_center_agent_uuid' => $agent->call_center_agent_uuid,
        ], 201);
    }

    public function updateAgent(UpdateBasicQueueAgentRequest $request, CallCenterAgents $agent, BasicQueueService $service): JsonResponse
    {
        if ($agent->domain_uuid !== session('domain_uuid')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $service->saveAgent($request->validated(), $agent);

        return response()->json([
            'messages' => ['success' => ['Agent updated.']],
        ]);
    }

    public function agentStatus()
    {
        if (! userCheckPermission('call_center_agent_view')) {
            return redirect('/');
        }

        return Inertia::render('BasicQueueAgentStatus', [
            'routes' => [
                'back' => route('basic-queues.index'),
                'data' => route('basic-queues.agents.status.data'),
                'update' => route('basic-queues.agents.status.update'),
            ],
            'permissions' => [
                'update' => userCheckPermission('call_center_agent_edit'),
            ],
        ]);
    }

    public function getAgentStatusData(): JsonResponse
    {
        if (! userCheckPermission('call_center_agent_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $agents = CallCenterAgents::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('agent_name')
            ->get([
                'call_center_agent_uuid',
                'agent_name',
                'agent_id',
                'agent_type',
                'agent_contact',
                'agent_status',
            ]);

        $runtimeAgents = collect($this->callCenterList('callcenter_config agent list'))
            ->keyBy('name');

        $rows = $agents->map(function (CallCenterAgents $agent) use ($runtimeAgents) {
            $runtime = $runtimeAgents->get($agent->call_center_agent_uuid, []);

            return [
                'call_center_agent_uuid' => $agent->call_center_agent_uuid,
                'agent_name' => $agent->agent_name,
                'agent_id' => $agent->agent_id,
                'agent_type' => $agent->agent_type,
                'agent_contact' => $agent->agent_contact,
                'default_status' => $agent->agent_status,
                'runtime_status' => $runtime['status'] ?? $agent->agent_status ?? 'Logged Out',
                'runtime_state' => $runtime['state'] ?? null,
                'calls_answered' => $runtime['calls_answered'] ?? null,
                'no_answer_count' => $runtime['no_answer_count'] ?? null,
                'last_status_change' => $runtime['last_status_change'] ?? null,
            ];
        })->values();

        return response()->json([
            'data' => $rows,
            'status_options' => $this->agentStatusOptions(),
            'runtime_available' => $this->eventSocketAvailable(),
        ]);
    }

    public function updateAgentStatus(Request $request): JsonResponse
    {
        if (! userCheckPermission('call_center_agent_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $validator = Validator::make($request->all(), [
            'agent_uuid' => ['required', 'uuid'],
            'status' => ['required', 'in:Available,Available (On Demand),On Break,Logged Out'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'messages' => ['error' => ['Invalid status request.']],
            ], 422);
        }

        $agent = CallCenterAgents::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereKey($request->input('agent_uuid'))
            ->firstOrFail();

        if (! $this->eventSocketAvailable()) {
            return response()->json(['messages' => ['error' => ['FreeSWITCH event socket is not available.']]], 503);
        }

        $status = $request->input('status');
        $response = $this->callCenterCommand(sprintf(
            "api callcenter_config agent set status %s '%s'",
            $agent->call_center_agent_uuid,
            str_replace("'", "\\'", $status)
        ));

        if (in_array($status, ['Available', 'Logged Out'], true)) {
            $this->callCenterCommand(sprintf(
                "api callcenter_config agent set state %s 'Waiting'",
                $agent->call_center_agent_uuid
            ));
        }

        return response()->json([
            'response' => $response,
            'messages' => ['success' => ["{$agent->agent_name} status updated."]],
        ]);
    }

    public function getQueueData(Request $request)
    {
        if (! userCheckPermission('call_center_queue_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return $this->scopedQueues($request)
            ->select([
                'domain_uuid',
                'call_center_queue_uuid',
                'dialplan_uuid',
                'queue_name',
                'queue_extension',
                'queue_strategy',
                'queue_moh_sound',
                'queue_tier_rules_apply',
                'queue_description',
            ])
            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description');
            }])
            ->withCount('agents')
            ->allowedSorts([
                'queue_name',
                'queue_extension',
                'queue_strategy',
                'queue_tier_rules_apply',
                'queue_description',
            ])
            ->defaultSort('queue_name')
            ->paginate($this->perPage)
            ->appends($request->query());
    }

    public function getAgentData(Request $request)
    {
        if (! userCheckPermission('call_center_agent_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return $this->scopedAgents($request)
            ->select([
                'domain_uuid',
                'call_center_agent_uuid',
                'agent_name',
                'agent_type',
                'agent_call_timeout',
                'agent_id',
                'agent_contact',
                'agent_status',
                'agent_max_no_answer',
            ])
            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description');
            }])
            ->withCount('queues')
            ->allowedSorts([
                'agent_name',
                'agent_id',
                'agent_type',
                'agent_call_timeout',
                'agent_status',
                'agent_max_no_answer',
            ])
            ->defaultSort('agent_name')
            ->paginate($this->perPage)
            ->appends($request->query());
    }

    public function getQueueItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('call_center_queue_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (! $itemUuid && ! userCheckPermission('call_center_queue_add')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $item = $itemUuid
            ? CallCenterQueues::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($itemUuid)
                ->with(['agents'])
                ->firstOrFail()
            : new CallCenterQueues();

        if ($itemUuid) {
            $item->append([
                'timeout_action',
                'timeout_target_uuid',
                'timeout_target_name',
                'timeout_target_extension',
            ]);
        } else {
            $item->queue_extension = $item->generateUniqueSequenceNumber();
        }

        $tiers = $itemUuid
            ? $item->agents
                ->map(fn (CallCenterAgents $agent) => [
                    'call_center_agent_uuid' => $agent->call_center_agent_uuid,
                    'agent_name' => $agent->agent_name,
                    'tier_level' => (int) ($agent->pivot?->tier_level ?? 1),
                    'tier_position' => (int) ($agent->pivot?->tier_position ?? 1),
                ])
                ->values()
            : collect();

        return response()->json([
            'item' => $item,
            'tiers' => $tiers,
            'agent_options' => $this->agentOptions(),
            'routing_types' => (new CallRoutingOptionsService)->routingTypes,
            'music_on_hold_options' => getMusicOnHoldCollection(session('domain_uuid')),
            'routes' => [
                'store_route' => route('basic-queues.queues.store'),
                'update_route' => $itemUuid ? route('basic-queues.queues.update', ['queue' => $item->call_center_queue_uuid]) : null,
                'get_routing_options' => route('routing.options'),
            ],
        ]);
    }

    public function getAgentItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('call_center_agent_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (! $itemUuid && ! userCheckPermission('call_center_agent_add')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $item = $itemUuid
            ? CallCenterAgents::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($itemUuid)
                ->firstOrFail()
            : new CallCenterAgents();

        return response()->json([
            'item' => $item,
            'contact_options' => $this->contactOptions(),
            'routes' => [
                'store_route' => route('basic-queues.agents.store'),
                'update_route' => $itemUuid ? route('basic-queues.agents.update', ['agent' => $item->call_center_agent_uuid]) : null,
            ],
        ]);
    }

    public function selectAllQueues(Request $request): JsonResponse
    {
        if (! userCheckPermission('call_center_queue_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return response()->json([
            'items' => $this->scopedQueues($request)
                ->defaultSort('queue_name')
                ->pluck('call_center_queue_uuid'),
            'messages' => ['success' => ['All matching queues selected.']],
        ]);
    }

    public function selectAllAgents(Request $request): JsonResponse
    {
        if (! userCheckPermission('call_center_agent_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return response()->json([
            'items' => $this->scopedAgents($request)
                ->defaultSort('agent_name')
                ->pluck('call_center_agent_uuid'),
            'messages' => ['success' => ['All matching agents selected.']],
        ]);
    }

    public function bulkDeleteQueues(Request $request, BasicQueueService $service): JsonResponse
    {
        if (! userCheckPermission('call_center_queue_delete')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json(['messages' => ['error' => ['No queues selected.']]], 422);
        }

        $queues = CallCenterQueues::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('call_center_queue_uuid', $uuids)
            ->get();

        $deleted = $service->deleteQueues($queues);

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} basic queue(s)."]],
        ]);
    }

    public function bulkDeleteAgents(Request $request, BasicQueueService $service): JsonResponse
    {
        if (! userCheckPermission('call_center_agent_delete')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json(['messages' => ['error' => ['No agents selected.']]], 422);
        }

        $agents = CallCenterAgents::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('call_center_agent_uuid', $uuids)
            ->get();

        $deleted = $service->deleteAgents($agents);

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} agent(s)."]],
        ]);
    }

    private function scopedQueues(Request $request): QueryBuilder
    {
        return QueryBuilder::for(CallCenterQueues::class)
            ->when(! userCheckPermission('call_center_all') || ! $request->boolean('filter.showGlobal'), function ($query) {
                $query->where(function ($query) {
                    $query->where('domain_uuid', session('domain_uuid'))
                        ->orWhereNull('domain_uuid');
                });
            })
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('queue_name', 'ilike', "%{$needle}%")
                            ->orWhere('queue_extension', 'ilike', "%{$needle}%")
                            ->orWhere('queue_strategy', 'ilike', "%{$needle}%")
                            ->orWhere('queue_description', 'ilike', "%{$needle}%");
                    });
                }),
                AllowedFilter::callback('showGlobal', function ($query, $value) {}),
            ]);
    }

    private function scopedAgents(Request $request): QueryBuilder
    {
        return QueryBuilder::for(CallCenterAgents::class)
            ->when(! userCheckPermission('call_center_all') || ! $request->boolean('filter.showGlobal'), function ($query) {
                $query->where(function ($query) {
                    $query->where('domain_uuid', session('domain_uuid'))
                        ->orWhereNull('domain_uuid');
                });
            })
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('agent_name', 'ilike', "%{$needle}%")
                            ->orWhere('agent_id', 'ilike', "%{$needle}%")
                            ->orWhere('agent_contact', 'ilike', "%{$needle}%")
                            ->orWhere('agent_status', 'ilike', "%{$needle}%");
                    });
                }),
                AllowedFilter::callback('showGlobal', function ($query, $value) {}),
            ]);
    }

    private function agentOptions(): array
    {
        return CallCenterAgents::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('agent_name')
            ->get(['call_center_agent_uuid', 'agent_name', 'agent_id'])
            ->map(fn (CallCenterAgents $agent) => [
                'value' => $agent->call_center_agent_uuid,
                'label' => trim($agent->agent_name . ($agent->agent_id ? " ({$agent->agent_id})" : '')),
            ])
            ->values()
            ->all();
    }

    private function contactOptions(): array
    {
        $domainName = session('domain_name');

        return Extensions::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('extension')
            ->get(['extension', 'effective_caller_id_name'])
            ->map(fn (Extensions $extension) => [
                'value' => "user/{$extension->extension}@{$domainName}",
                'label' => trim($extension->extension . ($extension->effective_caller_id_name ? " - {$extension->effective_caller_id_name}" : '')),
            ])
            ->values()
            ->all();
    }

    private function agentStatusOptions(): array
    {
        return [
            ['value' => 'Available', 'label' => 'Available'],
            ['value' => 'Available (On Demand)', 'label' => 'Available On Demand'],
            ['value' => 'On Break', 'label' => 'On Break'],
            ['value' => 'Logged Out', 'label' => 'Logged Out'],
        ];
    }

    private function callCenterList(string $command): array
    {
        $response = $this->callCenterCommand('api ' . $command);

        if (blank($response)) {
            return [];
        }

        return $this->pipeDelimitedRows((string) $response);
    }

    private function callCenterCommand(string $command): ?string
    {
        $fp = $this->eventSocket();

        if (! $fp) {
            return null;
        }

        return trim((string) event_socket_request($fp, $command));
    }

    private function eventSocketAvailable(): bool
    {
        return (bool) $this->eventSocket();
    }

    private function eventSocket()
    {
        static $fp = false;
        static $attempted = false;

        if ($attempted) {
            return $fp;
        }

        $attempted = true;
        $fp = event_socket_create(
            config('eventsocket.ip'),
            config('eventsocket.port'),
            config('eventsocket.password')
        );

        return $fp;
    }

    private function pipeDelimitedRows(string $contents): array
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', trim($contents)))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values();

        if ($lines->count() < 2) {
            return [];
        }

        $headers = str_getcsv($lines->shift(), '|');

        return $lines
            ->map(function ($line) use ($headers) {
                $values = str_getcsv($line, '|');
                $row = [];

                foreach ($headers as $index => $header) {
                    $row[$header] = $values[$index] ?? null;
                }

                return $row;
            })
            ->filter(fn ($row) => ! empty($row['name']))
            ->values()
            ->all();
    }

    private function validatedUuids(Request $request): array
    {
        return collect($request->input('items', []))
            ->filter(fn ($uuid) => is_string($uuid) && preg_match('/^[0-9a-fA-F-]{36}$/', $uuid))
            ->values()
            ->all();
    }
}
