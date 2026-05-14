<?php

namespace App\Http\Controllers;

use App\Models\SwitchModule;
use App\Services\SwitchModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SwitchModuleController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (! userCheckPermission('module_view')) {
            return redirect('/');
        }

        return Inertia::render('Modules', [
            'routes' => [
                'current_page' => route('modules.index'),
                'data_route' => route('modules.data'),
                'select_all' => route('modules.select.all'),
                'bulk_start' => route('modules.bulk.start'),
                'bulk_stop' => route('modules.bulk.stop'),
                'bulk_toggle' => route('modules.bulk.toggle'),
                'bulk_delete' => route('modules.bulk.delete'),
                'legacy_add' => '/app/modules/module_edit.php',
                'legacy_edit' => '/app/modules/module_edit.php?id=__MODULE__',
            ],
            'permissions' => [
                'create' => userCheckPermission('module_add'),
                'update' => userCheckPermission('module_edit'),
                'destroy' => userCheckPermission('module_delete'),
            ],
        ]);
    }

    public function getData(Request $request, SwitchModuleService $service): JsonResponse
    {
        if (! userCheckPermission('module_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $service->syncFromDisk();
        $service->writeXml();

        $activeNames = $service->activeModuleNames();
        $eventSocketAvailable = $activeNames->isNotEmpty() || $service->eventSocketIsAvailable();

        $modules = $this->moduleQuery($request)
            ->allowedSorts([
                'module_category',
                'module_label',
                'module_name',
                'module_enabled',
            ])
            ->defaultSort('module_category', 'module_label')
            ->paginate($this->perPage)
            ->appends($request->query())
            ->through(fn (SwitchModule $module) => $this->serializeModule($module, $activeNames, $eventSocketAvailable));

        return response()
            ->json($modules)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('module_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return response()->json([
            'items' => $this->moduleQuery($request)
                ->defaultSort('module_category', 'module_label')
                ->pluck('module_uuid'),
            'messages' => ['success' => ['All matching modules selected.']],
        ]);
    }

    public function bulkStart(Request $request, SwitchModuleService $service): JsonResponse
    {
        return $this->control($request, $service, 'start');
    }

    public function bulkStop(Request $request, SwitchModuleService $service): JsonResponse
    {
        return $this->control($request, $service, 'stop');
    }

    public function bulkToggle(Request $request, SwitchModuleService $service): JsonResponse
    {
        if (! userCheckPermission('module_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $modules = $this->selectedModules($request);

        if ($modules->isEmpty()) {
            return response()->json(['messages' => ['error' => ['No modules selected.']]], 422);
        }

        return response()->json($service->toggle($modules));
    }

    public function bulkDelete(Request $request, SwitchModuleService $service): JsonResponse
    {
        if (! userCheckPermission('module_delete')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $modules = $this->selectedModules($request);

        if ($modules->isEmpty()) {
            return response()->json(['messages' => ['error' => ['No modules selected.']]], 422);
        }

        return response()->json($service->delete($modules));
    }

    private function control(Request $request, SwitchModuleService $service, string $action): JsonResponse
    {
        if (! userCheckPermission('module_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $modules = $this->selectedModules($request);

        if ($modules->isEmpty()) {
            return response()->json(['messages' => ['error' => ['No modules selected.']]], 422);
        }

        $result = $service->control($modules, $action);

        return response()->json($result, $result['success'] ? 200 : 409);
    }

    private function moduleQuery(Request $request): QueryBuilder
    {
        return QueryBuilder::for(SwitchModule::class)
            ->select([
                'module_uuid',
                'module_label',
                'module_name',
                'module_category',
                'module_order',
                'module_enabled',
                'module_default_enabled',
                'module_description',
            ])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('module_label', 'ilike', "%{$needle}%")
                            ->orWhere('module_name', 'ilike', "%{$needle}%")
                            ->orWhere('module_category', 'ilike', "%{$needle}%")
                            ->orWhere('module_description', 'ilike', "%{$needle}%");
                    });
                }),
                AllowedFilter::exact('module_category'),
                AllowedFilter::callback('module_enabled', function ($query, $value) {
                    $values = collect(is_array($value) ? $value : [$value])
                        ->map(function ($item) {
                            return match (true) {
                                $item === true || $item === 1 || $item === '1' => 'true',
                                $item === false || $item === 0 || $item === '0' => 'false',
                                default => strtolower((string) $item),
                            };
                        })
                        ->filter(fn ($item) => in_array($item, ['true', 'false'], true))
                        ->values();

                    if ($values->isEmpty()) {
                        return;
                    }

                    $query->whereIn('module_enabled', $values->all());
                }),
            ]);
    }

    private function selectedModules(Request $request)
    {
        $uuids = collect($request->input('items', []))
            ->filter(fn ($uuid) => is_string($uuid) && \Illuminate\Support\Str::isUuid($uuid))
            ->values();

        if ($uuids->isEmpty()) {
            return collect();
        }

        return SwitchModule::query()
            ->whereIn('module_uuid', $uuids)
            ->get();
    }

    private function serializeModule(SwitchModule $module, $activeNames, bool $eventSocketAvailable): array
    {
        $running = $activeNames->contains($module->module_name);

        return [
            'module_uuid' => $module->module_uuid,
            'module_label' => $module->module_label,
            'module_name' => $module->module_name,
            'module_category' => $module->module_category ?: 'Uncategorized',
            'module_order' => $module->module_order,
            'module_enabled' => $module->module_enabled,
            'module_default_enabled' => $module->module_default_enabled,
            'module_description' => $module->module_description,
            'status' => $eventSocketAvailable ? ($running ? 'running' : 'stopped') : 'unknown',
            'can_control_runtime' => $eventSocketAvailable && $module->module_enabled === 'true',
            'edit_url' => '/app/modules/module_edit.php?id=' . urlencode($module->module_uuid),
        ];
    }
}
