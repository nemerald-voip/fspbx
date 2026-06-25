<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSipProfileRequest;
use App\Http\Requests\UpdateSipProfileRequest;
use App\Models\SipProfileDomain;
use App\Models\SipProfileSettings;
use App\Models\SipProfiles;
use App\Services\SipProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SipProfileController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (! userCheckPermission('sip_profile_view')) {
            return redirect('/');
        }

        return Inertia::render('SipProfiles', [
            'routes' => [
                'current_page' => route('sip-profiles.index'),
                'data_route' => route('sip-profiles.data'),
                'store' => route('sip-profiles.store'),
                'item_options' => route('sip-profiles.item.options'),
                'select_all' => route('sip-profiles.select.all'),
                'bulk_delete' => route('sip-profiles.bulk.delete'),
                'bulk_toggle' => route('sip-profiles.bulk.toggle'),
            ],
            'permissions' => $this->permissions(),
        ]);
    }

    public function getData(Request $request): JsonResponse
    {
        if (! userCheckPermission('sip_profile_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $profiles = $this->profileQuery($request)
            ->select([
                'sip_profile_uuid',
                'sip_profile_name',
                'sip_profile_hostname',
                'sip_profile_enabled',
                'sip_profile_description',
            ])
            ->withCount(['domains', 'settings'])
            ->allowedSorts([
                'sip_profile_name',
                'sip_profile_hostname',
                'sip_profile_enabled',
                'sip_profile_description',
            ])
            ->defaultSort('sip_profile_name')
            ->paginate($this->perPage)
            ->appends($request->query())
            ->through(fn (SipProfiles $profile) => [
                'sip_profile_uuid' => $profile->sip_profile_uuid,
                'sip_profile_name' => $profile->sip_profile_name,
                'sip_profile_hostname' => $profile->sip_profile_hostname,
                'sip_profile_enabled' => $profile->sip_profile_enabled,
                'sip_profile_description' => $profile->sip_profile_description,
                'domains_count' => $profile->domains_count,
                'settings_count' => $profile->settings_count,
                'update_route' => route('sip-profiles.update', ['sip_profile' => $profile->sip_profile_uuid]),
                'destroy_route' => route('sip-profiles.destroy', ['sip_profile' => $profile->sip_profile_uuid]),
            ]);

        return response()->json($profiles);
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('sip_profile_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (! $itemUuid && ! userCheckPermission('sip_profile_add')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $profile = $itemUuid
            ? SipProfiles::query()->whereKey($itemUuid)->firstOrFail()
            : new SipProfiles(['sip_profile_enabled' => 'true']);

        return response()->json([
            'item' => $profile,
            'domains' => $itemUuid ? $this->domains($itemUuid) : [],
            'settings' => $itemUuid ? $this->settings($itemUuid) : [],
            'permissions' => $this->permissions(),
            'routes' => [
                'store_route' => route('sip-profiles.store'),
                'update_route' => $itemUuid ? route('sip-profiles.update', ['sip_profile' => $profile->sip_profile_uuid]) : null,
            ],
        ]);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('sip_profile_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return response()->json([
            'items' => $this->profileQuery($request)
                ->defaultSort('sip_profile_name')
                ->pluck('sip_profile_uuid'),
            'messages' => ['success' => ['All matching SIP profiles selected.']],
        ]);
    }

    public function store(StoreSipProfileRequest $request, SipProfileService $service): JsonResponse
    {
        $profile = $service->save($request->validated());

        return response()->json([
            'messages' => ['success' => ['SIP profile created.']],
            'sip_profile_uuid' => $profile->sip_profile_uuid,
        ], 201);
    }

    public function update(UpdateSipProfileRequest $request, SipProfiles $sip_profile, SipProfileService $service): JsonResponse
    {
        $service->save($request->validated(), $sip_profile);

        return response()->json([
            'messages' => ['success' => ['SIP profile updated.']],
        ]);
    }

    public function destroy(SipProfiles $sip_profile, SipProfileService $service): JsonResponse
    {
        if (! userCheckPermission('sip_profile_delete')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $service->delete(collect([$sip_profile]));

        return response()->json([
            'messages' => ['success' => ['SIP profile deleted.']],
        ]);
    }

    public function bulkToggle(Request $request, SipProfileService $service): JsonResponse
    {
        if (! userCheckPermission('sip_profile_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $profiles = $this->selectedProfiles($request);
        if ($profiles->isEmpty()) {
            return response()->json(['messages' => ['error' => ['No SIP profiles selected.']]], 422);
        }

        $service->toggle($profiles);

        return response()->json([
            'messages' => ['success' => ['SIP profile enabled state toggled.']],
        ]);
    }

    public function bulkDelete(Request $request, SipProfileService $service): JsonResponse
    {
        if (! userCheckPermission('sip_profile_delete')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $profiles = $this->selectedProfiles($request);
        if ($profiles->isEmpty()) {
            return response()->json(['messages' => ['error' => ['No SIP profiles selected.']]], 422);
        }

        $count = $profiles->count();
        $service->delete($profiles);

        return response()->json([
            'messages' => ['success' => ["Deleted {$count} SIP profile(s)."]],
        ]);
    }

    private function profileQuery(Request $request): QueryBuilder
    {
        return QueryBuilder::for(SipProfiles::class)
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('sip_profile_name', 'ilike', "%{$needle}%")
                            ->orWhere('sip_profile_hostname', 'ilike', "%{$needle}%")
                            ->orWhere('sip_profile_description', 'ilike', "%{$needle}%");
                    });
                }),
                AllowedFilter::callback('sip_profile_enabled', function ($query, $value) {
                    $values = collect(is_array($value) ? $value : [$value])
                        ->map(fn ($item) => in_array($item, [true, 1, '1', 'true'], true) ? 'true' : (string) $item)
                        ->filter(fn ($item) => in_array($item, ['true', 'false'], true))
                        ->values();

                    if ($values->isNotEmpty()) {
                        $query->whereIn('sip_profile_enabled', $values->all());
                    }
                }),
            ]);
    }

    private function selectedProfiles(Request $request)
    {
        $uuids = collect($request->input('items', []))
            ->filter(fn ($uuid) => is_string($uuid) && \Illuminate\Support\Str::isUuid($uuid))
            ->values();

        if ($uuids->isEmpty()) {
            return collect();
        }

        return SipProfiles::query()
            ->whereIn('sip_profile_uuid', $uuids)
            ->get();
    }

    private function domains(string $profileUuid): array
    {
        return SipProfileDomain::query()
            ->where('sip_profile_uuid', $profileUuid)
            ->orderBy('sip_profile_domain_name')
            ->get()
            ->values()
            ->all();
    }

    private function settings(string $profileUuid): array
    {
        return SipProfileSettings::query()
            ->where('sip_profile_uuid', $profileUuid)
            ->orderBy('sip_profile_setting_name')
            ->get()
            ->values()
            ->all();
    }

    private function permissions(): array
    {
        return [
            'create' => userCheckPermission('sip_profile_add'),
            'update' => userCheckPermission('sip_profile_edit'),
            'destroy' => userCheckPermission('sip_profile_delete'),
            'domain_create' => userCheckPermission('sip_profile_domain_add'),
            'domain_update' => userCheckPermission('sip_profile_domain_edit'),
            'domain_destroy' => userCheckPermission('sip_profile_domain_delete'),
            'setting_create' => userCheckPermission('sip_profile_setting_add'),
            'setting_update' => userCheckPermission('sip_profile_setting_edit'),
            'setting_destroy' => userCheckPermission('sip_profile_setting_delete'),
        ];
    }
}
