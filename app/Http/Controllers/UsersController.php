<?php

namespace App\Http\Controllers;


use App\Models\User;
use Inertia\Inertia;
use App\Data\UserData;
use App\Models\Domain;
use App\Models\Groups;
use App\Models\Extensions;
use Illuminate\Support\Str;
use App\Models\DomainGroups;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Services\Auth\UserSessionInvalidationService;
use App\Traits\ChecksLimits;

class UsersController extends Controller
{
    use ChecksLimits;

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Users';
    protected $searchable = ['username', 'user_email', 'name_formatted'];

    public function __construct()
    {
        $this->model = new User();
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("user_view")) {
            return redirect('/');
        }

        $perPage = fspbx_pagination_per_page();
        $currentDomain = session('domain_uuid');

        $select = [
            'user_uuid',
            'username',
            'user_email',
            'user_enabled',
            'domain_uuid',
        ];

        if (Schema::hasColumn('v_users', 'extension_uuid')) {
            $select[] = 'extension_uuid';
        }

        $userQuery = User::query()
            ->where('domain_uuid', $currentDomain)
            ->select($select);

        // Directory provenance is only worth querying -- or surfacing in the UI --
        // when this account actually has a directory configured. Installs that
        // never touch Active Directory pay nothing and see no trace of it.
        $hasDirectories = Schema::hasTable('ldap_directory_users')
            && Schema::hasTable('ldap_directories')
            && DB::table('ldap_directories')->where('domain_uuid', $currentDomain)->exists();

        if ($hasDirectories) {
            $userQuery->addSelect([
                'ldap_directory_name' => DB::table('ldap_directory_users as directory_users')
                    ->join('ldap_directories as directories', 'directories.directory_uuid', '=', 'directory_users.directory_uuid')
                    ->select('directories.name')
                    ->whereColumn('directory_users.user_uuid', 'v_users.user_uuid')
                    ->whereColumn('directory_users.domain_uuid', 'v_users.domain_uuid')
                    ->where('directory_users.domain_uuid', $currentDomain)
                    ->orderBy('directories.priority')
                    ->orderBy('directories.name')
                    ->limit(1),
            ]);
        }

        $users = QueryBuilder::for($userQuery)
            ->allowedFilters([
                AllowedFilter::callback('search', fn($query, $value) => $this->applySearchFilter($query, $value)),
                AllowedFilter::exact('user_enabled'),
                AllowedFilter::callback('source', fn($query, $value) => $this->applySourceFilter($query, $value, $currentDomain, $hasDirectories)),
            ])
            ->allowedSorts(['username', 'add_date'])
            ->allowedIncludes(['user_groups'])
            ->with([
                'user_groups:user_uuid,user_group_uuid,group_uuid,group_name',
            ])
            ->with([
                'extension:extension_uuid,extension,effective_caller_id_name',
            ])
            ->defaultSort('username')
            ->paginate($perPage);

        $users->getCollection()->transform(function ($user) {
            $canManage = userCheckPermission('user_edit') && $this->canManageTarget($user);
            $canDelete = userCheckPermission('user_delete')
                && $this->canManageTarget($user)
                && blank($user->ldap_directory_name);

            $user->can_manage_target = $canManage;
            $user->can_delete_target = $canDelete;
            return $user;
        });

        // wrap in your DTO
        $usersDto = UserData::collect($users);

        // logger($usersDto);

        return Inertia::render(
            $this->viewName,
            [
                'data' => $usersDto,
                'pagination' => [
                    'per_page' => fspbx_pagination_per_page(),
                    'per_page_options' => fspbx_pagination_options(),
                ],

                'routes' => [
                    'current_page' => route('users.index'),
                    'item_options' => route('users.item.options'),
                    'bulk_delete' => route('users.bulk.delete'),
                    'select_all' => route('users.select.all'),
                ],
                'permissions' => $this->getUserPermissions(),
                'has_directories' => $hasDirectories,
                'selectable_total' => $this->selectableUserCount($request, $currentDomain, $hasDirectories),
            ]
        );
    }


    public function getItemOptions(Request $request)
    {
        $itemUuid = $request->input('item_uuid');

        $domain_uuid = session('domain_uuid');
        $directoryManagement = $this->emptyDirectoryManagement();

        $select = [
            'user_uuid',
            'username',
            'user_email',
            'user_enabled',
            'domain_uuid',
        ];

        if (Schema::hasColumn('v_users', 'extension_uuid')) {
            $select[] = 'extension_uuid';
        }

        // 1) Base payload: either an existing user DTO or a “new user” stub
        if ($itemUuid) {
            $user = QueryBuilder::for(User::class)
                ->select($select)
                ->where('domain_uuid', $domain_uuid)
                ->with([
                    'user_groups' => function ($q) {
                        $q->select([
                            'user_group_uuid',
                            'domain_uuid',
                            'user_uuid',
                            'group_name',
                            'group_uuid',
                        ]);
                    },
                ])
                ->with([
                    'domain_permissions' => function ($q) {
                        $q->select([
                            'id',
                            'domain_uuid',
                            'user_uuid',
                        ]);
                    },
                ])
                ->with([
                    'domain_group_permissions' => function ($q) {
                        $q->select([
                            'id',
                            'user_uuid',
                            'domain_group_uuid',
                            'user_uuid',
                        ]);
                    },
                ])
                ->with([
                    'extension' => function ($q) {
                        $q->select([
                            'extension_uuid',
                            'extension',
                            'effective_caller_id_name',
                        ]);
                    },
                ])
                ->with([
                    'locations' => function ($q) {
                        $q->select([
                            'locations.location_uuid',
                            'locations.name',
                        ]);
                    },
                ])
                ->whereKey($itemUuid)
                ->firstOrFail();

            $this->ensureCanManageTarget($user);

            $directoryManagement = $this->directoryManagement($user);
            $userDto = UserData::from($user);
            $updateRoute = route('users.update', ['user' => $itemUuid]);
        } else {
            if (! userCheckPermission('user_add')) {
                return response()->json([
                    'messages' => ['error' => [__('Access denied.')]]
                ], 403);
            }

            if ($resp = $this->enforceLimit(
                'users',
                \App\Models\User::class,
                'domain_uuid',
                'user_limit_error'
            )) {
                return $resp;
            }

            $userDto = new UserData(
                user_uuid: '',
                user_email: '',
                name_formatted: '',
                first_name: '',
                last_name: '',
                language: 'en-us',
                time_zone: get_local_time_zone(),
                user_enabled: 'true',
                domain_uuid: $domain_uuid,
                extension_uuid: null,
            );
            $updateRoute = null;
        }

        // 2) Permissions array
        $permissions = $this->getUserPermissions();

        $groups = Groups::where('group_level', '<=', session('user.group_level'))
            ->where(function ($query) use ($domain_uuid) {
                $query->where('domain_uuid', null)
                    ->orWhere('domain_uuid', $domain_uuid);
            })
            ->orderBy('group_name')
            ->get()
            ->map(function ($group) {
                return [
                    'value' => $group->group_uuid,
                    'label' => $group->group_name,
                ];
            })->toArray();

        $domains = Domain::where('domain_enabled', true)
            ->orderBy('domain_description')
            ->get()
            ->map(function ($domain) {
                return [
                    'value' => $domain->domain_uuid,
                    'label' => $domain->domain_description ?: $domain->domain_name,
                ];
            })->toArray();

        $domain_groups = DomainGroups::orderBy('group_name')
            ->get()
            ->map(function ($group) {
                return [
                    'value' => $group->domain_group_uuid,
                    'label' => $group->group_name,
                ];
            })->toArray();

        // Transform greetings into the desired array format
        $extensions = Extensions::where('domain_uuid', $domain_uuid)
            ->select([
                'extension_uuid',
                'extension',
                'effective_caller_id_name',
            ])
            ->orderBy('extension')
            ->get()
            ->map(function ($ext) {
                return [
                    'value' => $ext->extension_uuid,
                    'label' => $ext->name_formatted,
                ];
            })->toArray();


        // 3) Any routes your front end needs
        $routes = [
            'store_route'  => route('users.store'),
            'update_route' => $updateRoute,
            'password_reset' => route('users.password.email'),
            'tokens' => route('tokens.index'),
            'create_token' => route('tokens.store'),
            'token_bulk_delete' => route('tokens.bulk.delete'),
            'locations' => route('locations.index'),
        ];

        return response()->json([
            'item'        => $userDto,
            'permissions' => $permissions,
            'routes'      => $routes,
            'timezones' => getGroupedTimezones(),
            'groups' => $groups,
            'domains' => $domains,
            'domain_groups' => $domain_groups,
            'extensions' => $extensions,
            'directory_management' => $directoryManagement,
        ]);
    }


    /**
     * Store a newly created user in storage.
     *
     * @param  \App\Http\Requests\StoreUserRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreUserRequest $request)
    {
        if (! userCheckPermission('user_add')) {
            return response()->json([
                'messages' => ['error' => [__('Access denied.')]]
            ], 403);
        }

        $data = $request->validated();
        $domain_uuid = session('domain_uuid');

        if (! isSuperAdmin()) {
            $data['domain_uuid'] = $domain_uuid;
        }

        $allowedGroups = $this->allowedGroupsForActor($data['groups'] ?? [], $data['domain_uuid']);

        $username = Str::slug($data['first_name'], '_')
            . (!empty($data['last_name']) ? '_' . Str::slug($data['last_name'], '_') : '');

        try {
            DB::beginTransaction();

            $userAttributes = [
                'username'     => $username,
                'user_email'   => $data['user_email'],
                'user_enabled' => $data['user_enabled'] ?? 'true',
                'domain_uuid'  => $data['domain_uuid'],
            ];

            if (Schema::hasColumn('v_users', 'extension_uuid')) {
                $userAttributes['extension_uuid'] = $data['extension_uuid'] ?? null;
            }

            $user = User::create($userAttributes);

            $user->user_adv_fields()->create([
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'] ?? null,
            ]);

            foreach (['language', 'time_zone'] as $field) {
                $user->settings()->create([
                    'domain_uuid'               => $data['domain_uuid'],
                    'user_setting_category'     => 'domain',
                    'user_setting_subcategory'  => $field,
                    'user_setting_name'         => $field === 'language' ? 'code' : 'name',
                    'user_setting_value'        => $data[$field] ?? null,
                    'user_setting_enabled'      => true,
                ]);
            }

            foreach (($data['groups'] ?? []) as $groupUuid) {
                $user->user_groups()->create([
                    'group_uuid'  => $groupUuid,
                    'domain_uuid' => $data['domain_uuid'],
                    'group_name'  => $allowedGroups[$groupUuid]->group_name,
                ]);
            }

            if (isset($data['accounts']) && is_array($data['accounts'])) {
                foreach ($data['accounts'] as $managedDomainUuid) {
                    $user->domain_permissions()->create([
                        'user_uuid'   => $user->user_uuid,
                        'domain_uuid' => $managedDomainUuid,
                    ]);
                }
            }

            if (isset($data['account_groups']) && is_array($data['account_groups'])) {
                foreach ($data['account_groups'] as $domainGroupUuid) {
                    $user->domain_group_permissions()->create([
                        'user_uuid'         => $user->user_uuid,
                        'domain_group_uuid' => $domainGroupUuid,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => [__('User created')]],
                'user_uuid' => $user->user_uuid,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('User create error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('Something went wrong while creating the user.')]]
            ], 500);
        }
    }


    /**
     * Update the specified user in storage.
     *
     * @param  \App\Http\Requests\UpdateUserRequest  $request
     * @param  \App\Models\User                     $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        if (! userCheckPermission('user_edit')) {
            return response()->json([
                'messages' => ['error' => [__('Access denied.')]]
            ], 403);
        }

        $this->ensureCanManageTarget($user);

        $validated   = $request->validated();
        $domain_uuid = session('domain_uuid');
        $directoryManagement = $this->directoryManagement($user);
        $managedGroupUuids = collect($directoryManagement['managed_roles'])
            ->pluck('value');

        if (! isSuperAdmin()) {
            unset($validated['domain_uuid'], $validated['user_type']);
        }

        $localGroupUuids = collect($validated['groups'] ?? [])
            ->diff($managedGroupUuids)
            ->unique()
            ->values();
        $allowedGroups = collect();
        if ($localGroupUuids->isNotEmpty()) {
            $allowedGroups = $this->allowedGroupsForActor($localGroupUuids->all(), $user->domain_uuid);
        }

        $groupsChanged = false;
        if (array_key_exists('groups', $validated) && is_array($validated['groups'])) {
            $currentGroupUuids = $user->user_groups()
                ->pluck('group_uuid')
                ->sort()
                ->values()
                ->all();

            $newGroupUuids = $localGroupUuids
                ->merge($managedGroupUuids)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $groupsChanged = $currentGroupUuids !== $newGroupUuids;
        }

        try {
            DB::beginTransaction();

            if (! $directoryManagement['managed']) {
                $user->user_adv_fields()->updateOrCreate(
                    ['user_uuid' => $user->user_uuid],
                    [
                        'first_name' => $validated['first_name'],
                        'last_name'  => $validated['last_name'] ?? null,
                    ]
                );
            }

            $user->update($validated);

            foreach (['language', 'time_zone'] as $field) {
                $user->settings()->updateOrCreate(
                    [
                        'domain_uuid'                => $user->domain_uuid,
                        'user_setting_category'      => 'domain',
                        'user_setting_subcategory'   => $field,
                    ],
                    [
                        'user_setting_name'    => $field === 'language' ? 'code' : 'name',
                        'user_setting_value'   => $validated[$field] ?? null,
                        'user_setting_enabled' => true,
                    ]
                );
            }

            if (array_key_exists('groups', $validated) && is_array($validated['groups'])) {
                $localMemberships = $user->user_groups();
                if ($managedGroupUuids->isNotEmpty()) {
                    $localMemberships->whereNotIn('group_uuid', $managedGroupUuids);
                }
                $localMemberships->delete();

                foreach ($localGroupUuids as $groupUuid) {
                    $user->user_groups()->create([
                        'group_uuid'  => $groupUuid,
                        'domain_uuid' => $user->domain_uuid,
                        'group_name'  => $allowedGroups[$groupUuid]->group_name,
                    ]);
                }
            }

            $user->domain_permissions()->delete();
            if (isset($validated['accounts']) && is_array($validated['accounts'])) {
                foreach ($validated['accounts'] as $managedDomainUuid) {
                    $user->domain_permissions()->create([
                        'user_uuid'   => $user->user_uuid,
                        'domain_uuid' => $managedDomainUuid,
                    ]);
                }
            }

            $user->domain_group_permissions()->delete();
            if (isset($validated['account_groups']) && is_array($validated['account_groups'])) {
                foreach ($validated['account_groups'] as $domainGroupUuid) {
                    $user->domain_group_permissions()->create([
                        'user_uuid'         => $user->user_uuid,
                        'domain_group_uuid' => $domainGroupUuid,
                    ]);
                }
            }

            $user->locations()->detach();
            if (!empty($validated['locations']) && is_array($validated['locations'])) {
                foreach ($validated['locations'] as $locationUuid) {
                    $user->locations()->attach($locationUuid);
                }
            }

            DB::commit();

            if ($groupsChanged) {
                app(UserSessionInvalidationService::class)->invalidateByUserUuids([$user->user_uuid]);
            }

            return response()->json([
                'messages' => ['success' => [__('User updated')]]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('User update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('Something went wrong while updating.')]]
            ], 500);
        }
    }



    /**
     * Remove the specified users from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        if (! userCheckPermission('user_delete')) {
            return response()->json([
                'messages' => ['error' => [__('Access denied.')]]
            ], 403);
        }

        try {
            DB::beginTransaction();

            $domain_uuid = session('domain_uuid');
            $uuids = $request->input('items', []);

            $users = User::where('domain_uuid', $domain_uuid)
                ->whereIn('user_uuid', $uuids)
                ->get();

            foreach ($users as $user) {
                $this->ensureCanManageTarget($user);
            }

            if ($this->hasDirectoryManagedUsers($domain_uuid, $users->pluck('user_uuid'))) {
                DB::rollBack();

                return response()->json([
                    'messages' => ['error' => [__('Directory-managed users cannot be deleted here. Disable or remove them in the connected directory.')]],
                ], 422);
            }

            foreach ($users as $user) {
                $user->user_adv_fields()->delete();
                $user->settings()->delete();
                $user->user_groups()->delete();
                $user->domain_permissions()->delete();
                $user->domain_group_permissions()->delete();
                $user->delete();
            }

            DB::table('locationables')
                ->where('locationable_type', \App\Models\User::class)
                ->whereIn('locationable_id', $uuids)
                ->delete();

            DB::commit();

            app(UserSessionInvalidationService::class)->invalidateByUserUuids($users->pluck('user_uuid'));

            return response()->json([
                'messages' => ['success' => [__('Selected user(s) were deleted successfully.')]]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('User bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('An error occurred while deleting the selected user(s).')]]
            ], 500);
        }
    }

    private function hasDirectoryManagedUsers(string $domainUuid, $userUuids): bool
    {
        if (! Schema::hasTable('ldap_directory_users') || $userUuids->isEmpty()) {
            return false;
        }

        return DB::table('ldap_directory_users')
            ->where('domain_uuid', $domainUuid)
            ->whereIn('user_uuid', $userUuids)
            ->exists();
    }

    private function emptyDirectoryManagement(): array
    {
        return [
            'managed' => false,
            'directory_name' => null,
            'manage_groups_locally' => true,
            'email_managed' => false,
            'managed_roles' => [],
            'extension_managed' => false,
            'remote_extension' => null,
        ];
    }

    private function directoryManagement(User $user): array
    {
        if (! Schema::hasTable('ldap_directory_users') || ! Schema::hasTable('ldap_directories')) {
            return $this->emptyDirectoryManagement();
        }

        $link = DB::table('ldap_directory_users as directory_users')
            ->join('ldap_directories as directories', 'directories.directory_uuid', '=', 'directory_users.directory_uuid')
            ->where('directory_users.domain_uuid', $user->domain_uuid)
            ->where('directory_users.user_uuid', $user->user_uuid)
            ->orderBy('directories.priority')
            ->orderBy('directories.name')
            ->first([
                'directory_users.directory_user_uuid',
                'directory_users.email as directory_email',
                'directory_users.extension',
                'directories.name as directory_name',
                'directories.manage_groups_locally',
            ]);

        if (! $link) {
            return $this->emptyDirectoryManagement();
        }

        $manageGroupsLocally = filter_var($link->manage_groups_locally, FILTER_VALIDATE_BOOLEAN);
        $managedRoles = [];

        if (! $manageGroupsLocally && Schema::hasTable('ldap_directory_user_group_assignments')) {
            $managedRoles = DB::table('ldap_directory_user_group_assignments as assignments')
                ->join('v_groups as groups', 'groups.group_uuid', '=', 'assignments.group_uuid')
                ->where('assignments.directory_user_uuid', $link->directory_user_uuid)
                ->orderBy('groups.group_name')
                ->get(['groups.group_uuid as value', 'groups.group_name as label'])
                ->map(fn ($group) => ['value' => $group->value, 'label' => $group->label])
                ->all();
        }

        return [
            'managed' => true,
            'directory_name' => $link->directory_name,
            'manage_groups_locally' => $manageGroupsLocally,
            'email_managed' => filter_var($link->directory_email, FILTER_VALIDATE_EMAIL) !== false,
            'managed_roles' => $managedRoles,
            'extension_managed' => filled($link->extension),
            'remote_extension' => $link->extension,
        ];
    }

    /**
     * Returns every user uuid matching the current filters, so the list view can
     * offer "select all N" beyond the current page. The set is narrowed to users
     * the actor may actually manage -- bulkDelete() aborts the whole batch on the
     * first unmanageable target, so handing back a superadmin here would make
     * select-all-then-delete fail as a unit.
     */
    public function selectAll(Request $request)
    {
        if (! userCheckPermission('user_view')) {
            return response()->json([
                'messages' => ['error' => [__('Access denied.')]]
            ], 403);
        }

        try {
            $currentDomain = session('domain_uuid');

            $hasDirectories = Schema::hasTable('ldap_directory_users')
                && Schema::hasTable('ldap_directories')
                && DB::table('ldap_directories')->where('domain_uuid', $currentDomain)->exists();

            $query = User::query()
                ->where('domain_uuid', $currentDomain)
                ->select('user_uuid');

            if ($search = $request->input('search')) {
                $this->applySearchFilter($query, $search);
            }

            if ($source = $request->input('source')) {
                $this->applySourceFilter($query, $source, $currentDomain, $hasDirectories);
            }

            // The only bulk action on this page is Delete. Directory-managed
            // users are intentionally read-only, so "select all" must return
            // the same deletable set as the visible row checkboxes.
            if ($hasDirectories) {
                $this->applySourceFilter($query, 'local', $currentDomain, true);
            }

            $this->applyManageableScope($query);

            return response()->json([
                'messages' => ['success' => [__('All items selected')]],
                'items' => $query->pluck('user_uuid'),
            ]);
        } catch (\Throwable $e) {
            logger('User selectAll error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('Failed to select all items')]]
            ], 500);
        }
    }

    protected function applySearchFilter($query, $value): void
    {
        $query->where(function ($q) use ($value) {
            $q->where('user_email', 'ilike', "%{$value}%")
                ->orWhere('username', 'ilike', "%{$value}%")
                ->orWhereHas('user_adv_fields', function ($q2) use ($value) {
                    $q2->where('first_name', 'ilike', "%{$value}%")
                        ->orWhere('last_name',  'ilike', "%{$value}%");
                });
        });
    }

    private function selectableUserCount(Request $request, string $currentDomain, bool $hasDirectories): int
    {
        if (! userCheckPermission('user_delete')) {
            return 0;
        }

        $query = User::query()->where('domain_uuid', $currentDomain);

        if ($search = $request->input('filter.search')) {
            $this->applySearchFilter($query, $search);
        }

        if ($source = $request->input('filter.source')) {
            $this->applySourceFilter($query, $source, $currentDomain, $hasDirectories);
        }

        if ($hasDirectories) {
            $this->applySourceFilter($query, 'local', $currentDomain, true);
        }

        $this->applyManageableScope($query);

        return $query->count();
    }

    protected function applySourceFilter($query, $value, string $currentDomain, bool $hasDirectories): void
    {
        if (! $hasDirectories || ! in_array($value, ['local', 'directory'], true)) {
            return;
        }

        $linkedToDirectory = function ($q) use ($currentDomain) {
            $q->select(DB::raw(1))
                ->from('ldap_directory_users as directory_users')
                ->whereColumn('directory_users.user_uuid', 'v_users.user_uuid')
                ->where('directory_users.domain_uuid', $currentDomain);
        };

        $value === 'directory'
            ? $query->whereExists($linkedToDirectory)
            : $query->whereNotExists($linkedToDirectory);
    }

    /**
     * The SQL mirror of canManageTarget(): superadmins are off limits, and so is
     * anyone whose highest group level outranks the actor's. Kept in step with
     * that method -- it is the set-wide form of the same rule.
     */
    protected function applyManageableScope($query): void
    {
        if (isSuperAdmin()) {
            return;
        }

        $groupsOfUser = function ($q) {
            $q->select(DB::raw(1))
                ->from('v_user_groups')
                ->join('v_groups', 'v_groups.group_uuid', '=', 'v_user_groups.group_uuid')
                ->whereColumn('v_user_groups.user_uuid', 'v_users.user_uuid');
        };

        $actorLevel = $this->actorLevel();

        $query->whereNotExists(function ($q) use ($groupsOfUser) {
            $groupsOfUser($q);
            $q->whereRaw('lower(v_groups.group_name) = ?', ['superadmin']);
        })->whereNotExists(function ($q) use ($groupsOfUser, $actorLevel) {
            $groupsOfUser($q);
            $q->where('v_groups.group_level', '>', $actorLevel);
        });
    }

    protected function actorLevel(): int
    {
        return (int) (session('user.group_level') ?? 0);
    }

    protected function targetLevel(User $user): int
    {
        return (int) $user->user_groups()
            ->join('v_groups', 'v_groups.group_uuid', '=', 'v_user_groups.group_uuid')
            ->max('v_groups.group_level');
    }

    protected function targetIsSuperadmin(User $user): bool
    {
        return $user->user_groups()
            ->join('v_groups', 'v_groups.group_uuid', '=', 'v_user_groups.group_uuid')
            ->whereRaw('lower(v_groups.group_name) = ?', ['superadmin'])
            ->exists();
    }

    protected function canManageTarget(User $user): bool
    {
        if (isSuperAdmin()) {
            return true;
        }

        if ($user->domain_uuid !== session('domain_uuid')) {
            return false;
        }

        if ($this->targetIsSuperadmin($user)) {
            return false;
        }

        return $this->targetLevel($user) <= $this->actorLevel();
    }

    protected function ensureCanManageTarget(User $user): void
    {
        if (! $this->canManageTarget($user)) {
            abort(403, __('You are not allowed to manage this user.'));
        }
    }

    protected function allowedGroupsForActor(array $groupUuids, ?string $domainUuid = null)
    {
        $domainUuid ??= session('domain_uuid');

        $groups = Groups::query()
            ->whereIn('group_uuid', $groupUuids)
            ->where(function ($q) use ($domainUuid) {
                $q->whereNull('domain_uuid')
                    ->orWhere('domain_uuid', $domainUuid);
            })
            ->where('group_level', '<=', $this->actorLevel())
            ->get([
                'group_uuid',
                'group_name',
                'group_level',
            ]);

        if ($groups->count() !== count(array_unique($groupUuids))) {
            abort(403, __('One or more selected groups are not allowed.'));
        }

        if (! isSuperAdmin() && $groups->contains(fn($group) => strtolower($group->group_name) === 'superadmin')) {
            abort(403, __('You are not allowed to assign the superadmin group.'));
        }

        return $groups->keyBy('group_uuid');
    }

    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['user_create'] = userCheckPermission('user_add');
        $permissions['user_edit'] = userCheckPermission('user_edit');
        $permissions['user_delete'] = userCheckPermission('user_delete');
        $permissions['user_group_view'] = userCheckPermission('user_group_view');
        $permissions['user_group_edit'] = userCheckPermission('user_group_edit');
        $permissions['user_status'] = userCheckPermission('user_status');
        $permissions['user_view_managed_accounts'] = userCheckPermission('user_view_managed_accounts');
        $permissions['user_update_managed_accounts'] = userCheckPermission('user_update_managed_accounts');
        $permissions['user_view_managed_account_groups'] = userCheckPermission('user_view_managed_account_groups');
        $permissions['user_update_managed_account_groups'] = userCheckPermission('user_update_managed_account_groups');
        $permissions['api_key'] = userCheckPermission('api_key');
        $permissions['api_key_create'] = userCheckPermission('api_key_create');
        $permissions['api_key_update'] = userCheckPermission('api_key_update');
        $permissions['api_key_delete'] = userCheckPermission('api_key_delete');
        $permissions['is_superadmin'] = isSuperAdmin();

        return $permissions;
    }
}
