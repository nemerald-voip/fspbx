<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLdapDirectoryRequest;
use App\Jobs\SyncLdapDirectory;
use App\Models\Groups;
use App\Models\LdapDirectory;
use App\Services\ActiveDirectoryClient;
use App\Services\Auth\UserSessionInvalidationService;
use App\Services\LdapDirectoryDeletionService;
use App\Services\LdapDirectorySyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class LdapDirectoryController extends Controller
{
    public function index(): JsonResponse
    {
        abort_unless(userCheckPermission('ldap_directory_view'), 403);

        return response()->json(['directories' => $this->directories()]);
    }

    public function accountSettingsProps(): ?array
    {
        if (! userCheckPermission('ldap_directory_view')) {
            return null;
        }

        return [
            'directories' => $this->directories(),
            'defaults' => $this->defaults(),
            'routes' => [
                'index' => route('ldap-directories.index'),
                'store' => route('ldap-directories.store'),
            ],
            'permissions' => $this->permissions(),
        ];
    }

    public function store(StoreLdapDirectoryRequest $request): JsonResponse
    {
        $directory = new LdapDirectory();
        $directory->fill($request->validated());
        $directory->domain_uuid = session('domain_uuid');
        $directory->next_sync_at = $directory->enabled ? now() : null;
        $directory->save();

        $connection = $this->testAndRecord($directory);

        return response()->json([
            'message' => $connection['status'] === 'connected'
                ? __('Directory connection created and tested successfully.')
                : __('Directory connection created, but the connection test failed: :message', ['message' => $connection['message']]),
            'connection_status' => $connection['status'],
            'directory_uuid' => $directory->directory_uuid,
            'redirect' => route('account-settings.index', [
                'section' => 'active_directory',
                'directory' => $directory->directory_uuid,
            ]),
        ], 201);
    }

    public function update(StoreLdapDirectoryRequest $request, string $directory): JsonResponse
    {
        $record = $this->directory($directory);
        $values = $request->validated();
        if (blank($values['bind_password'] ?? null)) {
            unset($values['bind_password']);
        }

        $wasEnabled = $record->enabled;
        $wasManagingGroupsLocally = $record->manage_groups_locally;
        $record->fill($values);
        if ($record->enabled && ! $record->hasBindPassword()) {
            return response()->json(['errors' => ['bind_password' => [__('A bind password is required before enabling this directory.')]]], 422);
        }
        if ($record->enabled && ! $wasEnabled) {
            $record->next_sync_at = now();
        } elseif (! $record->enabled) {
            $record->next_sync_at = null;
        }
        $record->save();

        $connection = $this->testAndRecord($record);

        if ($wasManagingGroupsLocally && ! $record->manage_groups_locally) {
            app(LdapDirectorySyncService::class)->applyMappedLocalGroups($record);
        }

        return response()->json([
            'message' => $connection['status'] === 'connected'
                ? __('Directory connection updated and tested successfully.')
                : __('Directory connection updated, but the connection test failed: :message', ['message' => $connection['message']]),
            'connection_status' => $connection['status'],
            'directory_uuid' => $record->directory_uuid,
        ]);
    }

    public function destroy(string $directory): JsonResponse
    {
        abort_unless(userCheckPermission('ldap_directory_delete'), 403);
        $record = $this->directory($directory);
        $lock = Cache::lock('ldap-directory-sync:' . $record->directory_uuid, 900);

        if (! $lock->get()) {
            return response()->json([
                'message' => __('This directory is synchronizing. Try deleting it again after synchronization finishes.'),
            ], 409);
        }

        try {
            $deletedGroupsCount = $record->directoryGroups()->count();
            $deletedUserUuids = app(LdapDirectoryDeletionService::class)->delete($record);

            if ($deletedUserUuids->isNotEmpty()) {
                app(UserSessionInvalidationService::class)->invalidateByUserUuids($deletedUserUuids);
            }

            $users = trans_choice(':count imported user|:count imported users', $deletedUserUuids->count(), [
                'count' => $deletedUserUuids->count(),
            ]);
            $groups = trans_choice(':count imported group|:count imported groups', $deletedGroupsCount, [
                'count' => $deletedGroupsCount,
            ]);

            return response()->json([
                'message' => __('Directory connection deleted. :users and :groups were deleted.', [
                    'users' => $users,
                    'groups' => $groups,
                ]),
                'deleted_users_count' => $deletedUserUuids->count(),
                'deleted_groups_count' => $deletedGroupsCount,
            ]);
        } finally {
            $lock->release();
        }
    }

    public function test(string $directory): JsonResponse
    {
        abort_unless(userCheckPermission('ldap_directory_test'), 403);
        $record = $this->directory($directory);

        $result = $this->testAndRecord($record);

        if ($result['status'] === 'connected') {
            return response()->json(['message' => __('Connection successful.'), 'status' => 'connected']);
        }

        return response()->json(['message' => $result['message'], 'status' => 'failed'], 422);
    }

    public function sync(string $directory): JsonResponse
    {
        abort_unless(userCheckPermission('ldap_directory_sync'), 403);
        $record = $this->directory($directory);
        abort_unless($record->enabled, 422, __('Enable the directory before synchronizing it.'));
        $record->forceFill(['next_sync_at' => now()->addMinutes($record->sync_interval_minutes)])->save();
        SyncLdapDirectory::dispatch($record->directory_uuid);

        return response()->json(['message' => __('Synchronization queued.')], 202);
    }

    public function mappings(string $directory): JsonResponse
    {
        abort_unless(userCheckPermission('ldap_directory_map_groups'), 403);
        $record = $this->directory($directory);

        return response()->json($this->mappingData($record));
    }

    public function groupMembers(string $directory, string $group): JsonResponse
    {
        abort_unless(userCheckPermission('ldap_directory_map_groups'), 403);
        $record = $this->directory($directory);
        $directoryGroup = $record->directoryGroups()->whereKey($group)->firstOrFail();

        $members = DB::table('ldap_directory_group_members as memberships')
            ->join('ldap_directory_users as directory_users', 'directory_users.directory_user_uuid', '=', 'memberships.directory_user_uuid')
            ->leftJoin('v_users as users', function ($join) {
                $join->on('users.user_uuid', '=', 'directory_users.user_uuid')
                    ->on('users.domain_uuid', '=', 'directory_users.domain_uuid');
            })
            ->where('memberships.directory_group_uuid', $directoryGroup->directory_group_uuid)
            ->where('directory_users.directory_uuid', $record->directory_uuid)
            ->where('directory_users.domain_uuid', $record->domain_uuid)
            ->orderBy('directory_users.display_name')
            ->orderBy('directory_users.username')
            ->get([
                'directory_users.directory_user_uuid',
                'directory_users.user_uuid',
                'directory_users.username',
                DB::raw('COALESCE(directory_users.email, users.user_email) as email'),
                'directory_users.first_name',
                'directory_users.last_name',
                'directory_users.display_name',
                'directory_users.extension',
                'directory_users.remote_enabled',
            ])
            ->map(function ($member) {
                $name = trim((string) $member->display_name);

                if ($name === '') {
                    $name = trim(implode(' ', array_filter([$member->first_name, $member->last_name])));
                }

                return [
                    'directory_user_uuid' => $member->directory_user_uuid,
                    'user_uuid' => $member->user_uuid,
                    'name' => $name !== '' ? $name : $member->username,
                    'username' => $member->username,
                    'email' => $member->email,
                    'extension' => $member->extension,
                    'enabled' => filter_var($member->remote_enabled, FILTER_VALIDATE_BOOLEAN),
                ];
            })
            ->sortBy(fn ($member) => mb_strtolower((string) $member['name']))
            ->values();

        return response()->json([
            'group' => [
                'directory_group_uuid' => $directoryGroup->directory_group_uuid,
                'name' => $directoryGroup->name,
                'description' => $directoryGroup->description,
            ],
            'members' => $members,
            'member_count' => $members->count(),
        ]);
    }

    public function updateMappings(Request $request, string $directory): JsonResponse
    {
        abort_unless(userCheckPermission('ldap_directory_map_groups'), 403);
        $record = $this->directory($directory);

        if ($record->manage_groups_locally) {
            return response()->json([
                'message' => __('Set Manage groups locally to No before mapping directory groups to local roles.'),
            ], 422);
        }

        $values = $request->validate(['mappings' => ['present', 'array'], 'mappings.*' => ['nullable', 'uuid']]);
        $directoryGroups = $record->directoryGroups()->pluck('directory_group_uuid');
        $allowedLocalGroups = $this->localGroups()->pluck('group_uuid');

        DB::transaction(function () use ($record, $values, $directoryGroups, $allowedLocalGroups) {
            DB::table('ldap_directory_group_mappings')->where('directory_uuid', $record->directory_uuid)->delete();
            foreach ($values['mappings'] as $directoryGroupUuid => $localGroupUuid) {
                abort_unless($directoryGroups->contains($directoryGroupUuid), 422);
                if (blank($localGroupUuid)) {
                    continue;
                }
                abort_unless($allowedLocalGroups->contains($localGroupUuid), 403);
                DB::table('ldap_directory_group_mappings')->insert([
                    'directory_uuid' => $record->directory_uuid,
                    'directory_group_uuid' => $directoryGroupUuid,
                    'group_uuid' => $localGroupUuid,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        app(LdapDirectorySyncService::class)->applyMappedLocalGroups($record);

        return response()->json([
            'message' => __('Group mappings updated and applied.'),
        ]);
    }

    private function directory(string $uuid): LdapDirectory
    {
        return LdapDirectory::query()->where('domain_uuid', session('domain_uuid'))->whereKey($uuid)->firstOrFail();
    }

    private function directories()
    {
        $directories = LdapDirectory::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->withCount(['directoryUsers', 'directoryGroups'])
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        $ownedUserCounts = DB::table('v_users')
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('add_user', $directories->map(fn (LdapDirectory $directory) => 'ldap:' . $directory->directory_uuid))
            ->select(['add_user'])
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('add_user')
            ->pluck('aggregate', 'add_user');

        return $directories
            ->map(function (LdapDirectory $directory) use ($ownedUserCounts) {
                $directory->append('has_bind_password');

                return $directory->toArray() + [
                    'owned_users_count' => (int) ($ownedUserCounts['ldap:' . $directory->directory_uuid] ?? 0),
                    'routes' => [
                        'update' => route('ldap-directories.update', $directory->directory_uuid),
                        'test' => route('ldap-directories.test', $directory->directory_uuid),
                        'sync' => route('ldap-directories.sync', $directory->directory_uuid),
                        'destroy' => route('ldap-directories.destroy', $directory->directory_uuid),
                        'mappings' => route('ldap-directories.mappings', $directory->directory_uuid),
                    ],
                ];
            });
    }

    private function mappingData(LdapDirectory $directory): array
    {
        $directoryGroups = $directory->directoryGroups()
            ->select(['directory_group_uuid', 'directory_uuid', 'name', 'description'])
            ->withCount('directoryUsers')
            ->orderBy('name')
            ->get()
            ->map(function ($group) use ($directory) {
                return $group->toArray() + [
                    'routes' => [
                        'members' => route('ldap-directories.groups.members', [
                            'directory' => $directory->directory_uuid,
                            'group' => $group->directory_group_uuid,
                        ]),
                    ],
                ];
            });

        return [
            'directory_groups' => $directoryGroups,
            'local_groups' => $this->localGroups(),
            'mappings' => DB::table('ldap_directory_group_mappings')->where('directory_uuid', $directory->directory_uuid)->pluck('group_uuid', 'directory_group_uuid'),
            'manage_groups_locally' => $directory->manage_groups_locally,
        ];
    }

    private function localGroups()
    {
        return Groups::query()->where(fn ($query) => $query->whereNull('domain_uuid')->orWhere('domain_uuid', session('domain_uuid')))
            ->when(! isSuperAdmin(), fn ($query) => $query->where('group_level', '<=', (int) (session('user.group_level') ?? 0)))
            ->when(! isSuperAdmin(), fn ($query) => $query->whereRaw('LOWER(group_name) != ?', ['superadmin']))
            ->orderBy('group_name')->get(['group_uuid', 'group_name', 'group_description']);
    }

    private function permissions(): array
    {
        return collect(['view', 'create', 'update', 'delete', 'test', 'sync', 'map_groups'])
            ->mapWithKeys(fn ($action) => [$action => userCheckPermission('ldap_directory_' . $action)])->all();
    }

    private function defaults(): array
    {
        return [
            'type' => 'active_directory', 'enabled' => false, 'priority' => 100, 'sync_interval_minutes' => 60,
            'secure_connection' => 'ldaps', 'port' => 636, 'create_missing_extensions' => 'none', 'manage_groups_locally' => false,
            'common_name_attribute' => 'cn', 'description_attribute' => 'description', 'unique_identifier_attribute' => 'objectGUID',
            'user_dn' => '', 'user_object_class' => 'user', 'user_object_filter' => '(&(objectCategory=Person)(sAMAccountName=*))',
            'user_name_attribute' => 'sAMAccountName', 'user_first_name_attribute' => 'givenName', 'user_last_name_attribute' => 'sn',
            'user_display_name_attribute' => 'displayName', 'user_group_attribute' => 'memberOf', 'user_email_attribute' => 'mail',
            'user_title_attribute' => 'title', 'user_company_attribute' => 'company', 'user_department_attribute' => 'department',
            'user_home_phone_attribute' => 'homePhone', 'user_work_phone_attribute' => 'telephoneNumber', 'user_cell_phone_attribute' => 'mobile',
            'user_fax_attribute' => 'facsimileTelephoneNumber', 'user_extension_attribute' => 'ipPhone',
            'group_dn' => '', 'group_object_class' => 'group', 'group_object_filter' => '(objectCategory=Group)', 'group_members_attribute' => 'member',
        ];
    }

    private function testAndRecord(LdapDirectory $directory): array
    {
        try {
            $result = (new ActiveDirectoryClient($directory))->test();
            $message = 'Connected to ' . $result['host'];
            $directory->forceFill(['connection_status' => 'connected', 'connection_message' => $message, 'connection_tested_at' => now()])->save();

            return ['status' => 'connected', 'message' => $message];
        } catch (Throwable $e) {
            $directory->forceFill(['connection_status' => 'failed', 'connection_message' => $e->getMessage(), 'connection_tested_at' => now()])->save();

            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }
}
