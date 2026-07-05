<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceKeyTemplateRequest;
use App\Http\Requests\UpdateDeviceKeyTemplateRequest;
use App\Models\DeviceKeyTemplate;
use App\Models\Devices;
use App\Models\Domain;
use App\Services\DeviceKeyTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DeviceKeyTemplateController extends Controller
{
    public function index()
    {
        if (! userCheckPermission('device_key_template_view')) {
            return redirect('/');
        }

        return Inertia::render('DeviceKeyTemplates', [
            'pagination' => [
                'per_page' => fspbx_pagination_per_page(),
                'per_page_options' => fspbx_pagination_options(),
            ],
            'routes' => [
                'current_page' => route('device-key-templates.index'),
                'data_route' => route('device-key-templates.data'),
                'select_all' => route('device-key-templates.select.all'),
                'bulk_delete' => route('device-key-templates.bulk.delete'),
                'store' => route('device-key-templates.store'),
                'item_options' => route('device-key-templates.item.options'),
                'get_routing_options' => route('routing.options'),
                'duplicate' => route('device-key-templates.duplicate'),
                'copy_to_domain' => route('device-key-templates.copy-to-domain'),
                'devices' => route('devices.index'),
            ],
            'permissions' => [
                'create' => userCheckPermission('device_key_template_create'),
                'update' => userCheckPermission('device_key_template_update'),
                'destroy' => userCheckPermission('device_key_template_delete'),
                'copy_to_domain' => userCheckPermission('device_key_template_create') && userCheckPermission('domain_select'),
            ],
            'options' => [
                'domains' => $this->domainOptions(),
            ],
        ]);
    }

    public function getData(Request $request)
    {
        if (! userCheckPermission('device_key_template_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        return $this->scopedTemplates($request)
            ->select([
                'device_key_template_uuid',
                'domain_uuid',
                'name',
                'description',
                'enabled',
                'updated_at',
            ])
            ->withCount('keys')
            ->allowedSorts(['name', 'enabled', 'updated_at'])
            ->defaultSort('name')
            ->paginate(fspbx_pagination_per_page($request));
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('device_key_template_update')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (! $itemUuid && ! userCheckPermission('device_key_template_create')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($itemUuid) {
            $item = DeviceKeyTemplate::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->with(['keys' => function ($query) {
                    $query->select([
                        'device_key_template_key_uuid',
                        'device_key_template_uuid',
                        'key_area',
                        'key_index',
                        'key_type',
                        'key_value',
                        'key_label',
                    ])->orderBy('key_area')->orderBy('key_index');
                }])
                ->whereKey($itemUuid)
                ->firstOrFail();
        } else {
            $item = new DeviceKeyTemplate([
                'enabled' => 'true',
            ]);
            $item->setRelation('keys', collect());
        }

        return response()->json([
            'item' => $item,
            'extensions' => $this->extensionOptions(),
            'permissions' => [
                'is_superadmin' => isSuperAdmin(),
            ],
            'routes' => [
                'store_route' => route('device-key-templates.store'),
                'update_route' => $itemUuid
                    ? route('device-key-templates.update', ['device_key_template' => $item->device_key_template_uuid])
                    : null,
                'get_routing_options' => route('routing.options'),
            ],
        ]);
    }

    public function store(StoreDeviceKeyTemplateRequest $request, DeviceKeyTemplateService $service): JsonResponse
    {
        try {
            $template = $service->save($request->validated());

            return response()->json([
                'messages' => ['success' => ['Device key template created successfully.']],
                'device_key_template_uuid' => $template->device_key_template_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('DeviceKeyTemplateController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create device key template.']],
            ], 500);
        }
    }

    public function update(UpdateDeviceKeyTemplateRequest $request, DeviceKeyTemplate $device_key_template, DeviceKeyTemplateService $service): JsonResponse
    {
        if ($device_key_template->domain_uuid !== session('domain_uuid')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $service->save($request->validated(), $device_key_template);

            return response()->json([
                'messages' => ['success' => ['Device key template updated successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('DeviceKeyTemplateController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update device key template.']],
            ], 500);
        }
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('device_key_template_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->scopedTemplates($request)
            ->select(['device_key_template_uuid'])
            ->defaultSort('name')
            ->pluck('device_key_template_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching device key templates selected.']],
        ]);
    }

    public function duplicate(Request $request, DeviceKeyTemplateService $service): JsonResponse
    {
        $data = $request->validate([
            'uuid' => ['required', 'uuid', 'exists:device_key_templates,device_key_template_uuid'],
        ]);

        if (! userCheckPermission('device_key_template_create')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $template = DeviceKeyTemplate::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->with('keys')
                ->whereKey($data['uuid'])
                ->firstOrFail();

            $copy = $service->duplicate($template);

            return response()->json([
                'messages' => ['success' => ['Device key template duplicated successfully.']],
                'device_key_template_uuid' => $copy->device_key_template_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('DeviceKeyTemplateController@duplicate error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to duplicate device key template.']],
            ], 500);
        }
    }

    public function copyToDomain(Request $request, DeviceKeyTemplateService $service): JsonResponse
    {
        if (! userCheckPermission('device_key_template_create') || ! userCheckPermission('domain_select')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $data = $request->validate([
            'uuid' => ['required', 'uuid', 'exists:device_key_templates,device_key_template_uuid'],
            'target_domain_uuid' => ['required', 'uuid', 'exists:v_domains,domain_uuid'],
        ]);

        if ($data['target_domain_uuid'] === session('domain_uuid')) {
            return response()->json([
                'messages' => ['error' => ['Choose a different target domain.']],
            ], 422);
        }

        if (! $this->canAccessDomain($data['target_domain_uuid'])) {
            return response()->json([
                'messages' => ['error' => ['Domain access denied.']],
            ], 403);
        }

        try {
            $template = DeviceKeyTemplate::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->with('keys')
                ->whereKey($data['uuid'])
                ->first();

            if (! $template) {
                return response()->json([
                    'messages' => ['error' => ['Device key template was not found.']],
                ], 404);
            }

            $copy = $service->duplicate($template, $data['target_domain_uuid']);
            $targetDomain = Domain::query()->findOrFail($data['target_domain_uuid']);
            $targetDomainLabel = $targetDomain->domain_description ?: $targetDomain->domain_name;

            return response()->json([
                'messages' => ['success' => ["Device key template copied to {$targetDomainLabel}."]],
                'device_key_template_uuid' => $copy->device_key_template_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('DeviceKeyTemplateController@copyToDomain error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to copy device key template.']],
            ], 500);
        }
    }

    public function bulkDelete(Request $request, DeviceKeyTemplateService $service): JsonResponse
    {
        if (! userCheckPermission('device_key_template_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['uuid'],
        ])['items'];

        $items = DeviceKeyTemplate::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('device_key_template_uuid', $uuids)
            ->get();

        $deleted = $service->delete($items);

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} device key template(s)."]],
        ]);
    }

    public function storeFromDevice(Request $request, Devices $device, DeviceKeyTemplateService $service): JsonResponse
    {
        if (! userCheckPermission('device_key_template_create')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($device->domain_uuid !== session('domain_uuid')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $device->load(['keys' => function ($query) {
            $query->select([
                'device_key_uuid',
                'device_uuid',
                'key_area',
                'key_index',
                'key_type',
                'key_value',
                'key_label',
            ])->orderBy('key_area')->orderBy('key_index');
        }]);

        if ($device->keys->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['This device does not have keys to save as a template.']],
            ], 422);
        }

        $template = $service->createFromDeviceKeys($data['name'], $data['description'] ?? null, $device->keys);

        return response()->json([
            'messages' => ['success' => ['Device key template created successfully.']],
            'device_key_template_uuid' => $template->device_key_template_uuid,
        ], 201);
    }

    private function scopedTemplates(Request $request): QueryBuilder
    {
        return QueryBuilder::for(DeviceKeyTemplate::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('name', 'ilike', "%{$needle}%")
                            ->orWhere('description', 'ilike', "%{$needle}%")
                            ->orWhereHas('keys', function ($keyQuery) use ($needle) {
                                $keyQuery->where('key_value', 'ilike', "%{$needle}%")
                                    ->orWhere('key_label', 'ilike', "%{$needle}%");
                            });
                    });
                }),
            ]);
    }

    private function extensionOptions(): array
    {
        return \App\Models\Extensions::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('extension')
            ->get(['extension_uuid', 'extension', 'effective_caller_id_name'])
            ->map(fn ($extension) => [
                'value' => $extension->extension,
                'extension' => $extension->extension,
                'name' => $extension->name_formatted,
            ])
            ->all();
    }

    private function domainOptions(): array
    {
        return collect(session('domains', []))
            ->map(fn ($domain) => [
                'value' => data_get($domain, 'domain_uuid'),
                'label' => $this->domainOptionLabel($domain),
            ])
            ->filter(fn ($domain) => $domain['value']
                && $domain['label']
                && $domain['value'] !== session('domain_uuid'))
            ->values()
            ->all();
    }

    private function domainOptionLabel(mixed $domain): string
    {
        $name = (string) data_get($domain, 'domain_name', '');
        $description = (string) data_get($domain, 'domain_description', '');

        return $description ?: $name;
    }

    private function canAccessDomain(string $domainUuid): bool
    {
        if (userCheckPermission('domain_all')) {
            return true;
        }

        return collect(session('domains', []))
            ->contains(fn ($domain) => data_get($domain, 'domain_uuid') === $domainUuid);
    }
}
