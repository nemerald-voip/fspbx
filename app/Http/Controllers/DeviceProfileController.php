<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceProfileRequest;
use App\Http\Requests\UpdateDeviceProfileRequest;
use App\Models\DeviceProfile;
use App\Services\DeviceProfileService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DeviceProfileController extends Controller
{
    public function index()
    {
        if (! userCheckPermission('device_profile_view')) {
            return redirect('/');
        }

        return Inertia::render('DeviceProfiles', [
            'pagination' => [
                'per_page' => fspbx_pagination_per_page(),
                'per_page_options' => fspbx_pagination_options(),
            ],
            'routes' => [
                'current_page' => route('device-profiles.index'),
                'data_route' => route('device-profiles.data'),
                'select_all' => route('device-profiles.select.all'),
                'bulk_copy' => route('device-profiles.bulk.copy'),
                'bulk_toggle' => route('device-profiles.bulk.toggle'),
                'bulk_delete' => route('device-profiles.bulk.delete'),
                'store' => route('device-profiles.store'),
                'item_options' => route('device-profiles.item.options'),
                'devices' => route('devices.index'),
                'key_templates' => route('device-key-templates.index'),
            ],
            'permissions' => [
                'create' => userCheckPermission('device_profile_add'),
                'update' => userCheckPermission('device_profile_edit'),
                'destroy' => userCheckPermission('device_profile_delete'),
                'view_all' => userCheckPermission('device_profile_all'),
                'view_key_templates' => userCheckPermission('device_key_template_view'),
            ],
        ]);
    }

    public function getData(Request $request): JsonResponse
    {
        if (! userCheckPermission('device_profile_view')) {
            return $this->accessDenied();
        }

        $items = $this->profilesQuery($request)
            ->select([
                'device_profile_uuid',
                'domain_uuid',
                'device_profile_name',
                'device_profile_enabled',
                'device_profile_description',
            ])
            ->with([
                'domain:domain_uuid,domain_name,domain_description',
            ])
            ->withCount(['keys', 'settings', 'devices'])
            ->allowedSorts([
                'device_profile_name',
                'device_profile_enabled',
                'device_profile_description',
            ])
            ->defaultSort('device_profile_name')
            ->paginate(fspbx_pagination_per_page($request))
            ->through(fn (DeviceProfile $profile) => [
                'device_profile_uuid' => $profile->device_profile_uuid,
                'device_profile_name' => $profile->device_profile_name,
                'device_profile_enabled' => $profile->device_profile_enabled,
                'device_profile_description' => $profile->device_profile_description,
                'domain_uuid' => $profile->domain_uuid,
                'domain_label' => $profile->domain_uuid
                    ? ($profile->domain?->domain_description ?: $profile->domain?->domain_name ?: __('Account'))
                    : __('Global'),
                'keys_count' => $profile->keys_count,
                'settings_count' => $profile->settings_count,
                'devices_count' => $profile->devices_count,
            ]);

        return response()->json($items);
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('item_uuid', $request->input('itemUuid'));

        if ($itemUuid && ! userCheckPermission('device_profile_edit')) {
            return $this->accessDenied();
        }

        if (! $itemUuid && ! userCheckPermission('device_profile_add')) {
            return $this->accessDenied();
        }

        $permissions = $this->formPermissions();

        if ($itemUuid) {
            $query = DeviceProfile::query()
                ->whereKey($itemUuid);
            $this->applyScope($query, $request->input('scope'));

            $profile = $query
                ->with([
                    'keys' => fn ($query) => $query
                        ->orderBy('profile_key_vendor')
                        ->orderByRaw($this->keyCategoryOrder())
                        ->orderBy('profile_key_id'),
                    'settings' => fn ($query) => $query->orderBy('profile_setting_name'),
                    'domain:domain_uuid,domain_name,domain_description',
                ])
                ->firstOrFail();

            if (! $permissions['keys']['view']) {
                $profile->unsetRelation('keys');
            }

            if (! $permissions['settings']['view']) {
                $profile->unsetRelation('settings');
            }
        } else {
            $profile = new DeviceProfile([
                'domain_uuid' => session('domain_uuid'),
                'device_profile_enabled' => 'true',
            ]);
            $profile->setRelation('keys', collect());
            $profile->setRelation('settings', collect());
        }

        return response()->json([
            'item' => $profile,
            'domains' => $this->domainOptions($profile),
            'vendors' => $this->vendorOptions(),
            'vendor_functions' => $this->vendorFunctions(),
            'extension_names' => $this->extensionNames($profile),
            'permissions' => $permissions,
            'routes' => [
                'store_route' => route('device-profiles.store'),
                'update_route' => $itemUuid
                    ? route('device-profiles.update', ['device_profile' => $profile->device_profile_uuid])
                    : null,
            ],
        ]);
    }

    public function store(
        StoreDeviceProfileRequest $request,
        DeviceProfileService $service
    ): JsonResponse {
        $data = $this->prepareSaveData($request->validated());

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $profile = $service->save($data, null, $this->childPermissions());

            return response()->json([
                'messages' => ['success' => [__('Device profile created successfully.')]],
                'device_profile_uuid' => $profile->device_profile_uuid,
            ], 201);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'messages' => ['error' => [__('Failed to create the device profile.')]],
            ], 500);
        }
    }

    public function update(
        UpdateDeviceProfileRequest $request,
        DeviceProfile $device_profile,
        DeviceProfileService $service
    ): JsonResponse {
        $query = DeviceProfile::query()->whereKey($device_profile->device_profile_uuid);
        $this->applyScope($query, $request->input('scope'));
        $profile = $query->first();

        if (! $profile) {
            return $this->accessDenied();
        }

        $data = $this->prepareSaveData($request->validated(), $profile);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $service->save($data, $profile, $this->childPermissions());

            return response()->json([
                'messages' => ['success' => [__('Device profile updated successfully.')]],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'messages' => ['error' => [__('Failed to update the device profile.')]],
            ], 500);
        }
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('device_profile_view')) {
            return $this->accessDenied();
        }

        $items = $this->profilesQuery($request)
            ->select(['device_profile_uuid'])
            ->defaultSort('device_profile_name')
            ->pluck('device_profile_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => [__('All matching device profiles selected.')]],
        ]);
    }

    public function bulkCopy(Request $request, DeviceProfileService $service): JsonResponse
    {
        if (! userCheckPermission('device_profile_add')) {
            return $this->accessDenied();
        }

        $profiles = $this->selectedProfiles($request, true);
        if ($profiles->isEmpty()) {
            return $this->nothingSelected();
        }

        try {
            $count = $service->copy($profiles);

            return response()->json([
                'messages' => ['success' => [__('Copied :count device profile(s).', ['count' => $count])]],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'messages' => ['error' => [__('Failed to copy the selected device profiles.')]],
            ], 500);
        }
    }

    public function bulkToggle(Request $request, DeviceProfileService $service): JsonResponse
    {
        if (! userCheckPermission('device_profile_edit')) {
            return $this->accessDenied();
        }

        $profiles = $this->selectedProfiles($request);
        if ($profiles->isEmpty()) {
            return $this->nothingSelected();
        }

        try {
            $count = $service->toggle($profiles);

            return response()->json([
                'messages' => ['success' => [__('Updated :count device profile(s).', ['count' => $count])]],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'messages' => ['error' => [__('Failed to update the selected device profiles.')]],
            ], 500);
        }
    }

    public function bulkDelete(Request $request, DeviceProfileService $service): JsonResponse
    {
        if (! userCheckPermission('device_profile_delete')) {
            return $this->accessDenied();
        }

        $profiles = $this->selectedProfiles($request);
        if ($profiles->isEmpty()) {
            return $this->nothingSelected();
        }

        try {
            $count = $service->delete($profiles);

            return response()->json([
                'messages' => ['success' => [__('Deleted :count device profile(s).', ['count' => $count])]],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'messages' => ['error' => [__('Failed to delete the selected device profiles.')]],
            ], 500);
        }
    }

    private function profilesQuery(Request $request): QueryBuilder
    {
        $fields = (string) $request->input('filter.fields', 'profile');
        $query = DeviceProfile::query();

        $this->applyScope($query, $request->input('filter.scope'));

        return QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::callback('search', function (Builder $query, $value) use ($fields) {
                    $search = trim((string) $value);
                    if ($search === '') {
                        return;
                    }

                    $query->where(function (Builder $query) use ($search, $fields) {
                        $query->where('device_profile_name', 'ilike', "%{$search}%")
                            ->orWhere('device_profile_description', 'ilike', "%{$search}%");

                        if (in_array($fields, ['keys', 'all'], true)) {
                            $query->orWhereHas('keys', function (Builder $query) use ($search) {
                                $query->where('profile_key_value', 'ilike', "%{$search}%")
                                    ->orWhere('profile_key_label', 'ilike', "%{$search}%");
                            });
                        }

                        if (in_array($fields, ['settings', 'all'], true)) {
                            $query->orWhereHas('settings', function (Builder $query) use ($search) {
                                $query->where('profile_setting_name', 'ilike', "%{$search}%")
                                    ->orWhere('profile_setting_value', 'ilike', "%{$search}%")
                                    ->orWhere('profile_setting_description', 'ilike', "%{$search}%");
                            });
                        }
                    });
                }),
                AllowedFilter::callback('fields', fn () => null),
                AllowedFilter::callback('scope', fn () => null),
            ]);
    }

    private function selectedProfiles(Request $request, bool $withChildren = false)
    {
        $uuids = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['uuid'],
            'scope' => ['nullable', 'in:current,all'],
        ])['items'];

        $query = DeviceProfile::query()
            ->whereIn('device_profile_uuid', $uuids);

        $this->applyScope($query, $request->input('scope'));

        if ($withChildren) {
            $query->with(['keys', 'settings']);
        }

        return $query->get();
    }

    private function applyScope(Builder $query, mixed $scope): void
    {
        if ($scope === 'all' && userCheckPermission('device_profile_all')) {
            return;
        }

        $query->where(function (Builder $query) {
            $query->where('domain_uuid', session('domain_uuid'))
                ->orWhereNull('domain_uuid');
        });
    }

    private function prepareSaveData(array $data, ?DeviceProfile $profile = null): array|JsonResponse
    {
        $restrictedKeyFields = [
            'device_key_extension' => 'profile_key_extension',
            'device_key_protected' => 'profile_key_protected',
            'device_key_icon' => 'profile_key_icon',
        ];

        foreach ($restrictedKeyFields as $permission => $field) {
            if (! userCheckPermission($permission)) {
                foreach ($data['keys'] ?? [] as $index => $key) {
                    unset($data['keys'][$index][$field]);
                }
            }
        }

        if (! userCheckPermission('device_profile_domain')) {
            $data['domain_uuid'] = $profile?->domain_uuid ?? session('domain_uuid');

            return $data;
        }

        $domainUuid = $data['domain_uuid'] ?? null;
        if ($domainUuid && ! $this->canAccessDomain($domainUuid)) {
            return response()->json([
                'messages' => ['error' => [__('Account access denied.')]],
            ], 403);
        }

        return $data;
    }

    private function formPermissions(): array
    {
        return [
            'manage_domain' => userCheckPermission('device_profile_domain'),
            'keys' => $this->childPermissions()['keys'],
            'settings' => $this->childPermissions()['settings'],
            'key_fields' => [
                'extension' => userCheckPermission('device_key_extension'),
                'icon' => userCheckPermission('device_key_icon'),
            ],
        ];
    }

    private function childPermissions(): array
    {
        return [
            'keys' => [
                'view' => userCheckPermission('device_profile_key_view'),
                'create' => userCheckPermission('device_profile_key_add'),
                'update' => userCheckPermission('device_profile_key_edit'),
                'destroy' => userCheckPermission('device_profile_key_delete'),
            ],
            'settings' => [
                'view' => userCheckPermission('device_profile_setting_view')
                    || userCheckPermission('device_profile_setting_edit'),
                'create' => userCheckPermission('device_profile_setting_add'),
                'update' => userCheckPermission('device_profile_setting_edit'),
                'destroy' => userCheckPermission('device_profile_setting_delete'),
            ],
        ];
    }

    private function domainOptions(DeviceProfile $profile): array
    {
        $domains = collect(session('domains', []))
            ->map(fn ($domain) => [
                'value' => data_get($domain, 'domain_uuid'),
                'label' => data_get($domain, 'domain_description')
                    ?: data_get($domain, 'domain_name'),
            ])
            ->filter(fn ($domain) => $domain['value'] && $domain['label']);

        if ($profile->domain_uuid && ! $domains->contains('value', $profile->domain_uuid)) {
            $domains->push([
                'value' => $profile->domain_uuid,
                'label' => $profile->domain?->domain_description
                    ?: $profile->domain?->domain_name
                    ?: __('Account'),
            ]);
        }

        $domains->prepend([
            'value' => '__global__',
            'label' => __('Global'),
        ]);

        return $domains->unique('value')->values()->all();
    }

    private function vendorOptions(): array
    {
        return DB::table('v_device_vendors')
            ->where('enabled', 'true')
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->map(fn ($vendor) => [
                'value' => $vendor,
                'label' => ucwords((string) $vendor),
            ])
            ->values()
            ->all();
    }

    /**
     * Caller ID names keyed by extension, used to hint the key label the phone
     * will show when the operator leaves it blank. Global profiles fall back to
     * the account currently in session, the same way the legacy page did.
     */
    private function extensionNames(DeviceProfile $profile): array
    {
        $domainUuid = $profile->domain_uuid ?: session('domain_uuid');

        if (! $domainUuid) {
            return [];
        }

        return DB::table('v_extensions')
            ->where('domain_uuid', $domainUuid)
            ->whereNotNull('extension')
            ->pluck('effective_caller_id_name', 'extension')
            ->filter()
            ->all();
    }

    private function vendorFunctions(): array
    {
        return DB::table('v_device_vendors as vendors')
            ->join(
                'v_device_vendor_functions as functions',
                'vendors.device_vendor_uuid',
                '=',
                'functions.device_vendor_uuid'
            )
            ->where('vendors.enabled', 'true')
            ->where('functions.enabled', 'true')
            ->orderBy('vendors.name')
            ->orderBy('functions.type')
            ->get([
                'vendors.name as vendor',
                'functions.type',
                'functions.subtype',
                'functions.value',
            ])
            ->map(fn ($function) => [
                'vendor' => $function->vendor,
                'type' => $function->type,
                'subtype' => $function->subtype,
                'value' => $function->value,
            ])
            ->all();
    }

    private function canAccessDomain(string $domainUuid): bool
    {
        if (userCheckPermission('domain_all')) {
            return true;
        }

        return collect(session('domains', []))
            ->contains(fn ($domain) => data_get($domain, 'domain_uuid') === $domainUuid);
    }

    private function keyCategoryOrder(): string
    {
        return "case profile_key_category
            when 'line' then 1
            when 'memory' then 2
            when 'programmable' then 3
            when 'expansion' then 4
            when 'expansion-1' then 5
            when 'expansion-2' then 6
            when 'expansion-3' then 7
            when 'expansion-4' then 8
            when 'expansion-5' then 9
            when 'expansion-6' then 10
            else 11
        end";
    }

    private function accessDenied(): JsonResponse
    {
        return response()->json([
            'messages' => ['error' => [__('Access denied.')]],
        ], 403);
    }

    private function nothingSelected(): JsonResponse
    {
        return response()->json([
            'messages' => ['error' => [__('No device profiles selected.')]],
        ], 422);
    }
}
