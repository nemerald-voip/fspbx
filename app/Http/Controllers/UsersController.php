<?php

namespace App\Http\Controllers;


use App\Models\User;
use Inertia\Inertia;
use App\Data\UserData;
use App\Models\Groups;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Domain;
use App\Models\DomainGroups;

class UsersController extends Controller
{

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

        $users = QueryBuilder::for(User::class)
            // only users in the current domain
            ->where('domain_uuid', $currentDomain)
            ->select([
                'user_uuid',
                'username',
                'user_email',
                'user_enabled',
                'domain_uuid',
            ])
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
            ->defaultSort('username')
            ->paginate($perPage)
            ->appends($request->query());

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

        // 1) Base payload: either an existing user DTO or a “new user” stub
        if ($itemUuid) {
            $user = QueryBuilder::for(User::class)
                ->select([
                    'user_uuid',
                    'username',
                    'user_email',
                    'user_enabled',
                    'domain_uuid',
                ])
                ->allowedIncludes(['user_groups'])
                ->with([
                    'user_adv_fields:user_uuid,first_name,last_name',
                    'user_groups:user_uuid,user_group_uuid,group_uuid,group_name',
                ])
                ->whereKey($itemUuid)
                ->firstOrFail();

            $userDto = UserData::from($user);
            $updateRoute = route('users.update', ['user' => $itemUuid]);
        } else {
            // “New user” defaults
            $userDto     = new UserData(
                user_uuid: '',
                user_email: '',
                name_formatted: '',
                first_name: '',
                last_name: '',
                language: 'en-us',
                time_zone: get_local_time_zone(),
                user_enabled: true,
                domain_uuid: session('domain_uuid'),
            );
            $updateRoute = null;
        }

        // 2) Permissions array (you’ll have to implement this)
        $permissions = $this->getUserPermissions();

        $groups = Groups::where('domain_uuid', session('domain_uuid'))
            ->orWhere('domain_uuid', null)
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


        // 3) Any routes your front end needs
        $routes = [
            'store_route'  => route('users.store'),
            'update_route' => $updateRoute,
            'password_reset' => route('users.password.email'),
        ];

        return response()->json([
            'item'        => $userDto,
            'permissions' => $permissions,
            'routes'      => $routes,
            'timezones' => getGroupedTimezones(),
            'groups' => $groups,
            'domains' => $domains,
            'domain_groups' => $domain_groups,
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
                'user_enabled' => $data['user_enabled'] ? 'true' : 'false',
                'domain_uuid'  => $data['domain_uuid'] ?? null,
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

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['User created']],
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
            $user->update([
                'user_email'   => $validated['user_email'],
                'user_enabled' => $validated['user_enabled'] ? 'true' : 'false',
            ]);

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

                // Finally delete the user record
                $user->delete();
            }

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
        $permissions['user_group_view'] = !userCheckPermission('user_group_view');
        $permissions['user_group_edit'] = userCheckPermission('user_group_edit');
        $permissions['user_status'] = userCheckPermission('user_status');
        $permissions['user_view_managed_accounts'] = userCheckPermission('user_view_managed_accounts');
        $permissions['user_update_managed_accounts'] = userCheckPermission('user_update_managed_accounts');
        $permissions['user_view_managed_account_groups'] = userCheckPermission('user_view_managed_account_groups');
        $permissions['api_key'] = userCheckPermission('api_key');

        return $permissions;
    }
}
