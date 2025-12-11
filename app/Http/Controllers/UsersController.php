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

        $perPage = 50;
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

        $users = QueryBuilder::for(User::class)
            // only users in the current domain
            ->where('domain_uuid', $currentDomain)
            ->select($select)
            // allow ?filter[username]=foo or ?filter[user_email]=bar
            ->allowedFilters([
                // Only email and name_formatted
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('user_email', 'ilike', "%{$value}%")
                            // if you want to search the fallback username too:
                            ->orWhere('username', 'ilike', "%{$value}%")
                            // and/or match first_name/last_name
                            ->orWhereHas('user_adv_fields', function ($q2) use ($value) {
                                $q2->where('first_name', 'ilike', "%{$value}%")
                                    ->orWhere('last_name',  'ilike', "%{$value}%");
                            });
                    });
                }),
                // keep any other filters you still need:
                AllowedFilter::exact('user_enabled'),
            ])
            // allow ?sort=-username or ?sort=add_date
            ->allowedSorts(['username', 'add_date'])
            // let your front-end optionally eager-load relations
            ->allowedIncludes(['user_groups'])
            // eager-load only the columns you need on user_groups
            ->with([
                'user_groups:user_uuid,user_group_uuid,group_uuid,group_name',
            ])
            ->with([
                'extension:extension_uuid,extension,effective_caller_id_name',
            ])
            ->defaultSort('username')
            ->paginate($perPage);

        // wrap in your DTO
        $usersDto = UserData::collect($users);

        // logger($usersDto);

        return Inertia::render(
            $this->viewName,
            [
                'data' => $usersDto,

                'routes' => [
                    'current_page' => route('users.index'),
                    'item_options' => route('users.item.options'),
                    'bulk_delete' => route('users.bulk.delete'),
                    'select_all' => route('users.select.all'),
                ]
            ]
        );
    }


    public function getItemOptions(Request $request)
    {
        $itemUuid = $request->input('item_uuid');

        $domain_uuid = session('domain_uuid');

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
                        // qualify with table name and only select columns from `locations`
                        $q->select([
                            'locations.location_uuid',   // required PK for the related model
                            'locations.name',
                        ]);
                    },
                ])
                ->whereKey($itemUuid)
                ->firstOrFail();

            $userDto = UserData::from($user);
            // logger($userDto);
            $updateRoute = route('users.update', ['user' => $itemUuid]);
        } else {

          // Check for limits
                if ($resp = $this->enforceLimit(
                    'users',
                    \App\Models\User::class,
                    'domain_uuid',
                    'user_limit_error'
                )) {
                    return $resp;
                }

            // “New user” defaults
            $userDto     = new UserData(
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
        $data        = $request->validated();
        $domain_uuid = session('domain_uuid');

        // build username: slug of first_name + (optional '_' + slug(last_name))
        $username = Str::slug($data['first_name'], '_')
            . (!empty($data['last_name']) ? '_' . Str::slug($data['last_name'], '_') : '');

        try {
            DB::beginTransaction();

            // 1) Core user
            $user = User::create([
                'username'     => $username,
                'user_email'   => $data['user_email'],
                'user_enabled' => $data['user_enabled'] ?? 'true',
                'domain_uuid'  => $data['domain_uuid'] ?? session('domain_uuid'),
            ]);

            // 2) Advanced fields
            $user->user_adv_fields()->create([
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'] ?? null,
            ]);

            // 3) Domain settings
            foreach (['language', 'time_zone'] as $field) {
                $user->settings()->create([
                    'domain_uuid'               => $domain_uuid,
                    'user_setting_category'     => 'domain',
                    'user_setting_subcategory'  => $field,
                    'user_setting_name'         => $field === 'language' ? 'code' : 'name',
                    'user_setting_value'        => $data[$field] ?? null,
                    'user_setting_enabled'      => true,
                ]);
            }

            // 4) Groups
            $groupNames = Groups::whereIn('group_uuid', $data['groups'])
                ->pluck('group_name', 'group_uuid');

            foreach ($data['groups'] as $groupUuid) {
                $user->user_groups()->create([
                    'group_uuid'  => $groupUuid,
                    'domain_uuid' => $domain_uuid,
                    'group_name'  => $groupNames[$groupUuid] ?? null,
                ]);
            }

            // 5) Domain Permissions (Accounts)
            if (isset($data['accounts']) && is_array($data['accounts'])) {
                // Add new permissions
                foreach ($data['accounts'] as $domainUuid) {
                    $user->domain_permissions()->create([
                        'user_uuid'   => $user->user_uuid,
                        'domain_uuid' => $domainUuid,
                    ]);
                }
            }

            // 6) Domain Group Permissions (Account Groups)
            if (isset($data['account_groups']) && is_array($data['account_groups'])) {
                // Add new group permissions
                foreach ($data['account_groups'] as $domainGroupUuid) {
                    $user->domain_group_permissions()->create([
                        'user_uuid'         => $user->user_uuid,
                        'domain_group_uuid' => $domainGroupUuid,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['User created']],
                'user_uuid' => $user->user_uuid,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('User create error: ' . $e->getMessage()
                . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while creating the user.']]
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

        $validated   = $request->validated();
        $domain_uuid = session('domain_uuid');
        // logger($validated);

        try {
            DB::beginTransaction();

            // 1) Advanced fields (first_name, last_name)
            $user->user_adv_fields()->updateOrCreate(
                ['user_uuid' => $user->user_uuid],
                [
                    'first_name' => $validated['first_name'] ?? null,
                    'last_name'  => $validated['last_name']  ?? null,
                ]
            );

            // 2) Core user updates
            $user->update($validated);

            // 3) Domain settings: language & time_zone
            foreach (['language', 'time_zone'] as $field) {
                $user->settings()->updateOrCreate(
                    [
                        'user_setting_category'    => 'domain',
                        'user_setting_subcategory' => $field,
                    ],
                    [
                        'user_setting_value'   => $validated[$field] ?? null,
                        'user_setting_enabled' => true,
                    ]
                );
            }

            // 4) User groups
            if (!empty($validated['groups']) && is_array($validated['groups'])) {
                $groupNames = Groups::whereIn('group_uuid', $validated['groups'])
                    ->pluck('group_name', 'group_uuid');

                // Delete existing group relations for the user
                $user->user_groups()->delete();

                // Add new group relations
                foreach ($validated['groups'] as $groupUuid) {
                    $user->user_groups()->create([
                        'group_uuid'  => $groupUuid,
                        'domain_uuid' => $domain_uuid,
                        'group_name'  => $groupNames[$groupUuid] ?? null,
                    ]);
                }
            }

            // 5) Domain Permissions (Accounts)
            // Remove existing permissions for this user
            $user->domain_permissions()->delete();
            if (isset($validated['accounts']) && is_array($validated['accounts'])) {
                // Add new permissions
                foreach ($validated['accounts'] as $domainUuid) {
                    $user->domain_permissions()->create([
                        'user_uuid'   => $user->user_uuid,
                        'domain_uuid' => $domainUuid,
                    ]);
                }
            }

            // 6) Domain Group Permissions (Account Groups)
            // Remove existing group permissions for this user
            $user->domain_group_permissions()->delete();
            if (isset($validated['account_groups']) && is_array($validated['account_groups'])) {
                // Add new group permissions
                foreach ($validated['account_groups'] as $domainGroupUuid) {
                    $user->domain_group_permissions()->create([
                        'user_uuid'         => $user->user_uuid,
                        'domain_group_uuid' => $domainGroupUuid,
                    ]);
                }
            }

            // 7) Locations (polymorphic pivot)
            $user->locations()->detach(); // Remove existing links
            if (!empty($validated['locations']) && is_array($validated['locations'])) {
                foreach ($validated['locations'] as $locationUuid) {
                    $user->locations()->attach($locationUuid);
                }
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['User updated']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('User update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while updating.']]
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
                'messages' => ['error' => ['Access denied.']]
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
                // Delete related advanced fields
                $user->user_adv_fields()->delete();

                // Delete user settings
                $user->settings()->delete();

                // Delete group assignments
                $user->user_groups()->delete();

                $user->domain_permissions()->delete();       

                $user->domain_group_permissions()->delete();  

                // Finally delete the user record
                $user->delete();
            }

            // bulk-remove all location links for these users in one go
            DB::table('locationables')
            ->where('locationable_type', \App\Models\User::class)
            ->whereIn('locationable_id', $uuids)
            ->delete();

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected user(s) were deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('User bulkDelete error: '
                . $e->getMessage()
                . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected user(s).']]
            ], 500);
        }
    }

    public function getUserPermissions()
    {
        $permissions = [];
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
