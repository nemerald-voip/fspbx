<?php

namespace App\Http\Controllers;

use App\Models\ConferenceProfile;
use App\Models\ConferenceProfileParam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ConferenceProfileController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (! userCheckPermission('conference_profile_view')) {
            return redirect('/');
        }

        return Inertia::render('ConferenceProfiles', [
            'routes' => [
                'current_page' => route('conference-profiles.index'),
                'data_route' => route('conference-profiles.data'),
                'store' => route('conference-profiles.store'),
                'item_options' => route('conference-profiles.item.options'),
                'select_all' => route('conference-profiles.select.all'),
                'bulk_copy' => route('conference-profiles.bulk.copy'),
                'bulk_delete' => route('conference-profiles.bulk.delete'),
                'bulk_toggle' => route('conference-profiles.bulk.toggle'),
                'conference_centers' => route('conference-centers.index'),
            ],
            'permissions' => [
                'create' => userCheckPermission('conference_profile_add'),
                'update' => userCheckPermission('conference_profile_edit'),
                'destroy' => userCheckPermission('conference_profile_delete'),
                'param_create' => userCheckPermission('conference_profile_param_add'),
                'param_update' => userCheckPermission('conference_profile_param_edit'),
                'param_destroy' => userCheckPermission('conference_profile_param_delete'),
            ],
        ]);
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('conference_profile_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (! $itemUuid && ! userCheckPermission('conference_profile_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($itemUuid) {
            $item = QueryBuilder::for(ConferenceProfile::class)
                ->whereKey($itemUuid)
                ->firstOrFail();

            $params = QueryBuilder::for(ConferenceProfileParam::class)
                ->where('conference_profile_uuid', $itemUuid)
                ->allowedSorts([
                    'profile_param_name',
                    'profile_param_value',
                    'profile_param_description',
                    'profile_param_enabled',
                ])
                ->defaultSort('profile_param_name')
                ->get()
                ->map(fn (ConferenceProfileParam $param) => $this->serializeParam($param))
                ->values();
        } else {
            $item = new ConferenceProfile([
                'profile_enabled' => 'true',
            ]);
            $params = collect();
        }

        return response()->json([
            'item' => $item,
            'params' => $params,
            'permissions' => [
                'param_create' => userCheckPermission('conference_profile_param_add'),
                'param_update' => userCheckPermission('conference_profile_param_edit'),
                'param_destroy' => userCheckPermission('conference_profile_param_delete'),
            ],
            'routes' => [
                'store_route' => route('conference-profiles.store'),
                'update_route' => $itemUuid ? route('conference-profiles.update', ['conference_profile' => $item->conference_profile_uuid]) : null,
                'param_store_route' => $itemUuid ? route('conference-profiles.params.store', ['conference_profile' => $item->conference_profile_uuid]) : null,
                'param_bulk_delete_route' => route('conference-profiles.params.bulk.delete'),
                'param_bulk_toggle_route' => route('conference-profiles.params.bulk.toggle'),
            ],
        ]);
    }

    public function getData(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_profile_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->conferenceProfilesQuery($request)
            ->select([
                'conference_profile_uuid',
                'profile_name',
                'profile_enabled',
                'profile_description',
            ])
            ->allowedSorts([
                'profile_name',
                'profile_enabled',
                'profile_description',
            ])
            ->defaultSort('profile_name')
            ->paginate($this->perPage)
            ->through(function (ConferenceProfile $profile) {
                return [
                    'conference_profile_uuid' => $profile->conference_profile_uuid,
                    'profile_name' => $profile->profile_name,
                    'profile_enabled' => $profile->profile_enabled,
                    'profile_description' => $profile->profile_description,
                    'update_route' => route('conference-profiles.update', ['conference_profile' => $profile->conference_profile_uuid]),
                    'destroy_route' => route('conference-profiles.destroy', ['conference_profile' => $profile->conference_profile_uuid]),
                ];
            });

        return response()->json($items);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_profile_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->conferenceProfilesQuery($request)
            ->select(['conference_profile_uuid'])
            ->defaultSort('profile_name')
            ->pluck('conference_profile_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching conference profiles selected.']],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_profile_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $validated = $this->validatedProfileData($request);

        try {
            $profile = new ConferenceProfile($validated);
            $profile->conference_profile_uuid = (string) Str::uuid();
            $profile->insert_date = now();
            $profile->insert_user = session('user_uuid');
            $profile->update_date = now();
            $profile->update_user = session('user_uuid');
            $profile->save();

            return response()->json([
                'messages' => ['success' => ['Conference profile created successfully.']],
                'conference_profile_uuid' => $profile->conference_profile_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('ConferenceProfileController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create conference profile.']],
            ], 500);
        }
    }

    public function update(Request $request, ConferenceProfile $conference_profile): JsonResponse
    {
        if (! userCheckPermission('conference_profile_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $validated = $this->validatedProfileData($request);

        try {
            $conference_profile->fill($validated);
            $conference_profile->update_date = now();
            $conference_profile->update_user = session('user_uuid');
            $conference_profile->save();

            return response()->json([
                'messages' => ['success' => ['Conference profile updated successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('ConferenceProfileController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update conference profile.']],
            ], 500);
        }
    }

    public function destroy(ConferenceProfile $conference_profile): JsonResponse
    {
        if (! userCheckPermission('conference_profile_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            DB::transaction(function () use ($conference_profile) {
                DB::table('v_conference_profile_params')
                    ->where('conference_profile_uuid', $conference_profile->conference_profile_uuid)
                    ->delete();

                $conference_profile->delete();
            });

            return response()->json([
                'messages' => ['success' => ['Conference profile deleted successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('ConferenceProfileController@destroy error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to delete conference profile.']],
            ], 500);
        }
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_profile_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No conference profiles selected.']],
            ], 422);
        }

        $profiles = QueryBuilder::for(ConferenceProfile::class)
            ->whereIn('conference_profile_uuid', $uuids)
            ->get();

        DB::transaction(function () use ($profiles) {
            foreach ($profiles as $profile) {
                DB::table('v_conference_profile_params')
                    ->where('conference_profile_uuid', $profile->conference_profile_uuid)
                    ->delete();

                $profile->delete();
            }
        });

        return response()->json([
            'messages' => ['success' => ["Deleted {$profiles->count()} conference profile(s)."]],
        ]);
    }

    public function bulkToggle(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_profile_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No conference profiles selected.']],
            ], 422);
        }

        $profiles = QueryBuilder::for(ConferenceProfile::class)
            ->whereIn('conference_profile_uuid', $uuids)
            ->get();

        foreach ($profiles as $profile) {
            $profile->profile_enabled = $profile->profile_enabled === 'true' ? 'false' : 'true';
            $profile->update_date = now();
            $profile->update_user = session('user_uuid');
            $profile->save();
        }

        return response()->json([
            'messages' => ['success' => ['Conference profile status toggled.']],
        ]);
    }

    public function bulkCopy(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_profile_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No conference profiles selected.']],
            ], 422);
        }

        $profiles = QueryBuilder::for(ConferenceProfile::class)
            ->whereIn('conference_profile_uuid', $uuids)
            ->get();

        DB::transaction(function () use ($profiles) {
            foreach ($profiles as $profile) {
                $newProfile = $profile->replicate();
                $newProfile->conference_profile_uuid = (string) Str::uuid();
                $newProfile->profile_description = trim((string) $profile->profile_description) . ' (copy)';
                $newProfile->insert_date = now();
                $newProfile->insert_user = session('user_uuid');
                $newProfile->update_date = now();
                $newProfile->update_user = session('user_uuid');
                $newProfile->save();

                $params = QueryBuilder::for(ConferenceProfileParam::class)
                    ->where('conference_profile_uuid', $profile->conference_profile_uuid)
                    ->get();

                foreach ($params as $param) {
                    $newParam = $param->replicate();
                    $newParam->conference_profile_param_uuid = (string) Str::uuid();
                    $newParam->conference_profile_uuid = $newProfile->conference_profile_uuid;
                    $newParam->insert_date = now();
                    $newParam->insert_user = session('user_uuid');
                    $newParam->update_date = now();
                    $newParam->update_user = session('user_uuid');
                    $newParam->save();
                }
            }
        });

        return response()->json([
            'messages' => ['success' => ["Copied {$profiles->count()} conference profile(s)."]],
        ]);
    }

    public function storeParam(Request $request, ConferenceProfile $conference_profile): JsonResponse
    {
        if (! userCheckPermission('conference_profile_param_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $validated = $this->validatedParamData($request);

        try {
            $param = new ConferenceProfileParam($validated);
            $param->conference_profile_param_uuid = (string) Str::uuid();
            $param->conference_profile_uuid = $conference_profile->conference_profile_uuid;
            $param->insert_date = now();
            $param->insert_user = session('user_uuid');
            $param->update_date = now();
            $param->update_user = session('user_uuid');
            $param->save();

            return response()->json([
                'messages' => ['success' => ['Conference profile parameter created successfully.']],
                'param' => $this->serializeParam($param),
            ], 201);
        } catch (\Throwable $e) {
            logger('ConferenceProfileController@storeParam error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create conference profile parameter.']],
            ], 500);
        }
    }

    public function updateParam(Request $request, ConferenceProfileParam $conference_profile_param): JsonResponse
    {
        if (! userCheckPermission('conference_profile_param_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $validated = $this->validatedParamData($request);

        try {
            $conference_profile_param->fill($validated);
            $conference_profile_param->update_date = now();
            $conference_profile_param->update_user = session('user_uuid');
            $conference_profile_param->save();

            return response()->json([
                'messages' => ['success' => ['Conference profile parameter updated successfully.']],
                'param' => $this->serializeParam($conference_profile_param),
            ]);
        } catch (\Throwable $e) {
            logger('ConferenceProfileController@updateParam error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update conference profile parameter.']],
            ], 500);
        }
    }

    public function destroyParam(ConferenceProfileParam $conference_profile_param): JsonResponse
    {
        if (! userCheckPermission('conference_profile_param_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $conference_profile_param->delete();

            return response()->json([
                'messages' => ['success' => ['Conference profile parameter deleted successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('ConferenceProfileController@destroyParam error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to delete conference profile parameter.']],
            ], 500);
        }
    }

    public function bulkDeleteParams(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_profile_param_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No conference profile parameters selected.']],
            ], 422);
        }

        $deleted = QueryBuilder::for(ConferenceProfileParam::class)
            ->whereIn('conference_profile_param_uuid', $uuids)
            ->delete();

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} conference profile parameter(s)."]],
        ]);
    }

    public function bulkToggleParams(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_profile_param_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No conference profile parameters selected.']],
            ], 422);
        }

        $items = QueryBuilder::for(ConferenceProfileParam::class)
            ->whereIn('conference_profile_param_uuid', $uuids)
            ->get();

        foreach ($items as $item) {
            $item->profile_param_enabled = $item->profile_param_enabled === 'true' ? 'false' : 'true';
            $item->update_date = now();
            $item->update_user = session('user_uuid');
            $item->save();
        }

        return response()->json([
            'messages' => ['success' => ['Conference profile parameter status toggled.']],
        ]);
    }

    private function validatedProfileData(Request $request): array
    {
        return $request->validate([
            'profile_name' => ['required', 'string', 'max:255'],
            'profile_enabled' => ['required', 'string', 'in:true,false'],
            'profile_description' => ['nullable', 'string'],
        ]);
    }

    private function conferenceProfilesQuery(Request $request): QueryBuilder
    {
        return QueryBuilder::for(ConferenceProfile::class)
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = strtolower((string) $value);

                    $query->where(function ($query) use ($needle) {
                        $query->whereRaw('lower(profile_name) like ?', ["%{$needle}%"])
                            ->orWhereRaw('lower(profile_description) like ?', ["%{$needle}%"]);
                    });
                }),
            ]);
    }

    private function validatedParamData(Request $request): array
    {
        return $request->validate([
            'profile_param_name' => ['required', 'string', 'max:255'],
            'profile_param_value' => ['required', 'string', 'max:255'],
            'profile_param_enabled' => ['required', 'string', 'in:true,false'],
            'profile_param_description' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function validatedUuids(Request $request): array
    {
        return collect($request->input('items', []))
            ->filter(fn ($uuid) => is_string($uuid) && preg_match('/^[0-9a-fA-F-]{36}$/', $uuid))
            ->values()
            ->all();
    }

    private function serializeParam(ConferenceProfileParam $param): array
    {
        return [
            'conference_profile_param_uuid' => $param->conference_profile_param_uuid,
            'conference_profile_uuid' => $param->conference_profile_uuid,
            'profile_param_name' => $param->profile_param_name,
            'profile_param_value' => $param->profile_param_value,
            'profile_param_description' => $param->profile_param_description,
            'profile_param_enabled' => $param->profile_param_enabled,
            'update_route' => route('conference-profiles.params.update', ['conference_profile_param' => $param->conference_profile_param_uuid]),
            'destroy_route' => route('conference-profiles.params.destroy', ['conference_profile_param' => $param->conference_profile_param_uuid]),
        ];
    }
}
