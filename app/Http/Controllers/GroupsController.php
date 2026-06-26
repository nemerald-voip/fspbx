<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Domain;
use App\Models\Groups;
use App\Models\GroupPermissions;
use App\Models\UserGroup;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use App\Services\Auth\UserSessionInvalidationService;
use App\Http\Requests\CreatePermissionGroupRequest;
use App\Http\Requests\UpdatePermissionGroupRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class GroupsController extends Controller
{

    public $model;
    protected $viewName = 'Groups';

    public function __construct()
    {
        $this->model = new Groups();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Check permissions
        if (!userCheckPermission("group_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'pagination' => [
                    'per_page' => fspbx_pagination_per_page(),
                    'per_page_options' => fspbx_pagination_options(),
                ],

                'routes' => [
                    'current_page' => route('groups.index'),
                    'data_route' => route('groups.data'),
                    'item_options' => route('groups.item.options'),
                    'bulk_delete' => route('groups.bulk.delete'),
                    'clone' => route('groups.clone'),
                    'select_all' => route('groups.select.all'),
                    'members_data' => route('groups.members.data', ['group' => '__group_uuid__']),
                    'members_store' => route('groups.members.store', ['group' => '__group_uuid__']),
                    'members_delete' => route('groups.members.delete', ['group' => '__group_uuid__']),
                ],
                'permissions' => [
                    'create' => userCheckPermission('group_add'),
                    'update' => userCheckPermission('group_edit'),
                    'destroy' => userCheckPermission('group_delete'),
                    'members' => userCheckPermission('group_member_view'),
                    'domain_groups_view' => userCheckPermission('domain_groups_list_view'),
                ],
            ]
        );
    }

    public function permissionsIndex(Groups $group)
    {
        if (!userCheckPermission('group_permission_view')) {
            return redirect('/');
        }

        if (!$this->canAccessGroup($group)) {
            abort(404);
        }

        return Inertia::render('GroupPermissions', [
            'group' => [
                'group_uuid' => $group->group_uuid,
                'group_name' => $group->group_name,
                'group_level' => $group->group_level,
                'group_description' => $group->group_description,
            ],
            'routes' => [
                'groups' => route('groups.index'),
                'data_route' => route('groups.permissions.data', ['group' => $group]),
                'toggle' => route('groups.permissions.toggle', ['group' => $group]),
                'reload' => route('groups.permissions.reload', ['group' => $group]),
                'members_data' => route('groups.members.data', ['group' => '__group_uuid__']),
                'members_store' => route('groups.members.store', ['group' => '__group_uuid__']),
                'members_delete' => route('groups.members.delete', ['group' => '__group_uuid__']),
            ],
            'permissions' => [
                'assign' => userCheckPermission('group_permission_add') || userCheckPermission('group_permission_edit'),
                'remove' => userCheckPermission('group_permission_delete') || userCheckPermission('group_permission_edit'),
                'reload' => userCheckPermission('group_permission_view'),
                'members' => userCheckPermission('group_member_view'),
            ],
        ]);
    }

    public function permissionsData(Groups $group): JsonResponse
    {
        if (!userCheckPermission('group_permission_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (!$this->canAccessGroup($group)) {
            return response()->json(['messages' => ['error' => ['Group not found.']]], 404);
        }

        $rows = DB::table('v_permissions as permissions')
            ->leftJoin('v_group_permissions as group_permissions', function ($join) use ($group) {
                $join->on('permissions.permission_name', '=', 'group_permissions.permission_name')
                    ->where('group_permissions.group_uuid', '=', $group->group_uuid);
            })
            ->select([
                'permissions.permission_uuid',
                'permissions.application_name',
                'permissions.permission_name',
                'group_permissions.group_permission_uuid',
                'group_permissions.permission_assigned',
            ])
            ->distinct()
            ->orderBy('permissions.application_name')
            ->orderBy('permissions.permission_name')
            ->get()
            ->groupBy('permission_name')
            ->map(function ($permissionRows) {
                $row = $permissionRows->first();
                $assignedRow = $permissionRows->firstWhere('permission_assigned', 'true');

                return [
                    'permission_uuid' => $row->permission_uuid,
                    'application_name' => $row->application_name ?: 'Uncategorized',
                    'permission_name' => $row->permission_name,
                    'group_permission_uuid' => $assignedRow?->group_permission_uuid ?? $row->group_permission_uuid,
                    'assigned' => $assignedRow !== null,
                ];
            })
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function togglePermissionAssignments(Request $request, Groups $group): JsonResponse
    {
        if (!$this->canAccessGroup($group)) {
            return response()->json(['messages' => ['error' => ['Group not found.']]], 404);
        }

        $assigned = $request->boolean('assigned');

        if ($assigned && !userCheckPermission('group_permission_add') && !userCheckPermission('group_permission_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (!$assigned && !userCheckPermission('group_permission_delete') && !userCheckPermission('group_permission_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $permissionNames = collect($request->input('items', []))
            ->filter(fn ($permissionName) => is_string($permissionName) && $permissionName !== '')
            ->unique()
            ->values();

        if ($permissionNames->isEmpty()) {
            return response()->json(['messages' => ['error' => ['Select at least one permission.']]], 422);
        }

        $validPermissionNames = DB::table('v_permissions')
            ->whereIn('permission_name', $permissionNames)
            ->pluck('permission_name');

        if ($validPermissionNames->count() !== $permissionNames->count()) {
            return response()->json(['messages' => ['error' => ['One or more permissions are invalid.']]], 422);
        }

        $affectedUserUuids = UserGroup::query()
            ->where('group_uuid', $group->group_uuid)
            ->when($group->domain_uuid, fn ($query) => $query->where('domain_uuid', $group->domain_uuid))
            ->pluck('user_uuid');

        DB::transaction(function () use ($assigned, $group, $validPermissionNames) {
            if (!$assigned) {
                GroupPermissions::query()
                    ->where('group_uuid', $group->group_uuid)
                    ->whereIn('permission_name', $validPermissionNames)
                    ->delete();

                return;
            }

            $existingPermissions = GroupPermissions::query()
                ->where('group_uuid', $group->group_uuid)
                ->whereIn('permission_name', $validPermissionNames)
                ->get()
                ->keyBy('permission_name');

            $now = date('Y-m-d H:i:s');
            $newRows = [];

            foreach ($validPermissionNames as $permissionName) {
                $existing = $existingPermissions->get($permissionName);

                if ($existing) {
                    $existing->forceFill([
                        'group_name' => $group->group_name,
                        'permission_assigned' => 'true',
                    ])->save();

                    continue;
                }

                $newRows[] = [
                    'group_permission_uuid' => (string) Str::uuid(),
                    'group_uuid' => $group->group_uuid,
                    'group_name' => $group->group_name,
                    'permission_name' => $permissionName,
                    'permission_protected' => 'false',
                    'permission_assigned' => 'true',
                    'insert_date' => $now,
                ];
            }

            if ($newRows !== []) {
                GroupPermissions::query()->insert($newRows);
            }
        });

        app(UserSessionInvalidationService::class)->invalidateByUserUuids($affectedUserUuids);

        $action = $assigned ? 'assigned' : 'removed';
        $count = $validPermissionNames->count();

        return response()->json(['messages' => ['success' => ["{$count} permission(s) {$action}."]]]);
    }

    public function reloadPermissionSession(Groups $group): JsonResponse
    {
        if (!userCheckPermission('group_permission_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (!$this->canAccessGroup($group)) {
            return response()->json(['messages' => ['error' => ['Group not found.']]], 404);
        }

        $userGroups = collect(session('user.groups', []));
        $groupUuids = $userGroups->pluck('group_uuid')->filter()->values();

        if ($groupUuids->isEmpty()) {
            session()->forget('permissions');
            unset($_SESSION['permissions'], $_SESSION['user']['permissions']);

            return response()->json(['messages' => ['success' => ['Permissions reloaded.']]]);
        }

        $permissions = DB::table('v_permissions')
            ->join('v_group_permissions', 'v_permissions.permission_name', '=', 'v_group_permissions.permission_name')
            ->whereIn('v_group_permissions.group_uuid', $groupUuids)
            ->where('v_group_permissions.permission_assigned', 'true')
            ->where(function ($query) {
                $query->where('v_group_permissions.domain_uuid', session('domain_uuid'))
                    ->orWhereNull('v_group_permissions.domain_uuid');
            })
            ->distinct()
            ->get([
                'v_permissions.permission_uuid',
                'v_permissions.permission_name',
            ]);

        session()->put('permissions', $permissions);
        unset($_SESSION['permissions'], $_SESSION['user']['permissions']);

        foreach ($permissions as $permission) {
            $_SESSION['permissions'][$permission->permission_name] = true;
            $_SESSION['user']['permissions'][$permission->permission_name] = true;
        }

        return response()->json(['messages' => ['success' => ['Permissions reloaded.']]]);
    }

    public function membersData(Groups $group): JsonResponse
    {
        if (!userCheckPermission('group_member_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (!$this->canAccessGroup($group)) {
            return response()->json(['messages' => ['error' => ['Group not found.']]], 404);
        }

        $members = $this->groupMembersQuery($group)
            ->get()
            ->map(fn ($member) => [
                'user_group_uuid' => $member->user_group_uuid,
                'user_uuid' => $member->user_uuid,
                'username' => $member->username,
                'user_email' => $member->user_email,
                'domain_uuid' => $member->domain_uuid,
                'domain_name' => $member->domain_name,
            ])
            ->values();

        return response()->json([
            'group' => [
                'group_uuid' => $group->group_uuid,
                'group_name' => $group->group_name,
                'group_description' => $group->group_description,
            ],
            'members' => $members,
            'available_users' => $this->availableUsersForGroup($group),
            'permissions' => [
                'add' => userCheckPermission('group_member_add'),
                'delete' => userCheckPermission('group_member_delete'),
                'show_domain' => userCheckPermission('user_all'),
            ],
        ]);
    }

    public function addMember(Request $request, Groups $group): JsonResponse
    {
        if (!userCheckPermission('group_member_add')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (!$this->canAccessGroup($group)) {
            return response()->json(['messages' => ['error' => ['Group not found.']]], 404);
        }

        $validated = $request->validate([
            'user_uuid' => ['required', 'uuid'],
        ]);

        $memberDomainUuid = $this->memberDomainUuid($group);
        $user = DB::table('v_users')
            ->where('user_uuid', $validated['user_uuid'])
            ->where('domain_uuid', $memberDomainUuid)
            ->first(['user_uuid']);

        if (!$user) {
            return response()->json(['messages' => ['error' => ['User not found.']]], 404);
        }

        $exists = UserGroup::query()
            ->where('group_uuid', $group->group_uuid)
            ->where('user_uuid', $validated['user_uuid'])
            ->where('domain_uuid', $memberDomainUuid)
            ->exists();

        if ($exists) {
            return response()->json(['messages' => ['error' => ['User is already a member.']]], 422);
        }

        UserGroup::create([
            'user_group_uuid' => (string) Str::uuid(),
            'domain_uuid' => $memberDomainUuid,
            'group_uuid' => $group->group_uuid,
            'group_name' => $group->group_name,
            'user_uuid' => $validated['user_uuid'],
        ]);

        app(UserSessionInvalidationService::class)->invalidateByUserUuids([$validated['user_uuid']]);

        return response()->json([
            'messages' => ['success' => ['Member added.']],
            'member_count' => $this->groupMemberCount($group),
        ], 201);
    }

    public function deleteMembers(Request $request, Groups $group): JsonResponse
    {
        if (!userCheckPermission('group_member_delete')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (!$this->canAccessGroup($group)) {
            return response()->json(['messages' => ['error' => ['Group not found.']]], 404);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'uuid'],
        ]);

        $memberQuery = UserGroup::query()
            ->where('group_uuid', $group->group_uuid)
            ->whereIn('user_group_uuid', $validated['items'])
            ->when($group->domain_uuid, fn ($query) => $query->where('domain_uuid', $group->domain_uuid))
            ->when(!userCheckPermission('user_all'), fn ($query) => $query->where('domain_uuid', session('domain_uuid')));

        $affectedUserUuids = (clone $memberQuery)->pluck('user_uuid');
        $deleted = $memberQuery->delete();

        if ($deleted > 0) {
            app(UserSessionInvalidationService::class)->invalidateByUserUuids($affectedUserUuids);
        }

        return response()->json([
            'messages' => ['success' => ["{$deleted} member(s) removed."]],
            'member_count' => $this->groupMemberCount($group),
        ]);
    }

    /**
     *  Get data
     */
    public function getData(Request $request)
    {
        return $this->scopedGroups($request)
            ->select([
                'group_uuid',
                'domain_uuid',
                'group_name',
                'group_level',
                'group_description',
            ])
            ->withCount([
                'permissions',
                'user_groups' => function ($query) {
                    if (!userCheckPermission('user_all')) {
                        $query->where('domain_uuid', session('domain_uuid'));
                    }
                },
            ])
            ->allowedSorts([
                'group_name',
                'group_level',
                'group_description',
                'permissions_count',
                'user_groups_count',
            ])
            ->defaultSort('group_name')
            ->paginate(fspbx_pagination_per_page($request));
    }

    private function scopedGroups(Request $request): QueryBuilder
    {
        return QueryBuilder::for(Groups::class, $request)
            ->where(function ($query) {
                $query->where('domain_uuid', session('domain_uuid'))
                    ->orWhereNull('domain_uuid');
            })
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('group_name', 'ilike', "%{$needle}%")
                            ->orWhere('group_description', 'ilike', "%{$needle}%");
                    });
                }),
            ]);
    }

    private function canAccessGroup(Groups $group): bool
    {
        return $group->domain_uuid === session('domain_uuid') || $group->domain_uuid === null;
    }


    public function getItemOptions()
    {
        try {

            $item_uuid = request('item_uuid'); // Retrieve item_uuid from the request

            // Check if item_uuid exists to find an existing model
            if ($item_uuid) {
                // Find existing item by item_uuid
                $item = $this->model::where($this->model->getKeyName(), $item_uuid)
                    ->first();

                // If a model exists, use it; otherwise, create a new one
                if (!$item) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }


                // Define the update route
                $updateRoute = route('groups.update', ['group' => $item_uuid]);
            } else {
                // Create a new model if item_uuid is not provided
                $item = $this->model;

                $storeRoute  = route('groups.store');
            }

            // $permissions = $this->getUserPermissions();

            $domains = Domain::where('domain_enabled', 'true')
                ->select('domain_uuid', 'domain_name', 'domain_description')
                ->orderBy('domain_description')
                ->get()
                ->map(function ($domain) {
                    return [
                        'value' => $domain->domain_uuid,
                        'label' => $domain->domain_description,
                    ];
                })
                ->prepend([
                    'value' => '',
                    'label' => 'Global',
                ])
                ->toArray();

            $group_levels = [];
            for ($i = 10; $i <= 70; $i += 10) {
                $group_levels[] = [
                    'value' => (string)$i,
                    'label' => (string)$i,
                ];
            }


            $routes = [
                'store_route' => $storeRoute ?? null,
                'update_route' => $updateRoute ?? null,
            ];

            // Construct the itemOptions object
            $itemOptions = [
                'item' => $item,
                'routes' => $routes,
                'domains' => $domains,
                'group_levels' => $group_levels,
                // Define options for other fields as needed
            ];
            // logger($itemOptions);

            return $itemOptions;
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to fetch item details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreatePermissionGroupRequest $request)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $groupManager = Groups::create(array_merge($validated, [
                'domain_uuid' => session('domain_uuid'),
                'group_uuid'  => Str::uuid(),
                'group_protected' => 'false',
            ]));

            DB::commit();

            return response()->json([
                'messages'   => ['success' => ['Group created']],
                'group_uuid' => $groupManager->group_uuid,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger(
                'GroupManager store error: '
                    . $e->getMessage()
                    . ' at ' . $e->getFile() . ':' . $e->getLine()
            );

            return response()->json([
                'messages' => ['error' => ['Something went wrong while creating the group.']]
            ], 500);
        }
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdatePermissionGroupRequest  $request
     * @param  \App\Models\Groups                     $group
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdatePermissionGroupRequest $request, Groups $group)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $group->update($data);

            DB::commit();

            return response()->json([
                'messages'      => ['success' => ['Group updated']],
                'group_uuid'    => $group->group_uuid,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger(
                'GroupManager update error: '
                    . $e->getMessage()
                    . ' at ' . $e->getFile()
                    . ':' . $e->getLine()
            );

            return response()->json([
                'messages' => ['error' => ['Something went wrong while updating the group.']]
            ], 500);
        }
    }



    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        if (!userCheckPermission('group_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['required', 'uuid'],
        ]);

        try {
            DB::beginTransaction();

            // delete all matching groups in one query
            Groups::query()
                ->whereIn('group_uuid', $validated['items'])
                ->where(function ($query) {
                    $query->where('domain_uuid', session('domain_uuid'))
                        ->orWhereNull('domain_uuid');
                })
                ->delete();

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected group(s) were deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('RingGroups bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected group(s).']]
            ], 500);
        }
    }

    public function cloneGroup(Request $request): JsonResponse
    {
        if (!userCheckPermission('group_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'size:1'],
            'items.*' => ['required', 'uuid'],
        ], [
            'items.size' => 'Select exactly one group to clone.',
        ]);

        $source = Groups::query()
            ->whereKey($validated['items'][0])
            ->first();

        if (!$source || !$this->canAccessGroup($source)) {
            return response()->json([
                'messages' => ['error' => ['Group not found.']]
            ], 404);
        }

        try {
            DB::beginTransaction();

            $cloneDomainUuid = $source->domain_uuid ?: session('domain_uuid');
            $cloneName = $this->uniqueCloneName($source->group_name, $cloneDomainUuid);

            $clone = Groups::create([
                'domain_uuid' => $cloneDomainUuid,
                'group_name' => $cloneName,
                'group_level' => $source->group_level,
                'group_protected' => $source->group_protected,
                'group_description' => $source->group_description,
            ]);

            $now = date('Y-m-d H:i:s');
            $hasPermissionDomain = Schema::hasColumn('v_group_permissions', 'domain_uuid');

            $permissionRows = GroupPermissions::query()
                ->where('group_uuid', $source->group_uuid)
                ->get()
                ->map(function ($permission) use ($clone, $cloneName, $hasPermissionDomain, $now) {
                    $row = [
                        'group_permission_uuid' => (string) Str::uuid(),
                        'group_uuid' => $clone->group_uuid,
                        'group_name' => $cloneName,
                        'permission_name' => $permission->permission_name,
                        'permission_protected' => $permission->permission_protected ?? 'false',
                        'permission_assigned' => $permission->permission_assigned ?? 'true',
                        'insert_date' => $now,
                    ];

                    if ($hasPermissionDomain) {
                        $row['domain_uuid'] = $permission->domain_uuid ?? null;
                    }

                    return $row;
                })
                ->values()
                ->all();

            if ($permissionRows !== []) {
                GroupPermissions::query()->insert($permissionRows);
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ["Group cloned as {$cloneName}."]],
                'group_uuid' => $clone->group_uuid,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('GroupManager clone error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while cloning the group.']]
            ], 500);
        }
    }

    public function selectAll(Request $request)
    {
        try {
            $uuids = $this->scopedGroups($request)
                ->select('group_uuid')
                ->defaultSort('group_name')
                ->pluck('group_uuid');

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $uuids,
            ], 200);
        } catch (\Exception $e) {
            logger($e);
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    private function uniqueCloneName(string $sourceName, ?string $domainUuid): string
    {
        $baseName = trim($sourceName) !== '' ? trim($sourceName) : 'Group';
        $baseName = mb_substr($baseName, 0, 240) . ' Copy';
        $candidate = $baseName;
        $suffix = 2;

        while ($this->groupNameExists($candidate, $domainUuid)) {
            $candidate = mb_substr($baseName, 0, 250 - strlen((string) $suffix)) . " {$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function groupNameExists(string $groupName, ?string $domainUuid): bool
    {
        return Groups::query()
            ->where('group_name', $groupName)
            ->where(function ($query) use ($domainUuid) {
                $query->where('domain_uuid', $domainUuid)
                    ->orWhereNull('domain_uuid');
            })
            ->exists();
    }

    private function groupMembersQuery(Groups $group)
    {
        return DB::table('v_user_groups as user_groups')
            ->join('v_users as users', 'user_groups.user_uuid', '=', 'users.user_uuid')
            ->leftJoin('v_domains as domains', 'user_groups.domain_uuid', '=', 'domains.domain_uuid')
            ->where('user_groups.group_uuid', $group->group_uuid)
            ->when($group->domain_uuid, fn ($query) => $query->where('user_groups.domain_uuid', $group->domain_uuid))
            ->when(!userCheckPermission('user_all'), fn ($query) => $query->where('users.domain_uuid', session('domain_uuid')))
            ->orderBy('domains.domain_name')
            ->orderBy('users.username')
            ->select([
                'user_groups.user_group_uuid',
                'user_groups.user_uuid',
                'user_groups.domain_uuid',
                'users.username',
                'users.user_email',
                'domains.domain_name',
            ]);
    }

    private function availableUsersForGroup(Groups $group): array
    {
        if (!userCheckPermission('group_member_add')) {
            return [];
        }

        $memberDomainUuid = $this->memberDomainUuid($group);
        $assignedUserUuids = UserGroup::query()
            ->where('group_uuid', $group->group_uuid)
            ->where('domain_uuid', $memberDomainUuid)
            ->pluck('user_uuid');

        return DB::table('v_users')
            ->where('domain_uuid', $memberDomainUuid)
            ->whereNotIn('user_uuid', $assignedUserUuids)
            ->orderBy('username')
            ->get(['user_uuid', 'username', 'user_email'])
            ->map(fn ($user) => [
                'value' => $user->user_uuid,
                'label' => $user->username,
                'detail' => $user->user_email,
            ])
            ->values()
            ->all();
    }

    private function memberDomainUuid(Groups $group): string
    {
        return $group->domain_uuid ?: session('domain_uuid');
    }

    private function groupMemberCount(Groups $group): int
    {
        return $this->groupMembersQuery($group)->count();
    }
}
