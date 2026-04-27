<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCallFlowRequest;
use App\Http\Requests\UpdateCallFlowRequest;
use App\Models\CallFlows;
use App\Models\CallFlowGroup;
use App\Services\CallFlowService;
use App\Services\CallRoutingOptionsService;
use App\Services\FreeswitchEslService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CallFlowController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (!userCheckPermission('call_flow_view')) {
            return redirect('/');
        }

        return Inertia::render('CallFlows', [
            'routes' => [
                'current_page' => route('call-flows.index'),
                'data_route' => route('call-flows.data'),
                'select_all' => route('call-flows.select.all'),
                'bulk_delete' => route('call-flows.bulk.delete'),
                'bulk_copy' => route('call-flows.bulk.copy'),
                'bulk_toggle' => route('call-flows.bulk.toggle'),
                'store' => route('call-flows.store'),
                'item_options' => route('call-flows.item.options'),
            ],
            'permissions' => [
                'create' => userCheckPermission('call_flow_add'),
                'update' => userCheckPermission('call_flow_edit'),
                'destroy' => userCheckPermission('call_flow_delete'),
                'view_global' => userCheckPermission('call_flow_all'),
                'context' => userCheckPermission('call_flow_context'),
            ],
        ]);
    }

    public function store(StoreCallFlowRequest $request): JsonResponse
    {
        if (!userCheckPermission('call_flow_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $validated = app(CallFlowService::class)->buildSaveData(
            $request->validated(),
            null,
            $this->currentDomainName()
        );

        try {
            DB::beginTransaction();

            $callFlowUuid = (string) Str::uuid();
            $dialplanUuid = (string) Str::uuid();
            $this->saveCallFlow($validated, $callFlowUuid, $dialplanUuid);

            DB::commit();

            $this->afterDialplanChange(collect([$validated['call_flow_context']]));
            $this->notifyCallFlowBlf($validated['call_flow_extension'], $validated['call_flow_status']);

            return response()->json([
                'messages' => ['success' => ['Call flow created successfully.']],
                'call_flow_uuid' => $callFlowUuid,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('CallFlowController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create call flow.']],
            ], 500);
        }
    }

    public function update(UpdateCallFlowRequest $request, string $call_flow): JsonResponse
    {
        $callFlow = CallFlows::query()
            ->whereKey($call_flow)
            ->firstOrFail();

        if (!userCheckPermission('call_flow_edit') || $callFlow->domain_uuid !== session('domain_uuid')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $validated = app(CallFlowService::class)->buildSaveData(
            $request->validated(),
            $callFlow,
            $this->currentDomainName()
        );

        try {
            DB::beginTransaction();

            $dialplanUuid = $callFlow->dialplan_uuid ?: (string) Str::uuid();
            $this->saveCallFlow($validated, $callFlow->call_flow_uuid, $dialplanUuid);

            DB::commit();

            $this->afterDialplanChange(collect([$validated['call_flow_context'], $callFlow->call_flow_context])->filter()->unique()->values());
            $this->notifyCallFlowBlf($validated['call_flow_extension'], $validated['call_flow_status']);

            return response()->json([
                'messages' => ['success' => ['Call flow updated successfully.']],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('CallFlowController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update call flow.']],
            ], 500);
        }
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && !userCheckPermission('call_flow_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (!$itemUuid && !userCheckPermission('call_flow_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($itemUuid) {
            $item = CallFlows::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($itemUuid)
                ->firstOrFail();

            $item->append([
                'call_flow_destination',
                'call_flow_target_uuid',
                'call_flow_action',
                'call_flow_action_display',
                'call_flow_target_name',
                'call_flow_target_extension',
                'call_flow_target',
                'call_flow_alternate_destination',
                'call_flow_alternate_target_uuid',
                'call_flow_alternate_action',
                'call_flow_alternate_action_display',
                'call_flow_alternate_target_name',
                'call_flow_alternate_target_extension',
                'call_flow_alternate_target',
            ]);
        } else {
            $extension = (new CallFlows())->generateUniqueSequenceNumber();

            $item = new CallFlows();
            $item->call_flow_uuid = null;
            $item->dialplan_uuid = null;
            $item->call_flow_extension = $extension;
            $item->call_flow_feature_code = $extension ? '*' . $extension : null;
            $item->call_flow_status = 'true';
            $item->call_flow_enabled = 'true';
        }

        return response()->json([
            'item' => $item,
            'routing_types' => app(CallRoutingOptionsService::class)->routingTypes,
            'sound_options' => getSoundsCollectionGrouped(session('domain_uuid')),
            'group_options' => $this->groupOptions(),
            'routes' => [
                'store_route' => route('call-flows.store'),
                'update_route' => $itemUuid ? route('call-flows.update', ['call_flow' => $item->call_flow_uuid]) : null,
                'group_store_route' => route('call-flows.groups.store'),
                'get_routing_options' => route('routing.options'),
            ],
        ]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        if (!userCheckPermission('call_flow_add') && !userCheckPermission('call_flow_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $name = trim($validated['name']);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => ['Enter a group name.'],
            ]);
        }

        $group = CallFlowGroup::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereRaw('lower(call_flow_group_name) = ?', [strtolower($name)])
            ->first();

        if (!$group) {
            $group = CallFlowGroup::create([
                'domain_uuid' => session('domain_uuid'),
                'call_flow_group_name' => $name,
            ]);
        }

        return response()->json([
            'group' => [
                'value' => $group->call_flow_group_name,
                'label' => $group->call_flow_group_name,
            ],
            'group_options' => $this->groupOptions(),
            'messages' => ['success' => ['Call flow group saved.']],
        ], $group->wasRecentlyCreated ? 201 : 200);
    }

    public function getData(Request $request)
    {
        if (!userCheckPermission('call_flow_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $currentDomain = session('domain_uuid');

        $items = QueryBuilder::for(CallFlows::class)
            ->select([
                'domain_uuid',
                'call_flow_uuid',
                'dialplan_uuid',
                'call_flow_name',
                'call_flow_extension',
                'call_flow_feature_code',
                'call_flow_status',
                'call_flow_label',
                'call_flow_alternate_label',
                'call_flow_context',
                'call_flow_enabled',
                'call_flow_group',
                'call_flow_description',
            ])
            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description');
            }])
            ->when(!userCheckPermission('call_flow_all') || !$request->boolean('filter.showGlobal'), function ($query) use ($currentDomain) {
                $query->where(function ($query) use ($currentDomain) {
                    $query->where('domain_uuid', $currentDomain)
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
                        $query->where('call_flow_name', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_extension', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_feature_code', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_context', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_group', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_pin_number', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_label', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_alternate_label', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_description', 'ilike', "%{$needle}%");
                    });
                }),
                AllowedFilter::callback('showGlobal', function ($query, $value) {}),
            ])
            ->allowedSorts([
                'call_flow_name',
                'call_flow_extension',
                'call_flow_feature_code',
                'call_flow_status',
                'call_flow_context',
                'call_flow_enabled',
                'call_flow_group',
                'call_flow_description',
            ])
            ->defaultSort('call_flow_name')
            ->paginate($this->perPage);

        return $items;
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (!userCheckPermission('call_flow_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $currentDomain = session('domain_uuid');

        $items = QueryBuilder::for(CallFlows::class)
            ->select(['call_flow_uuid'])
            ->when(!userCheckPermission('call_flow_all') || !$request->boolean('filter.showGlobal'), function ($query) use ($currentDomain) {
                $query->where(function ($query) use ($currentDomain) {
                    $query->where('domain_uuid', $currentDomain)
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
                        $query->where('call_flow_name', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_extension', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_feature_code', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_context', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_group', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_pin_number', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_label', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_alternate_label', 'ilike', "%{$needle}%")
                            ->orWhere('call_flow_description', 'ilike', "%{$needle}%");
                    });
                }),
                AllowedFilter::callback('showGlobal', function ($query, $value) {}),
            ])
            ->defaultSort('call_flow_name')
            ->pluck('call_flow_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching call flows selected.']],
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        if (!userCheckPermission('call_flow_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No call flows selected.']],
            ], 422);
        }

        try {
            DB::beginTransaction();

            $callFlows = CallFlows::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('call_flow_uuid', $uuids)
                ->get(['call_flow_uuid', 'dialplan_uuid', 'call_flow_context']);

            $dialplanUuids = $callFlows->pluck('dialplan_uuid')->filter()->values();
            $contexts = $callFlows->pluck('call_flow_context')->filter()->unique()->values();

            if ($dialplanUuids->isNotEmpty()) {
                DB::table('v_dialplan_details')
                    ->where('domain_uuid', session('domain_uuid'))
                    ->whereIn('dialplan_uuid', $dialplanUuids)
                    ->delete();

                DB::table('v_dialplans')
                    ->where('domain_uuid', session('domain_uuid'))
                    ->whereIn('dialplan_uuid', $dialplanUuids)
                    ->delete();
            }

            $deleted = DB::table('v_call_flows')
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('call_flow_uuid', $callFlows->pluck('call_flow_uuid'))
                ->delete();

            DB::commit();

            $this->afterDialplanChange($contexts);

            return response()->json([
                'messages' => ['success' => ["Deleted {$deleted} call flow(s)."]],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('CallFlowController@bulkDelete error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected call flows.']],
            ], 500);
        }
    }

    public function bulkCopy(Request $request): JsonResponse
    {
        if (!userCheckPermission('call_flow_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No call flows selected.']],
            ], 422);
        }

        try {
            DB::beginTransaction();

            $callFlows = CallFlows::query()
                ->where(function ($query) {
                    $query->where('domain_uuid', session('domain_uuid'))
                        ->orWhereNull('domain_uuid');
                })
                ->whereIn('call_flow_uuid', $uuids)
                ->get();

            $copied = 0;
            $contexts = collect();

            foreach ($callFlows as $callFlow) {
                $source = $callFlow->getAttributes();
                $newCallFlowUuid = (string) Str::uuid();
                $newDialplanUuid = (string) Str::uuid();

                $source['call_flow_uuid'] = $newCallFlowUuid;
                $source['dialplan_uuid'] = $newDialplanUuid;
                $source['call_flow_description'] = trim(($source['call_flow_description'] ?? '') . ' (copy)');

                $newCallFlow = new CallFlows();
                $newCallFlow->forceFill($source)->save();

                $this->applyGroupExclusivity(
                    $newCallFlowUuid,
                    $source['call_flow_group'] ?? null,
                    $source['call_flow_status'] ?? 'false'
                );

                $contexts->push($callFlow->call_flow_context);
                $copied++;
            }

            DB::commit();

            $this->afterDialplanChange($contexts->filter()->unique()->values());

            return response()->json([
                'messages' => ['success' => ["Copied {$copied} call flow(s)."]],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('CallFlowController@bulkCopy error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while copying the selected call flows.']],
            ], 500);
        }
    }

    public function bulkToggle(Request $request): JsonResponse
    {
        if (!userCheckPermission('call_flow_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $request->validate([
            'field' => 'required|in:call_flow_status,call_flow_enabled',
        ]);

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No call flows selected.']],
            ], 422);
        }

        $field = $request->input('field');

        try {
            DB::beginTransaction();

            $callFlows = CallFlows::query()
                ->where(function ($query) {
                    $query->where('domain_uuid', session('domain_uuid'))
                        ->orWhereNull('domain_uuid');
                })
                ->whereIn('call_flow_uuid', $uuids)
                ->get(['call_flow_uuid', 'dialplan_uuid', 'call_flow_extension', 'call_flow_context', 'call_flow_group', $field]);

            foreach ($callFlows as $callFlow) {
                $newState = $callFlow->{$field} === 'true' ? 'false' : 'true';

                DB::table('v_call_flows')
                    ->where('call_flow_uuid', $callFlow->call_flow_uuid)
                    ->update([$field => $newState]);

                if ($field === 'call_flow_enabled' && $callFlow->dialplan_uuid) {
                    DB::table('v_dialplans')
                        ->where('dialplan_uuid', $callFlow->dialplan_uuid)
                        ->update(['dialplan_enabled' => $newState]);
                } else {
                    $this->applyGroupExclusivity($callFlow->call_flow_uuid, $callFlow->call_flow_group, $newState);
                    $this->notifyCallFlowBlf($callFlow->call_flow_extension, $newState);
                }
            }

            DB::commit();

            $this->afterDialplanChange($callFlows->pluck('call_flow_context')->filter()->unique()->values());

            return response()->json([
                'messages' => ['success' => ['Call flow setting toggled.']],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('CallFlowController@bulkToggle error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while toggling the selected call flows.']],
            ], 500);
        }
    }

    private function saveCallFlow(array $data, string $callFlowUuid, string $dialplanUuid): void
    {
        $callFlow = CallFlows::query()->firstOrNew(['call_flow_uuid' => $callFlowUuid]);
        $callFlow->forceFill([
            'domain_uuid' => session('domain_uuid'),
            'dialplan_uuid' => $dialplanUuid,
            'call_flow_name' => $data['call_flow_name'],
            'call_flow_extension' => $data['call_flow_extension'],
            'call_flow_feature_code' => $data['call_flow_feature_code'],
            'call_flow_status' => $data['call_flow_status'],
            'call_flow_pin_number' => $data['call_flow_pin_number'],
            'call_flow_label' => $data['call_flow_label'],
            'call_flow_sound' => $data['call_flow_sound'],
            'call_flow_app' => $data['call_flow_app'],
            'call_flow_data' => $data['call_flow_data'],
            'call_flow_alternate_label' => $data['call_flow_alternate_label'],
            'call_flow_alternate_sound' => $data['call_flow_alternate_sound'],
            'call_flow_alternate_app' => $data['call_flow_alternate_app'],
            'call_flow_alternate_data' => $data['call_flow_alternate_data'],
            'call_flow_context' => $data['call_flow_context'],
            'call_flow_enabled' => $data['call_flow_enabled'],
            'call_flow_group' => $data['call_flow_group'],
            'call_flow_description' => $data['call_flow_description'],
        ])->save();

        $this->applyGroupExclusivity($callFlowUuid, $data['call_flow_group'], $data['call_flow_status']);
    }

    private function validatedUuids(Request $request): array
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*' => 'required|uuid',
        ]);

        return array_values(array_unique($validated['items']));
    }

    private function groupOptions(): array
    {
        $persistedGroups = CallFlowGroup::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('call_flow_group_name')
            ->pluck('call_flow_group_name');

        $assignedGroups = CallFlows::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereNotNull('call_flow_group')
            ->where('call_flow_group', '<>', '')
            ->distinct()
            ->orderBy('call_flow_group')
            ->pluck('call_flow_group');

        return $persistedGroups
            ->merge($assignedGroups)
            ->filter()
            ->unique()
            ->sort(fn ($a, $b) => strcasecmp((string) $a, (string) $b))
            ->values()
            ->all();
    }

    private function currentDomainName(): string
    {
        if (filled(session('domain_name'))) {
            return session('domain_name');
        }

        return DB::table('v_domains')
            ->where('domain_uuid', session('domain_uuid'))
            ->value('domain_name') ?: '';
    }

    private function applyGroupExclusivity(string $callFlowUuid, ?string $group, string $status): void
    {
        if ($status !== 'false' || !filled($group)) {
            return;
        }

        $siblings = CallFlows::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('call_flow_group', $group)
            ->where('call_flow_uuid', '!=', $callFlowUuid)
            ->where('call_flow_status', 'false')
            ->get(['call_flow_uuid', 'call_flow_extension']);

        if ($siblings->isEmpty()) {
            return;
        }

        DB::table('v_call_flows')
            ->whereIn('call_flow_uuid', $siblings->pluck('call_flow_uuid'))
            ->update(['call_flow_status' => 'true']);

        foreach ($siblings as $sibling) {
            $this->notifyCallFlowBlf($sibling->call_flow_extension, 'true');
        }
    }

    private function notifyCallFlowBlf(?string $extension, string $status): void
    {
        $domainName = $this->currentDomainName();

        if (!filled($extension) || !filled($domainName)) {
            return;
        }

        $command = sprintf(
            'bgapi luarun lua/flow_notify.lua %s %s %s',
            escapeshellarg((string) $extension),
            escapeshellarg((string) $domainName),
            escapeshellarg($status)
        );

        $freeSwitchService = new FreeswitchEslService();
        $freeSwitchService->executeCommand($command);
    }

    private function afterDialplanChange($contexts): void
    {
        session(['reload_xml' => true]);
        session()->forget('destinations.array');

        foreach ($contexts as $context) {
            cache()->forget('dialplan:' . $context);
        }
    }

}
