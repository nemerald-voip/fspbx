<?php

namespace App\Http\Controllers;

use App\Models\ConferenceControl;
use App\Models\ConferenceControlDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ConferenceControlController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (! userCheckPermission('conference_control_view')) {
            return redirect('/');
        }

        return Inertia::render('ConferenceControls', [
            'routes' => [
                'current_page' => route('conference-controls.index'),
                'data_route' => route('conference-controls.data'),
                'store' => route('conference-controls.store'),
                'item_options' => route('conference-controls.item.options'),
                'select_all' => route('conference-controls.select.all'),
                'bulk_copy' => route('conference-controls.bulk.copy'),
                'bulk_delete' => route('conference-controls.bulk.delete'),
                'bulk_toggle' => route('conference-controls.bulk.toggle'),
                'conference_centers' => route('conference-centers.index'),
            ],
            'permissions' => [
                'create' => userCheckPermission('conference_control_add'),
                'update' => userCheckPermission('conference_control_edit'),
                'destroy' => userCheckPermission('conference_control_delete'),
                'detail_create' => userCheckPermission('conference_control_detail_add'),
                'detail_update' => userCheckPermission('conference_control_detail_edit'),
                'detail_destroy' => userCheckPermission('conference_control_detail_delete'),
            ],
        ]);
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('conference_control_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (! $itemUuid && ! userCheckPermission('conference_control_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($itemUuid) {
            $item = QueryBuilder::for(ConferenceControl::class)
                ->whereKey($itemUuid)
                ->firstOrFail();

            $details = QueryBuilder::for(ConferenceControlDetail::class)
                ->where('conference_control_uuid', $itemUuid)
                ->allowedSorts([
                    'control_digits',
                    'control_action',
                    'control_data',
                    'control_enabled',
                ])
                ->defaultSort('control_digits')
                ->get()
                ->map(fn (ConferenceControlDetail $detail) => $this->serializeDetail($detail))
                ->values();
        } else {
            $item = new ConferenceControl([
                'control_enabled' => 'true',
            ]);
            $details = collect();
        }

        return response()->json([
            'item' => $item,
            'details' => $details,
            'permissions' => [
                'detail_create' => userCheckPermission('conference_control_detail_add'),
                'detail_update' => userCheckPermission('conference_control_detail_edit'),
                'detail_destroy' => userCheckPermission('conference_control_detail_delete'),
            ],
            'routes' => [
                'store_route' => route('conference-controls.store'),
                'update_route' => $itemUuid ? route('conference-controls.update', ['conference_control' => $item->conference_control_uuid]) : null,
                'detail_store_route' => $itemUuid ? route('conference-controls.details.store', ['conference_control' => $item->conference_control_uuid]) : null,
                'detail_bulk_delete_route' => route('conference-controls.details.bulk.delete'),
                'detail_bulk_toggle_route' => route('conference-controls.details.bulk.toggle'),
            ],
        ]);
    }

    public function getData(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_control_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->conferenceControlsQuery($request)
            ->select([
                'conference_control_uuid',
                'control_name',
                'control_enabled',
                'control_description',
            ])
            ->allowedSorts([
                'control_name',
                'control_enabled',
                'control_description',
            ])
            ->defaultSort('control_name')
            ->paginate($this->perPage)
            ->through(function (ConferenceControl $control) {
                return [
                    'conference_control_uuid' => $control->conference_control_uuid,
                    'control_name' => $control->control_name,
                    'control_enabled' => $control->control_enabled,
                    'control_description' => $control->control_description,
                    'update_route' => route('conference-controls.update', ['conference_control' => $control->conference_control_uuid]),
                    'destroy_route' => route('conference-controls.destroy', ['conference_control' => $control->conference_control_uuid]),
                ];
            });

        return response()->json($items);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_control_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->conferenceControlsQuery($request)
            ->select(['conference_control_uuid'])
            ->defaultSort('control_name')
            ->pluck('conference_control_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching conference controls selected.']],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_control_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $validated = $this->validatedControlData($request);

        try {
            $control = new ConferenceControl($validated);
            $control->conference_control_uuid = (string) Str::uuid();
            $control->insert_date = now();
            $control->insert_user = session('user_uuid');
            $control->update_date = now();
            $control->update_user = session('user_uuid');
            $control->save();

            return response()->json([
                'messages' => ['success' => ['Conference control created successfully.']],
                'conference_control_uuid' => $control->conference_control_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('ConferenceControlController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create conference control.']],
            ], 500);
        }
    }

    public function update(Request $request, ConferenceControl $conference_control): JsonResponse
    {
        if (! userCheckPermission('conference_control_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $validated = $this->validatedControlData($request);

        try {
            $conference_control->fill($validated);
            $conference_control->update_date = now();
            $conference_control->update_user = session('user_uuid');
            $conference_control->save();

            return response()->json([
                'messages' => ['success' => ['Conference control updated successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('ConferenceControlController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update conference control.']],
            ], 500);
        }
    }

    public function destroy(ConferenceControl $conference_control): JsonResponse
    {
        if (! userCheckPermission('conference_control_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            DB::transaction(function () use ($conference_control) {
                DB::table('v_conference_control_details')
                    ->where('conference_control_uuid', $conference_control->conference_control_uuid)
                    ->delete();

                $conference_control->delete();
            });

            return response()->json([
                'messages' => ['success' => ['Conference control deleted successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('ConferenceControlController@destroy error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to delete conference control.']],
            ], 500);
        }
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_control_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No conference controls selected.']],
            ], 422);
        }

        $controls = QueryBuilder::for(ConferenceControl::class)
            ->whereIn('conference_control_uuid', $uuids)
            ->get();

        DB::transaction(function () use ($controls) {
            foreach ($controls as $control) {
                DB::table('v_conference_control_details')
                    ->where('conference_control_uuid', $control->conference_control_uuid)
                    ->delete();

                $control->delete();
            }
        });

        return response()->json([
            'messages' => ['success' => ["Deleted {$controls->count()} conference control(s)."]],
        ]);
    }

    public function bulkToggle(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_control_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No conference controls selected.']],
            ], 422);
        }

        $controls = QueryBuilder::for(ConferenceControl::class)
            ->whereIn('conference_control_uuid', $uuids)
            ->get();

        foreach ($controls as $control) {
            $control->control_enabled = $control->control_enabled === 'true' ? 'false' : 'true';
            $control->update_date = now();
            $control->update_user = session('user_uuid');
            $control->save();
        }

        return response()->json([
            'messages' => ['success' => ['Conference control status toggled.']],
        ]);
    }

    public function bulkCopy(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_control_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No conference controls selected.']],
            ], 422);
        }

        $controls = QueryBuilder::for(ConferenceControl::class)
            ->whereIn('conference_control_uuid', $uuids)
            ->get();

        DB::transaction(function () use ($controls) {
            foreach ($controls as $control) {
                $newControl = $control->replicate();
                $newControl->conference_control_uuid = (string) Str::uuid();
                $newControl->control_description = trim((string) $control->control_description) . ' (copy)';
                $newControl->insert_date = now();
                $newControl->insert_user = session('user_uuid');
                $newControl->update_date = now();
                $newControl->update_user = session('user_uuid');
                $newControl->save();

                $details = QueryBuilder::for(ConferenceControlDetail::class)
                    ->where('conference_control_uuid', $control->conference_control_uuid)
                    ->get();

                foreach ($details as $detail) {
                    $newDetail = $detail->replicate();
                    $newDetail->conference_control_detail_uuid = (string) Str::uuid();
                    $newDetail->conference_control_uuid = $newControl->conference_control_uuid;
                    $newDetail->insert_date = now();
                    $newDetail->insert_user = session('user_uuid');
                    $newDetail->update_date = now();
                    $newDetail->update_user = session('user_uuid');
                    $newDetail->save();
                }
            }
        });

        return response()->json([
            'messages' => ['success' => ["Copied {$controls->count()} conference control(s)."]],
        ]);
    }

    public function storeDetail(Request $request, ConferenceControl $conference_control): JsonResponse
    {
        if (! userCheckPermission('conference_control_detail_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $validated = $this->validatedDetailData($request);

        try {
            $detail = new ConferenceControlDetail($validated);
            $detail->conference_control_detail_uuid = (string) Str::uuid();
            $detail->conference_control_uuid = $conference_control->conference_control_uuid;
            $detail->insert_date = now();
            $detail->insert_user = session('user_uuid');
            $detail->update_date = now();
            $detail->update_user = session('user_uuid');
            $detail->save();

            return response()->json([
                'messages' => ['success' => ['Conference control detail created successfully.']],
                'detail' => $this->serializeDetail($detail),
            ], 201);
        } catch (\Throwable $e) {
            logger('ConferenceControlController@storeDetail error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create conference control detail.']],
            ], 500);
        }
    }

    public function updateDetail(Request $request, ConferenceControlDetail $conference_control_detail): JsonResponse
    {
        if (! userCheckPermission('conference_control_detail_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $validated = $this->validatedDetailData($request);

        try {
            $conference_control_detail->fill($validated);
            $conference_control_detail->update_date = now();
            $conference_control_detail->update_user = session('user_uuid');
            $conference_control_detail->save();

            return response()->json([
                'messages' => ['success' => ['Conference control detail updated successfully.']],
                'detail' => $this->serializeDetail($conference_control_detail),
            ]);
        } catch (\Throwable $e) {
            logger('ConferenceControlController@updateDetail error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update conference control detail.']],
            ], 500);
        }
    }

    public function destroyDetail(ConferenceControlDetail $conference_control_detail): JsonResponse
    {
        if (! userCheckPermission('conference_control_detail_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $conference_control_detail->delete();

            return response()->json([
                'messages' => ['success' => ['Conference control detail deleted successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('ConferenceControlController@destroyDetail error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to delete conference control detail.']],
            ], 500);
        }
    }

    public function bulkDeleteDetails(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_control_detail_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No conference control details selected.']],
            ], 422);
        }

        $deleted = QueryBuilder::for(ConferenceControlDetail::class)
            ->whereIn('conference_control_detail_uuid', $uuids)
            ->delete();

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} conference control detail(s)."]],
        ]);
    }

    public function bulkToggleDetails(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_control_detail_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No conference control details selected.']],
            ], 422);
        }

        $items = QueryBuilder::for(ConferenceControlDetail::class)
            ->whereIn('conference_control_detail_uuid', $uuids)
            ->get();

        foreach ($items as $item) {
            $item->control_enabled = $item->control_enabled === 'true' ? 'false' : 'true';
            $item->update_date = now();
            $item->update_user = session('user_uuid');
            $item->save();
        }

        return response()->json([
            'messages' => ['success' => ['Conference control detail status toggled.']],
        ]);
    }

    private function validatedControlData(Request $request): array
    {
        return $request->validate([
            'control_name' => ['required', 'string', 'max:255'],
            'control_enabled' => ['required', 'string', 'in:true,false'],
            'control_description' => ['nullable', 'string'],
        ]);
    }

    private function conferenceControlsQuery(Request $request): QueryBuilder
    {
        return QueryBuilder::for(ConferenceControl::class)
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = strtolower((string) $value);

                    $query->where(function ($query) use ($needle) {
                        $query->whereRaw('lower(control_name) like ?', ["%{$needle}%"])
                            ->orWhereRaw('lower(control_description) like ?', ["%{$needle}%"]);
                    });
                }),
            ]);
    }

    private function validatedDetailData(Request $request): array
    {
        return $request->validate([
            'control_digits' => ['required', 'string', 'max:255'],
            'control_action' => ['required', 'string', 'max:255'],
            'control_data' => ['nullable', 'string', 'max:255'],
            'control_enabled' => ['required', 'string', 'in:true,false'],
        ]);
    }

    private function validatedUuids(Request $request): array
    {
        return collect($request->input('items', []))
            ->filter(fn ($uuid) => is_string($uuid) && preg_match('/^[0-9a-fA-F-]{36}$/', $uuid))
            ->values()
            ->all();
    }

    private function serializeDetail(ConferenceControlDetail $detail): array
    {
        return [
            'conference_control_detail_uuid' => $detail->conference_control_detail_uuid,
            'conference_control_uuid' => $detail->conference_control_uuid,
            'control_digits' => $detail->control_digits,
            'control_action' => $detail->control_action,
            'control_data' => $detail->control_data,
            'control_enabled' => $detail->control_enabled,
            'update_route' => route('conference-controls.details.update', ['conference_control_detail' => $detail->conference_control_detail_uuid]),
            'destroy_route' => route('conference-controls.details.destroy', ['conference_control_detail' => $detail->conference_control_detail_uuid]),
        ];
    }
}
