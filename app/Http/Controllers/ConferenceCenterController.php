<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConferenceCenterRequest;
use App\Http\Requests\UpdateConferenceCenterRequest;
use App\Models\ConferenceCenter;
use App\Services\ConferenceCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ConferenceCenterController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (! userCheckPermission('conference_center_view')) {
            return redirect('/');
        }

        return Inertia::render('ConferenceCenters', [
            'routes' => [
                'current_page' => route('conference-centers.index'),
                'data_route' => route('conference-centers.data'),
                'select_all' => route('conference-centers.select.all'),
                'bulk_delete' => route('conference-centers.bulk.delete'),
                'bulk_toggle' => route('conference-centers.bulk.toggle'),
                'store' => route('conference-centers.store'),
                'item_options' => route('conference-centers.item.options'),
                'rooms' => '/app/conference_centers/conference_rooms.php',
                'active_conferences' => '/app/conferences_active/conferences_active.php',
            ],
            'permissions' => [
                'create' => userCheckPermission('conference_center_add'),
                'update' => userCheckPermission('conference_center_edit'),
                'destroy' => userCheckPermission('conference_center_delete'),
                'view_global' => userCheckPermission('conference_center_all'),
                'view_active' => userCheckPermission('conference_active_view'),
            ],
        ]);
    }

    public function store(StoreConferenceCenterRequest $request, ConferenceCenterService $service): JsonResponse
    {
        try {
            $conferenceCenter = $service->save($request->validated());

            return response()->json([
                'messages' => ['success' => ['Conference center created successfully.']],
                'conference_center_uuid' => $conferenceCenter->conference_center_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('ConferenceCenterController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create conference center.']],
            ], 500);
        }
    }

    public function update(UpdateConferenceCenterRequest $request, ConferenceCenter $conference_center, ConferenceCenterService $service): JsonResponse
    {
        if ($conference_center->domain_uuid !== session('domain_uuid')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $service->save($request->validated(), $conference_center);

            return response()->json([
                'messages' => ['success' => ['Conference center updated successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('ConferenceCenterController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update conference center.']],
            ], 500);
        }
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('conference_center_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (! $itemUuid && ! userCheckPermission('conference_center_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($itemUuid) {
            $item = ConferenceCenter::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($itemUuid)
                ->firstOrFail();
        } else {
            $item = new ConferenceCenter();
            $item->conference_center_enabled = 'true';
            $item->conference_center_pin_length = 9;
        }

        return response()->json([
            'item' => $item,
            'sound_options' => getSoundsCollectionGrouped(session('domain_uuid')),
            'routes' => [
                'store_route' => route('conference-centers.store'),
                'update_route' => $itemUuid ? route('conference-centers.update', ['conference_center' => $item->conference_center_uuid]) : null,
            ],
        ]);
    }

    public function getData(Request $request)
    {
        if (! userCheckPermission('conference_center_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        return $this->scopedConferenceCenters($request)
            ->select([
                'domain_uuid',
                'conference_center_uuid',
                'dialplan_uuid',
                'conference_center_name',
                'conference_center_extension',
                'conference_center_greeting',
                'conference_center_pin_length',
                'conference_center_enabled',
                'conference_center_description',
            ])
            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description');
            }])
            ->allowedSorts([
                'conference_center_name',
                'conference_center_extension',
                'conference_center_greeting',
                'conference_center_pin_length',
                'conference_center_enabled',
                'conference_center_description',
            ])
            ->defaultSort('conference_center_name')
            ->paginate($this->perPage);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_center_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->scopedConferenceCenters($request)
            ->select(['conference_center_uuid'])
            ->defaultSort('conference_center_name')
            ->pluck('conference_center_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching conference centers selected.']],
        ]);
    }

    public function bulkDelete(Request $request, ConferenceCenterService $service): JsonResponse
    {
        if (! userCheckPermission('conference_center_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No conference centers selected.']],
            ], 422);
        }

        $items = ConferenceCenter::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('conference_center_uuid', $uuids)
            ->get();

        $deleted = $service->delete($items);

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} conference center(s)."]],
        ]);
    }

    public function bulkToggle(Request $request, ConferenceCenterService $service): JsonResponse
    {
        if (! userCheckPermission('conference_center_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No conference centers selected.']],
            ], 422);
        }

        $items = ConferenceCenter::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('conference_center_uuid', $uuids)
            ->get();

        $service->toggle($items);

        return response()->json([
            'messages' => ['success' => ['Conference center status toggled.']],
        ]);
    }

    private function scopedConferenceCenters(Request $request): QueryBuilder
    {
        return QueryBuilder::for(ConferenceCenter::class)
            ->when(! userCheckPermission('conference_center_all') || ! $request->boolean('filter.showGlobal'), function ($query) {
                $query->where('domain_uuid', session('domain_uuid'));
            })
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('conference_center_uuid', 'ilike', "%{$needle}%")
                            ->orWhere('conference_center_name', 'ilike', "%{$needle}%")
                            ->orWhere('conference_center_extension', 'ilike', "%{$needle}%")
                            ->orWhere('conference_center_greeting', 'ilike', "%{$needle}%")
                            ->orWhere('conference_center_description', 'ilike', "%{$needle}%");
                    });
                }),
                AllowedFilter::callback('showGlobal', function ($query, $value) {}),
            ]);
    }

    private function validatedUuids(Request $request): array
    {
        return collect($request->input('items', []))
            ->filter(fn ($uuid) => is_string($uuid) && preg_match('/^[0-9a-fA-F-]{36}$/', $uuid))
            ->values()
            ->all();
    }
}
