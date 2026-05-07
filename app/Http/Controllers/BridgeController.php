<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBridgeRequest;
use App\Http\Requests\UpdateBridgeRequest;
use App\Models\Bridge;
use App\Models\Gateways;
use App\Services\BridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BridgeController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (! userCheckPermission('bridge_view')) {
            return redirect('/');
        }

        return Inertia::render('Bridges', [
            'routes' => [
                'current_page' => route('bridges.index'),
                'data_route' => route('bridges.data'),
                'select_all' => route('bridges.select.all'),
                'bulk_copy' => route('bridges.bulk.copy'),
                'bulk_delete' => route('bridges.bulk.delete'),
                'bulk_toggle' => route('bridges.bulk.toggle'),
                'store' => route('bridges.store'),
                'item_options' => route('bridges.item.options'),
            ],
            'permissions' => $this->permissions(),
        ]);
    }

    public function store(StoreBridgeRequest $request, BridgeService $service): JsonResponse
    {
        try {
            $bridge = $service->save($request->validated());

            return response()->json([
                'messages' => ['success' => ['Bridge created successfully.']],
                'bridge_uuid' => $bridge->bridge_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('BridgeController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create bridge.']],
            ], 500);
        }
    }

    public function update(UpdateBridgeRequest $request, Bridge $bridge, BridgeService $service): JsonResponse
    {
        if ($bridge->domain_uuid !== session('domain_uuid')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $service->save($request->validated(), $bridge);

            return response()->json([
                'messages' => ['success' => ['Bridge updated successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('BridgeController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update bridge.']],
            ], 500);
        }
    }

    public function getItemOptions(Request $request, BridgeService $service): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('bridge_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (! $itemUuid && ! userCheckPermission('bridge_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($itemUuid) {
            $item = QueryBuilder::for(Bridge::class)
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($itemUuid)
                ->firstOrFail();
        } else {
            $item = new Bridge();
            $item->bridge_enabled = 'true';
        }

        $form = $service->parseDestination($item->bridge_destination);
        $gatewayOptions = $this->gatewayOptions();
        $form = $this->hydrateGatewaySelections($form, $gatewayOptions);

        return response()->json([
            'item' => $item,
            'form' => $form,
            'actions' => $this->bridgeActions(),
            'variables' => $this->bridgeVariables($form['bridge_variables'] ?? []),
            'gateways' => $gatewayOptions,
            'profiles' => $this->sipProfiles(),
            'routes' => [
                'store_route' => route('bridges.store'),
                'update_route' => $itemUuid ? route('bridges.update', ['bridge' => $item->bridge_uuid]) : null,
            ],
        ]);
    }

    public function getData(Request $request)
    {
        if (! userCheckPermission('bridge_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        return $this->scopedBridges($request)
            ->select([
                'domain_uuid',
                'bridge_uuid',
                'bridge_name',
                'bridge_destination',
                'bridge_enabled',
                'bridge_description',
            ])
            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description');
            }])
            ->allowedSorts([
                'bridge_name',
                'bridge_destination',
                'bridge_enabled',
                'bridge_description',
            ])
            ->defaultSort('bridge_name')
            ->paginate($this->perPage);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('bridge_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->scopedBridges($request)
            ->select(['bridge_uuid'])
            ->defaultSort('bridge_name')
            ->pluck('bridge_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching bridges selected.']],
        ]);
    }

    public function bulkCopy(Request $request, BridgeService $service): JsonResponse
    {
        if (! userCheckPermission('bridge_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->itemsFromRequest($request);
        if ($items->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No bridges selected.']],
            ], 422);
        }

        $copied = $service->copy($items);

        return response()->json([
            'messages' => ['success' => ["Copied {$copied} bridge(s)."]],
        ]);
    }

    public function bulkDelete(Request $request, BridgeService $service): JsonResponse
    {
        if (! userCheckPermission('bridge_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->itemsFromRequest($request);
        if ($items->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No bridges selected.']],
            ], 422);
        }

        $deleted = $service->delete($items);

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} bridge(s)."]],
        ]);
    }

    public function bulkToggle(Request $request, BridgeService $service): JsonResponse
    {
        if (! userCheckPermission('bridge_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->itemsFromRequest($request);
        if ($items->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No bridges selected.']],
            ], 422);
        }

        $service->toggle($items);

        return response()->json([
            'messages' => ['success' => ['Bridge status toggled.']],
        ]);
    }

    private function scopedBridges(Request $request): QueryBuilder
    {
        return QueryBuilder::for(Bridge::class)
            ->when(! userCheckPermission('bridge_all') || ! $request->boolean('filter.showGlobal'), function ($query) {
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
                        $query->where('bridge_name', 'ilike', "%{$needle}%")
                            ->orWhere('bridge_destination', 'ilike', "%{$needle}%")
                            ->orWhere('bridge_enabled', 'ilike', "%{$needle}%")
                            ->orWhere('bridge_description', 'ilike', "%{$needle}%");
                    });
                }),
                AllowedFilter::callback('showGlobal', function ($query, $value) {}),
            ]);
    }

    private function itemsFromRequest(Request $request): Collection
    {
        $uuids = collect($request->input('items', []))
            ->filter(fn ($uuid) => is_string($uuid) && preg_match('/^[0-9a-fA-F-]{36}$/', $uuid))
            ->values()
            ->all();

        if (empty($uuids)) {
            return collect();
        }

        return QueryBuilder::for(Bridge::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('bridge_uuid', $uuids)
            ->get();
    }

    private function bridgeActions(): array
    {
        return collect(session('bridge.action', ['user', 'gateway', 'profile', 'loopback']))
            ->filter()
            ->map(fn ($action) => [
                'value' => $action,
                'label' => ucfirst($action),
            ])
            ->values()
            ->all();
    }

    private function bridgeVariables(array $currentValues): array
    {
        return collect(session('bridge.variable', []))
            ->map(function ($variable) use ($currentValues) {
                [$name, $default] = array_pad(explode('=', (string) $variable, 2), 2, '');

                return [
                    'name' => $name,
                    'label' => ucwords(str_replace('_', ' ', $name)),
                    'value' => $currentValues[$name] ?? $default,
                ];
            })
            ->filter(fn ($variable) => $variable['name'] !== '')
            ->values()
            ->all();
    }

    private function gatewayOptions(): array
    {
        return Gateways::query()
            ->with(['domain:domain_uuid,domain_name,domain_description'])
            ->where('enabled', 'true')
            ->when(! userCheckPermission('outbound_route_any_gateway'), function ($query) {
                $query->where('domain_uuid', session('domain_uuid'));
            })
            ->orderByRaw('domain_uuid = ? desc', [session('domain_uuid')])
            ->orderBy('gateway')
            ->get(['gateway_uuid', 'domain_uuid', 'gateway'])
            ->groupBy(fn (Gateways $gateway) => $gateway->domain?->domain_description ?: $gateway->domain?->domain_name ?: 'Global')
            ->map(fn ($items, $label) => [
                'label' => $label,
                'items' => $items->map(fn (Gateways $gateway) => [
                    'value' => $gateway->gateway_uuid . ':' . $gateway->gateway,
                    'label' => $gateway->gateway,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function hydrateGatewaySelections(array $form, array $gatewayOptions): array
    {
        $values = collect($gatewayOptions)
            ->flatMap(fn ($group) => $group['items'])
            ->keyBy(fn ($item) => explode(':', $item['value'], 2)[0]);

        foreach ([0, 1, 2] as $index) {
            $gatewayUuid = $form['bridge_gateways'][$index] ?? null;
            $form['bridge_gateway_' . ($index + 1)] = $gatewayUuid && isset($values[$gatewayUuid])
                ? $values[$gatewayUuid]['value']
                : null;
        }

        return $form;
    }

    private function sipProfiles(): array
    {
        return DB::table('v_sip_profiles')
            ->where('sip_profile_enabled', 'true')
            ->orderBy('sip_profile_name')
            ->pluck('sip_profile_name')
            ->map(fn ($profile) => ['value' => $profile, 'label' => $profile])
            ->all();
    }

    private function permissions(): array
    {
        return [
            'create' => userCheckPermission('bridge_add'),
            'update' => userCheckPermission('bridge_edit'),
            'destroy' => userCheckPermission('bridge_delete'),
            'copy' => userCheckPermission('bridge_add'),
            'view_global' => userCheckPermission('bridge_all'),
        ];
    }
}
