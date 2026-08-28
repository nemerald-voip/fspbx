<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDynamicRouteRequest;
use App\Http\Requests\UpdateDynamicRouteRequest;
use App\Models\DynamicRoute;
use App\Services\CallRoutingOptionsService;
use App\Services\DynamicRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DynamicRouteController extends Controller
{
    public function index()
    {
        if (! userCheckPermission('dynamic_route_view')) {
            return redirect('/');
        }

        return Inertia::render('DynamicRoutes', [
            'pagination' => [
                'per_page' => fspbx_pagination_per_page(),
                'per_page_options' => fspbx_pagination_options(),
            ],
            'routes' => [
                'data_route' => route('dynamic-routes.data'),
                'select_all' => route('dynamic-routes.select.all'),
                'bulk_delete' => route('dynamic-routes.bulk.delete'),
                'bulk_toggle' => route('dynamic-routes.bulk.toggle'),
                'item_options' => route('dynamic-routes.item.options'),
            ],
            'permissions' => [
                'create' => userCheckPermission('dynamic_route_create'),
                'update' => userCheckPermission('dynamic_route_update'),
                'destroy' => userCheckPermission('dynamic_route_delete'),
            ],
        ]);
    }

    public function getData(Request $request)
    {
        if (! userCheckPermission('dynamic_route_view')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        return $this->scoped($request)
            ->select([
                'dynamic_route_uuid',
                'name',
                'extension',
                'source',
                'enabled',
                'description',
                'updated_at',
            ])
            ->withCount('rules')
            ->allowedSorts(['name', 'extension', 'enabled', 'updated_at'])
            ->defaultSort('name')
            ->paginate(fspbx_pagination_per_page($request));
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('dynamic_route_update')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        if (! $itemUuid && ! userCheckPermission('dynamic_route_create')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        if ($itemUuid) {
            $item = DynamicRoute::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->with('rules')
                ->whereKey($itemUuid)
                ->firstOrFail();
            $item->append('default_destination_target');
        } else {
            $item = new DynamicRoute([
                'extension' => (new DynamicRoute())->generateUniqueSequenceNumber(),
                'source' => DynamicRoute::SOURCE_CALLER_DESTINATION,
                'enabled' => true,
                'default_destination_type' => 'hangup',
            ]);
            $item->setRelation('rules', collect());
            $item->append('default_destination_target');
        }

        $routingTypes = collect(app(CallRoutingOptionsService::class)->routingTypes)
            ->whereIn('value', DynamicRouteService::DESTINATION_TYPES)
            ->values();

        return response()->json([
            'item' => $item,
            'routing_types' => $routingTypes,
            'source_options' => [
                [
                    'value' => DynamicRoute::SOURCE_CALLER_DESTINATION,
                    'label' => __('Original DID'),
                ],
            ],
            'routes' => [
                'store_route' => route('dynamic-routes.store'),
                'update_route' => $itemUuid
                    ? route('dynamic-routes.update', ['dynamic_route' => $item->dynamic_route_uuid])
                    : null,
                'get_routing_options' => route('routing.options'),
            ],
        ]);
    }

    public function store(StoreDynamicRouteRequest $request, DynamicRouteService $service): JsonResponse
    {
        $dynamicRoute = $service->save($request->validated());

        return response()->json([
            'messages' => ['success' => [__('Dynamic route created successfully.')]],
            'dynamic_route_uuid' => $dynamicRoute->dynamic_route_uuid,
        ], 201);
    }

    public function update(
        UpdateDynamicRouteRequest $request,
        DynamicRoute $dynamicRoute,
        DynamicRouteService $service
    ): JsonResponse {
        if ($dynamicRoute->domain_uuid !== session('domain_uuid')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $service->save($request->validated(), $dynamicRoute);

        return response()->json([
            'messages' => ['success' => [__('Dynamic route updated successfully.')]],
        ]);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('dynamic_route_view')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        return response()->json([
            'items' => $this->scoped($request)->pluck('dynamic_route_uuid'),
        ]);
    }

    public function bulkToggle(Request $request, DynamicRouteService $service): JsonResponse
    {
        if (! userCheckPermission('dynamic_route_update')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $items = $this->selectedItems($request);
        $service->toggle($items);

        return response()->json([
            'messages' => ['success' => [__('Dynamic route status updated.')]],
        ]);
    }

    public function bulkDelete(Request $request, DynamicRouteService $service): JsonResponse
    {
        if (! userCheckPermission('dynamic_route_delete')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $deleted = $service->delete($this->selectedItems($request));

        return response()->json([
            'messages' => ['success' => [trans_choice(':count dynamic route deleted.|:count dynamic routes deleted.', $deleted, ['count' => $deleted])]],
        ]);
    }

    private function selectedItems(Request $request)
    {
        $uuids = array_values(array_unique($request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['required', 'uuid'],
        ])['items']));

        return DynamicRoute::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('dynamic_route_uuid', $uuids)
            ->get();
    }

    private function scoped(Request $request): QueryBuilder
    {
        return QueryBuilder::for(DynamicRoute::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);
                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('name', 'ilike', "%{$needle}%")
                            ->orWhere('extension', 'ilike', "%{$needle}%")
                            ->orWhere('description', 'ilike', "%{$needle}%");
                    });
                }),
            ]);
    }
}
