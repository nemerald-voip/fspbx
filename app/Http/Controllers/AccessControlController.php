<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccessControlRequest;
use App\Http\Requests\UpdateAccessControlRequest;
use App\Models\AccessControl;
use App\Services\AccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AccessControlController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (!userCheckPermission('access_control_view')) {
            return redirect('/');
        }

        return Inertia::render('AccessControls', [
            'routes' => [
                'current_page' => route('access-controls.index'),
                'data_route' => route('access-controls.data'),
                'select_all' => route('access-controls.select.all'),
                'bulk_delete' => route('access-controls.bulk.delete'),
                'bulk_copy' => route('access-controls.bulk.copy'),
                'reload' => route('access-controls.reload'),
                'store' => route('access-controls.store'),
                'item_options' => route('access-controls.item.options'),
            ],
            'permissions' => [
                'create' => userCheckPermission('access_control_add'),
                'update' => userCheckPermission('access_control_edit'),
                'destroy' => userCheckPermission('access_control_delete'),
            ],
        ]);
    }

    public function store(StoreAccessControlRequest $request, AccessControlService $service): JsonResponse
    {
        try {
            DB::beginTransaction();

            $accessControl = $service->saveAccessControl(new AccessControl(), $request->validated());

            DB::commit();

            $reloadResponse = $service->sync();

            return response()->json([
                'messages' => ['success' => array_filter([
                    'Access control list created successfully.',
                    $reloadResponse ? "FreeSWITCH: {$reloadResponse}" : null,
                ])],
                'access_control_uuid' => $accessControl->access_control_uuid,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('AccessControlController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create access control list.']],
            ], 500);
        }
    }

    public function update(UpdateAccessControlRequest $request, AccessControl $accessControl, AccessControlService $service): JsonResponse
    {
        try {
            DB::beginTransaction();

            $service->saveAccessControl($accessControl, $request->validated());

            DB::commit();

            $reloadResponse = $service->sync();

            return response()->json([
                'messages' => ['success' => array_filter([
                    'Access control list updated successfully.',
                    $reloadResponse ? "FreeSWITCH: {$reloadResponse}" : null,
                ])],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('AccessControlController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update access control list.']],
            ], 500);
        }
    }

    public function getData(Request $request)
    {
        if (!userCheckPermission('access_control_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        return QueryBuilder::for(AccessControl::class)
            ->select([
                'access_control_uuid',
                'access_control_name',
                'access_control_default',
                'access_control_description',
            ])
            ->withCount('nodes')
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = strtolower((string) $value);

                    $query->where(function ($query) use ($needle) {
                        $query->whereRaw('lower(access_control_name) like ?', ["%{$needle}%"])
                            ->orWhereRaw('lower(access_control_default) like ?', ["%{$needle}%"])
                            ->orWhereRaw('lower(access_control_description) like ?', ["%{$needle}%"]);
                    });
                }),
            ])
            ->allowedSorts([
                'access_control_name',
                'access_control_default',
                'access_control_description',
                'nodes_count',
            ])
            ->defaultSort('access_control_name')
            ->paginate($this->perPage);
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && !userCheckPermission('access_control_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (!$itemUuid && !userCheckPermission('access_control_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $item = $itemUuid
            ? AccessControl::query()->with('nodes')->whereKey($itemUuid)->firstOrFail()
            : new AccessControl([
                'access_control_uuid' => null,
                'access_control_default' => 'deny',
                'nodes' => [],
            ]);

        if ($itemUuid) {
            $item->setRelation('nodes', $item->nodes->values());
        }

        return response()->json([
            'item' => $item,
            'routes' => [
                'store_route' => route('access-controls.store'),
                'update_route' => $itemUuid ? route('access-controls.update', ['access_control' => $item->access_control_uuid]) : null,
            ],
        ]);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (!userCheckPermission('access_control_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = QueryBuilder::for(AccessControl::class)
            ->select(['access_control_uuid'])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = strtolower((string) $value);

                    $query->where(function ($query) use ($needle) {
                        $query->whereRaw('lower(access_control_name) like ?', ["%{$needle}%"])
                            ->orWhereRaw('lower(access_control_default) like ?', ["%{$needle}%"])
                            ->orWhereRaw('lower(access_control_description) like ?', ["%{$needle}%"]);
                    });
                }),
            ])
            ->defaultSort('access_control_name')
            ->pluck('access_control_uuid');

        return response()->json([
            'messages' => ['success' => ['All matching access control lists selected.']],
            'items' => $items,
        ]);
    }

    public function bulkDelete(Request $request, AccessControlService $service): JsonResponse
    {
        if (!userCheckPermission('access_control_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $accessControls = $this->selectedAccessControls($request);

        if ($accessControls->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No access control lists selected.']],
            ], 422);
        }

        try {
            DB::beginTransaction();

            $accessControls->each(function (AccessControl $accessControl) use ($service) {
                $service->removeGatewayProviderIpsForListName((string) $accessControl->access_control_name);
                $accessControl->nodes()->delete();
                $accessControl->delete();
            });

            DB::commit();

            $service->sync();

            return response()->json([
                'messages' => ['success' => ["Deleted {$accessControls->count()} access control list(s)."]],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('AccessControlController@bulkDelete error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to delete selected access control lists.']],
            ], 500);
        }
    }

    public function bulkCopy(Request $request, AccessControlService $service): JsonResponse
    {
        if (!userCheckPermission('access_control_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $accessControls = $this->selectedAccessControls($request);

        if ($accessControls->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No access control lists selected.']],
            ], 422);
        }

        try {
            DB::beginTransaction();

            $accessControls->each(function (AccessControl $accessControl) {
                $copy = $accessControl->replicate();
                $copy->access_control_uuid = null;
                $copy->access_control_name = $accessControl->access_control_name . ' copy';
                $copy->save();

                $accessControl->nodes->each(function ($node) use ($copy) {
                    $copy->nodes()->create($node->only([
                        'node_type',
                        'node_cidr',
                        'node_description',
                    ]));
                });
            });

            DB::commit();

            $service->sync();

            return response()->json([
                'messages' => ['success' => ["Copied {$accessControls->count()} access control list(s)."]],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('AccessControlController@bulkCopy error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to copy selected access control lists.']],
            ], 500);
        }
    }

    public function reload(AccessControlService $service): JsonResponse
    {
        if (!userCheckPermission('access_control_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $response = $service->sync();

        return response()->json([
            'messages' => ['success' => array_filter([
                'Access control lists reloaded.',
                $response ? "FreeSWITCH: {$response}" : null,
            ])],
        ]);
    }

    private function selectedAccessControls(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['required', 'uuid'],
        ]);

        return AccessControl::query()
            ->with('nodes')
            ->whereIn('access_control_uuid', array_values(array_unique($validated['items'])))
            ->get();
    }
}
