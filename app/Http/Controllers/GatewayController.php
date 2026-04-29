<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGatewayRequest;
use App\Http\Requests\UpdateGatewayRequest;
use App\Models\Domain;
use App\Models\Gateways;
use App\Models\SipProfiles;
use App\Services\AccessControlService;
use App\Services\FreeswitchEslService;
use App\Services\GatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class GatewayController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (!userCheckPermission('gateway_view')) {
            return redirect('/');
        }

        return Inertia::render('Gateways', [
            'routes' => [
                'current_page' => route('gateways.index'),
                'data_route' => route('gateways.data'),
                'select_all' => route('gateways.select.all'),
                'bulk_delete' => route('gateways.bulk.delete'),
                'bulk_copy' => route('gateways.bulk.copy'),
                'bulk_toggle' => route('gateways.bulk.toggle'),
                'bulk_start' => route('gateways.bulk.start'),
                'bulk_stop' => route('gateways.bulk.stop'),
                'store' => route('gateways.store'),
                'item_options' => route('gateways.item.options'),
            ],
            'permissions' => [
                'create' => userCheckPermission('gateway_add'),
                'update' => userCheckPermission('gateway_edit'),
                'destroy' => userCheckPermission('gateway_delete'),
                'view_global' => userCheckPermission('gateway_all'),
                'domain' => userCheckPermission('gateway_domain'),
                'channels' => userCheckPermission('gateway_channels'),
            ],
        ]);
    }

    public function store(StoreGatewayRequest $request, GatewayService $service, AccessControlService $accessControlService): JsonResponse
    {
        $validated = $request->validated();
        $data = $service->saveData($validated);

        try {
            DB::beginTransaction();

            $gateway = new Gateways();
            $gateway->forceFill($data)->save();
            $accessControlService->syncGatewayProviderIps($gateway, $validated['gateway_acl_cidrs'] ?? null);

            DB::commit();

            $accessControlService->sync();
            $service->sync(collect([$gateway->profile]));
            $startResponse = $gateway->enabled === 'true'
                ? $service->executeGatewayCommand('start', $gateway)
                : 'Skipped: gateway is disabled.';

            return response()->json([
                'messages' => ['success' => array_filter([
                    'Gateway created successfully.',
                    $startResponse ? "FreeSWITCH: {$startResponse}" : null,
                ])],
                'gateway_uuid' => $gateway->gateway_uuid,
                'start_response' => $startResponse,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('GatewayController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create gateway.']],
            ], 500);
        }
    }

    public function update(UpdateGatewayRequest $request, Gateways $gateway, GatewayService $service, AccessControlService $accessControlService): JsonResponse
    {
        if (!$this->canModifyGateway($gateway)) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $oldProfile = $gateway->profile;
        $validated = $request->validated();
        $data = $service->saveData($validated, $gateway);

        try {
            DB::beginTransaction();

            $gateway->forceFill($data)->save();
            $accessControlService->syncGatewayProviderIps($gateway, $validated['gateway_acl_cidrs'] ?? null);

            DB::commit();

            $accessControlService->sync();
            $service->sync(collect([$oldProfile, $gateway->profile]));

            return response()->json([
                'messages' => ['success' => ['Gateway updated successfully.']],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('GatewayController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update gateway.']],
            ], 500);
        }
    }

    public function getData(Request $request)
    {
        if (!userCheckPermission('gateway_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $currentDomain = session('domain_uuid');

        $items = QueryBuilder::for(Gateways::class)
            ->select([
                'gateway_uuid',
                'domain_uuid',
                'gateway',
                'username',
                'auth_username',
                'from_user',
                'from_domain',
                'proxy',
                'register_proxy',
                'outbound_proxy',
                'register',
                'context',
                'profile',
                'hostname',
                'enabled',
                'description',
            ])
            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description');
            }])
            ->when(!userCheckPermission('gateway_all') || !$request->boolean('filter.showGlobal'), function ($query) use ($currentDomain) {
                $query->where(function ($query) use ($currentDomain) {
                    $query->where('domain_uuid', $currentDomain);

                    if (userCheckPermission('gateway_domain')) {
                        $query->orWhereNull('domain_uuid');
                    }
                });
            })
            ->allowedFilters($this->allowedFilters())
            ->allowedSorts([
                'gateway',
                'proxy',
                'context',
                'register',
                'profile',
                'hostname',
                'enabled',
                'description',
            ])
            ->defaultSort('gateway')
            ->paginate($this->perPage);

        $this->appendSwitchStatus($items->getCollection());

        return $items;
    }

    public function getItemOptions(Request $request, AccessControlService $accessControlService): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && !userCheckPermission('gateway_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (!$itemUuid && !userCheckPermission('gateway_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($itemUuid) {
            $item = Gateways::query()->whereKey($itemUuid)->firstOrFail();

            if (!$this->canViewGateway($item)) {
                return response()->json([
                    'messages' => ['error' => ['Access denied.']],
                ], 403);
            }

            // Convert null to empty string so frontend selects "Global"
            if (is_null($item->domain_uuid)) {
                $item->domain_uuid = '';
            }

            $item->gateway_acl_cidrs = $accessControlService->gatewayCidrs($item)
                ->map(fn ($cidr) => ['node_cidr' => $cidr])
                ->values()
                ->all();
        } else {
            $item = new Gateways([
                'gateway_uuid' => null,
                'domain_uuid' => '', 
                'expire_seconds' => '800',
                'register' => 'false',
                'retry_seconds' => '30',
                'channels' => 0,
                'context' => 'public',
                'profile' => $this->defaultSipProfile(),
                'enabled' => 'true',
                'caller_id_in_from' => 'true',
                'gateway_acl_cidrs' => [],
            ]);
        }

        return response()->json([
            'item' => $item,
            'profile_options' => $this->profileOptions(),
            'domain_options' => $this->domainOptions(),
            'routes' => [
                'store_route' => route('gateways.store'),
                'update_route' => $itemUuid ? route('gateways.update', ['gateway' => $item->gateway_uuid]) : null,
            ],
        ]);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (!userCheckPermission('gateway_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $currentDomain = session('domain_uuid');

        $items = QueryBuilder::for(Gateways::class)
            ->select(['gateway_uuid'])
            ->when(!userCheckPermission('gateway_all') || !$request->boolean('filter.showGlobal'), function ($query) use ($currentDomain) {
                $query->where(function ($query) use ($currentDomain) {
                    $query->where('domain_uuid', $currentDomain);

                    if (userCheckPermission('gateway_domain')) {
                        $query->orWhereNull('domain_uuid');
                    }
                });
            })
            ->allowedFilters($this->allowedFilters())
            ->defaultSort('gateway')
            ->pluck('gateway_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching gateways selected.']],
        ]);
    }

    public function bulkDelete(Request $request, GatewayService $service, AccessControlService $accessControlService): JsonResponse
    {
        if (!userCheckPermission('gateway_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $gateways = $this->selectedGateways($request, true);

        if ($gateways->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No gateways selected.']],
            ], 422);
        }

        try {
            DB::beginTransaction();

            $profiles = $gateways->pluck('profile');

            $gateways->each(function (Gateways $gateway) use ($service) {
                $service->executeGatewayCommand('stop', $gateway);
                $gateway->delete();
            });

            $gateways->each(fn (Gateways $gateway) => $accessControlService->removeGatewayProviderIps($gateway));

            DB::commit();

            $accessControlService->sync();
            $service->sync($profiles);

            return response()->json([
                'messages' => ['success' => ["Deleted {$gateways->count()} gateway(s)."]],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('GatewayController@bulkDelete error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected gateways.']],
            ], 500);
        }
    }

    public function bulkCopy(Request $request, GatewayService $service, AccessControlService $accessControlService): JsonResponse
    {
        if (!userCheckPermission('gateway_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $gateways = $this->selectedGateways($request, true);

        if ($gateways->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No gateways selected.']],
            ], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($gateways as $gateway) {
                $copy = $gateway->replicate();
                $copy->gateway_uuid = (string) Str::uuid();
                $copy->description = trim(($gateway->description ?? '') . ' (copy)');
                $copy->channels = $gateway->channels ?: 0;
                $copy->expire_seconds = $gateway->expire_seconds ?: '800';
                $copy->retry_seconds = $gateway->retry_seconds ?: '30';
                $copy->save();
                $accessControlService->syncGatewayProviderIps($copy, $accessControlService->gatewayCidrs($gateway)->all());
            }

            DB::commit();

            $accessControlService->sync();
            $service->sync($gateways->pluck('profile'));

            return response()->json([
                'messages' => ['success' => ["Copied {$gateways->count()} gateway(s)."]],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('GatewayController@bulkCopy error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while copying the selected gateways.']],
            ], 500);
        }
    }

    public function bulkToggle(Request $request, GatewayService $service): JsonResponse
    {
        if (!userCheckPermission('gateway_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $gateways = $this->selectedGateways($request, true);

        if ($gateways->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No gateways selected.']],
            ], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($gateways as $gateway) {
                $gateway->enabled = $gateway->enabled === 'true' ? 'false' : 'true';
                $gateway->save();
            }

            DB::commit();

            $service->sync($gateways->pluck('profile'));

            return response()->json([
                'messages' => ['success' => ['Gateway enabled state toggled.']],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('GatewayController@bulkToggle error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while toggling the selected gateways.']],
            ], 500);
        }
    }

    public function bulkStart(Request $request, GatewayService $service): JsonResponse
    {
        return $this->bulkGatewayCommand($request, $service, 'start', 'Gateway(s) started.');
    }

    public function bulkStop(Request $request, GatewayService $service): JsonResponse
    {
        return $this->bulkGatewayCommand($request, $service, 'stop', 'Gateway(s) stopped.');
    }

    private function bulkGatewayCommand(Request $request, GatewayService $service, string $action, string $message): JsonResponse
    {
        if (!userCheckPermission('gateway_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $gateways = $this->selectedGateways($request, true);

        if ($gateways->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No gateways selected.']],
            ], 422);
        }

        if ($action === 'start') {
            $service->sync($gateways->pluck('profile'));
        }

        $responses = $gateways
            ->mapWithKeys(fn(Gateways $gateway) => [
                $gateway->gateway => $service->executeGatewayCommand($action, $gateway) ?: 'No response from FreeSWITCH.',
            ]);

        $responseMessages = $responses
            ->map(fn($response, $gateway) => "{$gateway}: {$response}")
            ->values()
            ->all();

        $errors = $responses->filter(fn($response) => str_starts_with(trim((string) $response), '-ERR'));

        if ($errors->isNotEmpty()) {
            return response()->json([
                'messages' => ['error' => array_merge(["Unable to {$action} selected gateway(s)."], $responseMessages)],
                'responses' => $responses,
            ], 422);
        }

        return response()->json([
            'messages' => ['success' => array_merge([$message], $responseMessages)],
            'responses' => $responses,
        ]);
    }

    private function selectedGateways(Request $request, bool $allowGlobal = false)
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['required', 'uuid'],
        ]);

        return Gateways::query()
            ->whereIn('gateway_uuid', array_values(array_unique($validated['items'])))
            ->when(!$allowGlobal || !userCheckPermission('gateway_all'), function ($query) {
                $query->where(function ($query) {
                    $query->where('domain_uuid', session('domain_uuid'));

                    if (userCheckPermission('gateway_domain')) {
                        $query->orWhereNull('domain_uuid');
                    }
                });
            })
            ->get();
    }

    private function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('search', function ($query, $value) {
                $needle = trim((string) $value);

                if ($needle === '') {
                    return;
                }

                $query->where(function ($query) use ($needle) {
                    $query->where('gateway', 'ilike', "%{$needle}%")
                        ->orWhere('username', 'ilike', "%{$needle}%")
                        ->orWhere('auth_username', 'ilike', "%{$needle}%")
                        ->orWhere('from_user', 'ilike', "%{$needle}%")
                        ->orWhere('from_domain', 'ilike', "%{$needle}%")
                        ->orWhere('proxy', 'ilike', "%{$needle}%")
                        ->orWhere('register_proxy', 'ilike', "%{$needle}%")
                        ->orWhere('outbound_proxy', 'ilike', "%{$needle}%")
                        ->orWhere('description', 'ilike', "%{$needle}%");
                });
            }),
            AllowedFilter::callback('showGlobal', function ($query, $value) {}),
        ];
    }

    private function canViewGateway(Gateways $gateway): bool
    {
        if (userCheckPermission('gateway_all')) {
            return true;
        }

        if (userCheckPermission('gateway_domain') && blank($gateway->domain_uuid)) {
            return true;
        }

        return $gateway->domain_uuid === session('domain_uuid');
    }

    private function canModifyGateway(Gateways $gateway): bool
    {
        if (userCheckPermission('gateway_all')) {
            return true;
        }

        return $gateway->domain_uuid === session('domain_uuid')
            || (userCheckPermission('gateway_domain') && blank($gateway->domain_uuid));
    }

    private function profileOptions(): array
    {
        return SipProfiles::query()
            ->where('sip_profile_enabled', 'true')
            ->orderBy('sip_profile_name')
            ->pluck('sip_profile_name')
            ->map(fn($profile) => ['value' => $profile, 'label' => $profile])
            ->values()
            ->all();
    }

    private function defaultSipProfile(): ?string
    {
        return SipProfiles::query()
            ->where('sip_profile_enabled', 'true')
            ->where('sip_profile_name', 'external')
            ->value('sip_profile_name')
            ?: SipProfiles::query()
            ->where('sip_profile_enabled', 'true')
            ->orderBy('sip_profile_name')
            ->value('sip_profile_name');
    }

private function domainOptions(): array
    {
        if (!userCheckPermission('gateway_domain')) {
            return [];
        }

        return collect([['value' => '', 'label' => 'Global']])
            ->merge(
                Domain::query()
                    ->orderBy('domain_name')
                    ->get(['domain_uuid', 'domain_name'])
                    ->map(fn (Domain $domain) => [
                        'value' => $domain->domain_uuid,
                        'label' => $domain->domain_name,
                    ])
            )
            ->values()
            ->all();
    }

    private function appendSwitchStatus($gateways): void
    {
        $statuses = $this->gatewayStatuses();

        $gateways->each(function (Gateways $gateway) use ($statuses) {
            if ($gateway->enabled !== 'true') {
                $gateway->setAttribute('switch_status', null);
                $gateway->setAttribute('switch_state', null);
                return;
            }

            $status = $statuses->get(strtolower((string) $gateway->gateway_uuid));
            $switchStatus = strtolower($status['status'] ?? '') === 'up' ? 'running' : 'stopped';

            $gateway->setAttribute('switch_status', $status ? $switchStatus : 'stopped');
            $gateway->setAttribute('switch_state', $status['state'] ?? null);
        });
    }

    private function gatewayStatuses()
    {
        $service = new FreeswitchEslService();

        if (!$service->isConnected()) {
            return collect();
        }

        $response = $service->executeCommand('sofia xmlstatus gateway', false);
        $service->disconnect();

        if (!$response || !isset($response->gateway)) {
            return collect();
        }

        $statuses = collect();

        foreach ($response->gateway as $row) {
            $uuid = strtolower((string) $row->name);

            if (blank($uuid)) {
                continue;
            }

            $statuses->put($uuid, [
                'state' => (string) $row->state,
                'status' => (string) $row->status,
            ]);
        }

        return $statuses;
    }
}
