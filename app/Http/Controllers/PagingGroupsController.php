<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePagingGroupRequest;
use App\Http\Requests\UpdatePagingGroupRequest;
use App\Models\Extensions;
use App\Models\PagingGroup;
use App\Models\Recordings;
use App\Services\PagingGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PagingGroupsController extends Controller
{
    public function __construct(private PagingGroupService $groups)
    {
    }

    public function index(Request $request)
    {
        if (!userCheckPermission('paging_groups_list_view')) {
            return redirect('/');
        }

        return Inertia::render('PagingGroups', [
            'routes' => [
                'data_route' => route('paging-groups.data'),
                'item_options' => route('paging-groups.item.options'),
                'select_all' => route('paging-groups.select.all'),
                'bulk_delete' => route('paging-groups.bulk.delete'),
                'bulk_toggle' => route('paging-groups.bulk.toggle'),
            ],
            'permissions' => [
                'create' => userCheckPermission('paging_groups_create'),
                'update' => userCheckPermission('paging_groups_update'),
                'destroy' => userCheckPermission('paging_groups_delete'),
            ],
        ]);
    }

    public function getData(Request $request)
    {
        abort_unless(userCheckPermission('paging_groups_list_view'), 403);

        $items = $this->query()
            ->with([
                'members' => fn ($query) => $query->where('v_extensions.domain_uuid', session('domain_uuid'))->orderBy('extension'),
                'allowedCallers' => fn ($query) => $query->where('v_extensions.domain_uuid', session('domain_uuid'))->orderBy('extension'),
            ])
            ->allowedSorts(['name', 'extension', 'enabled', 'description'])
            ->defaultSort('extension')
            ->paginate(fspbx_pagination_per_page($request));

        $items->getCollection()->transform(fn (PagingGroup $group) => [
            'uuid' => $group->uuid,
            'name' => $group->name,
            'extension' => $group->extension,
            'busy_mode' => $group->busy_mode,
            'mode' => $group->mode,
            'intro_beep' => $group->intro_beep,
            'intro_recording' => $group->intro_recording,
            'enabled' => $group->enabled,
            'members' => $group->members->pluck('extension')->values(),
            'allowed_callers' => $group->allowedCallers->pluck('extension')->values(),
            'description' => $group->description,
        ]);

        return $items;
    }

    public function selectAll(Request $request): JsonResponse
    {
        abort_unless(userCheckPermission('paging_groups_list_view'), 403);

        return response()->json([
            'items' => $this->query()->pluck('uuid'),
            'messages' => ['success' => [__('All matching paging groups selected.')]],
        ]);
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('item_uuid');

        if (!userCheckPermission($itemUuid ? 'paging_groups_update' : 'paging_groups_create')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        if ($itemUuid) {
            $group = PagingGroup::query()->where('domain_uuid', session('domain_uuid'))->findOrFail($itemUuid);
            $item = [
                'uuid' => $group->uuid,
                'name' => $group->name,
                'extension' => $group->extension,
                'busy_mode' => $group->busy_mode,
                'mode' => $group->mode,
                'intro_beep' => $group->intro_beep,
                'intro_recording' => $group->intro_recording,
                'enabled' => $group->enabled,
                'members' => $group->members()->where('v_extensions.domain_uuid', session('domain_uuid'))->pluck('v_extensions.extension_uuid'),
                'allowed_callers' => $group->allowedCallers()->where('v_extensions.domain_uuid', session('domain_uuid'))->pluck('v_extensions.extension_uuid'),
                'description' => $group->description,
            ];
        } else {
            $item = [
                'uuid' => null,
                'busy_mode' => PagingGroup::BUSY_SKIP,
                'mode' => PagingGroup::MODE_LIVE,
                'intro_beep' => true,
                'intro_recording' => null,
                'enabled' => true,
                'members' => [],
                'allowed_callers' => [],
            ];
        }

        $extensions = Extensions::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('extension')
            ->get(['extension_uuid', 'extension', 'effective_caller_id_name'])
            ->map(fn ($extension) => [
                'value' => $extension->extension_uuid,
                'label' => $extension->name_formatted,
            ]);

        $recordings = Recordings::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('recording_name')
            ->get(['recording_filename', 'recording_name'])
            ->map(fn ($recording) => [
                'value' => $recording->recording_filename,
                'label' => $recording->recording_name ?: $recording->recording_filename,
            ]);

        return response()->json([
            'item' => $item,
            'extension_options' => $extensions,
            'recording_options' => $recordings,
            'routes' => [
                'store_route' => route('paging-groups.store'),
                'update_route' => $itemUuid ? route('paging-groups.update', ['paging_group' => $itemUuid]) : null,
            ],
        ]);
    }

    public function store(StorePagingGroupRequest $request): JsonResponse
    {
        try {
            $this->groups->save($request->validated(), null, session('domain_uuid'), session('domain_name'));
        } catch (\Throwable $e) {
            logger('PagingGroupsController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json(['messages' => ['error' => [__('Failed to create paging group.')]]], 500);
        }

        return response()->json(['messages' => ['success' => [__('Paging group created successfully.')]]], 201);
    }

    public function update(UpdatePagingGroupRequest $request, string $paging_group): JsonResponse
    {
        $group = PagingGroup::query()->where('domain_uuid', session('domain_uuid'))->findOrFail($paging_group);

        try {
            $this->groups->save($request->validated(), $group, session('domain_uuid'), session('domain_name'));
        } catch (\Throwable $e) {
            logger('PagingGroupsController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json(['messages' => ['error' => [__('Failed to update paging group.')]]], 500);
        }

        return response()->json(['messages' => ['success' => [__('Paging group updated successfully.')]]]);
    }

    public function bulkToggle(Request $request): JsonResponse
    {
        if (!userCheckPermission('paging_groups_update')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['uuid'],
        ]);

        $groups = PagingGroup::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('uuid', $validated['items'])
            ->get();

        foreach ($groups as $group) {
            $group->enabled = !$group->enabled;
            $group->save();
            $this->groups->writeDialplan($group, session('domain_name'));
        }

        return response()->json(['messages' => ['success' => [__('Paging group updated successfully.')]]]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        if (!userCheckPermission('paging_groups_delete')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['uuid'],
        ]);

        $groups = PagingGroup::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('uuid', $validated['items'])
            ->get();

        foreach ($groups as $group) {
            $this->groups->delete($group, session('domain_name'));
        }

        return response()->json([
            'messages' => ['success' => [__('Deleted :count paging group(s).', ['count' => $groups->count()])]],
        ]);
    }

    private function query(): QueryBuilder
    {
        return QueryBuilder::for(PagingGroup::class)
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
