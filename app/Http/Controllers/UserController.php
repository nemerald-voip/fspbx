<?php

namespace App\Http\Controllers;


use App\Models\User;
use Inertia\Inertia;
use App\Data\UserData;
use App\Models\Domain;
use App\Models\Groups;
use App\Models\Extensions;
use App\Models\DomainGroups;
use App\Models\LdapDirectory;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Services\UserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Traits\ChecksLimits;

class UserController extends Controller
{
    use ChecksLimits;

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Users';
    protected $searchable = ['username', 'user_email', 'name_formatted'];

    private UserService $users;

    public function __construct(?UserService $users = null)
    {
        $this->model = new User();
        $this->users = $users ?? app(UserService::class);
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

        return Inertia::render($this->viewName, [
            'pagination' => [
                'per_page' => fspbx_pagination_per_page($request),
                'per_page_options' => fspbx_pagination_options(),
            ],
            'routes' => [
                'data_route' => route('users.data'),
                'item_options' => route('users.item.options'),
                'bulk_delete' => route('users.bulk.delete'),
                'select_all' => route('users.select.all'),
            ],
            'permissions' => $this->getUserPermissions(),
        ]);
    }

    public function getData(Request $request)
    {
        abort_unless(userCheckPermission('user_view'), 403);

        $perPage = fspbx_pagination_per_page($request);
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
            && LdapDirectory::query()->where('domain_uuid', $currentDomain)->exists();

        if ($hasDirectories) {
            $userQuery->with([
                'ldapDirectoryUsers' => fn ($links) => $links
                    ->where('domain_uuid', $currentDomain)
                    ->whereHas('directory')
                    ->with('directory:directory_uuid,name')
                    ->orderByDirectoryPriority()
                    ->select(['directory_uuid', 'user_uuid']),
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

        $users->getCollection()->transform(function ($user) use ($hasDirectories) {
            $user->ldap_directory_name = $hasDirectories
                ? $user->ldapDirectoryUsers->first()?->directory?->name
                : null;
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

        return response()->json(array_merge($usersDto->toArray(), [
            'has_directories' => $hasDirectories,
            'selectable_total' => $this->selectableUserCount($request, $currentDomain, $hasDirectories),
        ]));
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
        $data = $request->validated();
        $domainUuid = $this->users->isSuperadmin($request->user())
            ? ($data['domain_uuid'] ?: session('domain_uuid'))
            : session('domain_uuid');

        try {
            $user = $this->users->create($request->user(), $domainUuid, $data);
            return response()->json([
                'messages' => ['success' => [__('User created')]],
                'user_uuid' => $user->user_uuid,
            ], 201);
        } catch (AuthorizationException | ValidationException | ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'messages' => ['error' => [__('Something went wrong while creating the user.')]],
            ], 500);
        }
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        try {
            $this->users->update($request->user(), (string) session('domain_uuid'), $user, $request->validated());
            return response()->json(['messages' => ['success' => [__('User updated')]]]);
        } catch (AuthorizationException | ValidationException | ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'messages' => ['error' => [__('Something went wrong while updating.')]],
            ], 500);
        }
    }

    public function bulkDelete(Request $request)
    {
        abort_unless(userCheckPermission('user_delete'), 403);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'uuid', 'distinct'],
        ]);

        try {
            $this->users->deleteMany($request->user(), (string) session('domain_uuid'), $data['items']);
            return response()->json([
                'messages' => ['success' => [__('Selected user(s) were deleted successfully.')]],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['messages' => ['error' => collect($e->errors())->flatten()->all()]], 422);
        } catch (AuthorizationException | ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'messages' => ['error' => [__('An error occurred while deleting the selected user(s).')]],
            ], 500);
        }
    }

    private function emptyDirectoryManagement(): array
    {
        return $this->users->emptyDirectoryManagement();
    }

    private function directoryManagement(User $user): array
    {
        return $this->users->directoryManagement($user);
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
                && LdapDirectory::query()->where('domain_uuid', $currentDomain)->exists();

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
        $query->searchIdentity($value, true);
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

        $inDomain = fn ($links) => $links->where('domain_uuid', $currentDomain);

        $value === 'directory'
            ? $query->whereHas('ldapDirectoryUsers', $inDomain)
            : $query->whereDoesntHave('ldapDirectoryUsers', $inDomain);
    }

    /**
     * The query equivalent of canManageTarget(): superadmins are off limits, and so is
     * anyone whose highest group level outranks the actor's. Kept in step with
     * that method -- it is the set-wide form of the same rule.
     */
    protected function applyManageableScope($query): void
    {
        if (isSuperAdmin()) {
            return;
        }

        $actorLevel = $this->actorLevel();

        $query->whereDoesntHave('roles', fn ($roles) => $roles->protectedFrom($actorLevel));
    }

    protected function actorLevel(): int
    {
        return $this->users->actorLevel(auth()->user());
    }

    protected function canManageTarget(User $user): bool
    {
        return $this->users->canManageTarget(auth()->user(), $user, (string) session('domain_uuid'));
    }

    protected function ensureCanManageTarget(User $user): void
    {
        $this->users->ensureCanManageTarget(auth()->user(), $user, (string) session('domain_uuid'));
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
