<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainGroupRelations;
use App\Models\Extensions;
use App\Models\Groups;
use App\Models\Location;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\UserSessionInvalidationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;

class UserService
{
    public function __construct(
        private PermissionService $permissions,
        private UserSessionInvalidationService $sessions,
    ) {}

    public function query(string $domainUuid): Builder
    {
        return User::query()->where('domain_uuid', $domainUuid);
    }

    public function create(User $actor, string $domainUuid, array $data): User
    {
        $this->authorize($actor, 'user_add', $domainUuid);

        // Match the Users form's hidden default, including for API-created users.
        $data += ['language' => 'en-us'];
        // Use the target account's default, without relying on a web session.
        $data['time_zone'] ??= get_local_time_zone($domainUuid);

        return DB::transaction(function () use ($actor, $domainUuid, $data) {
            // Serialize creates in this account so simultaneous requests cannot bypass its limit.
            Domain::whereKey($domainUuid)->lockForUpdate()->firstOrFail();
            $limit = get_limit_setting('users', $domainUuid);
            if ($limit !== null && $this->query($domainUuid)->count() >= $limit) {
                throw ValidationException::withMessages(['users' => "You have reached the maximum number of Users allowed ({$limit})."]);
            }

            $user = new User();
            $user->domain_uuid = $domainUuid;
            $user->username = Str::slug($data['first_name'], '_')
                . (! empty($data['last_name']) ? '_'.Str::slug($data['last_name'], '_') : '');
            $user->user_enabled = 'true';
            $this->saveChanges($actor, $user, $data);

            return $user->refresh();
        });
    }

    public function update(User $actor, string $domainUuid, User $user, array $data): User
    {
        $this->authorize($actor, 'user_edit', $domainUuid);
        $updated = DB::transaction(function () use ($actor, $domainUuid, $user, $data) {
            $user = $this->query($domainUuid)->whereKey($user->user_uuid)->lockForUpdate()->firstOrFail();
            $this->ensureCanManageTarget($actor, $user, $domainUuid);
            $this->saveChanges($actor, $user, $data);

            return $user->refresh();
        });

        // Role, account, status, and identity changes must take effect in existing sessions too.
        $this->sessions->invalidateByUserUuids([$updated->user_uuid]);

        return $updated;
    }

    public function sendPasswordResetLink(User $actor, string $domainUuid, User $user): string
    {
        $this->authorize($actor, 'user_edit', $domainUuid);
        $this->ensureCanManageTarget($actor, $user, $domainUuid);

        if ($this->directoryManagement($user)['managed']) {
            throw ValidationException::withMessages([
                'user_uuid' => __('This user is managed by an external directory. Reset the password in the connected directory.'),
            ]);
        }

        validator(['user_email' => $user->user_email], ['user_email' => ['required', 'email']], [
            'user_email.required' => 'This user does not have a valid email address.',
            'user_email.email' => 'This user does not have a valid email address.',
        ])->validate();

        // Use the same broker and notification as the web reset flow, scoped to this user.
        return Password::broker()->sendResetLink([
            'user_uuid' => $user->user_uuid,
            'domain_uuid' => $domainUuid,
            'user_email' => $user->user_email,
        ]);
    }

    public function deleteMany(User $actor, string $domainUuid, array $userUuids): void
    {
        $this->authorize($actor, 'user_delete', $domainUuid);
        $deleted = DB::transaction(function () use ($actor, $domainUuid, $userUuids) {
            $users = $this->query($domainUuid)->whereIn('user_uuid', $userUuids)->lockForUpdate()->get();
            if ($users->count() !== count(array_unique($userUuids))) {
                throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(User::class);
            }

            foreach ($users as $user) {
                $this->ensureCanManageTarget($actor, $user, $domainUuid);
                if ($this->directoryManagement($user)['managed']) {
                    throw ValidationException::withMessages([
                        'user_uuid' => __('Directory-managed users cannot be deleted here. Disable or remove them in the connected directory.'),
                    ]);
                }
            }

            foreach ($users as $user) {
                $user->user_adv_fields()->delete();
                $user->settings()->delete();
                $user->user_groups()->delete();
                $user->domain_permissions()->delete();
                $user->domain_group_permissions()->delete();
                $user->locations()->detach();
                $user->tokens()->delete();
                $user->delete();
            }

            return $users->pluck('user_uuid');
        });

        $this->sessions->invalidateByUserUuids($deleted);
    }

    private function saveChanges(User $actor, User $user, array $data): void
    {
        $creating = ! $user->exists;
        $domainUuid = (string) $user->domain_uuid;
        $directory = $creating ? $this->emptyDirectoryManagement() : $this->directoryManagement($user);
        if ($directory['managed']) {
            $locked = ['first_name', 'last_name', 'user_enabled'];
            if ($directory['email_managed']) {
                $locked[] = 'user_email';
            }
            if ($directory['extension_managed']) {
                $locked[] = 'extension_uuid';
            }
            foreach ($locked as $field) {
                if (array_key_exists($field, $data)) {
                    throw ValidationException::withMessages([$field => 'This field is managed by the connected directory.']);
                }
            }
        }

        if (array_key_exists('extension_uuid', $data) && $data['extension_uuid'] !== null) {
            if (! Extensions::query()->where('domain_uuid', $domainUuid)->whereKey($data['extension_uuid'])->exists()) {
                throw ValidationException::withMessages(['extension_uuid' => 'The selected extension is invalid.']);
            }
        }

        if (array_key_exists('user_enabled', $data)) {
            $enabled = filter_var($data['user_enabled'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            if ($enabled !== $user->user_enabled) {
                $this->requirePermission($actor, 'user_status', $domainUuid);
            }
            $data['user_enabled'] = $enabled;
        }

        $managedGroups = collect($directory['managed_roles'])->pluck('value');
        $groups = null;
        if (array_key_exists('groups', $data)) {
            $localGroups = collect($data['groups'])->diff($managedGroups)->unique()->values();
            $desired = $localGroups->merge($managedGroups)->unique()->sort()->values()->all();
            $current = $creating ? [] : $user->user_groups()->pluck('group_uuid')->unique()->sort()->values()->all();
            if ($desired !== $current) {
                $this->requirePermission($actor, 'user_group_edit', $domainUuid);
                $groups = $this->allowedGroupsForActor($actor, $localGroups->all(), $domainUuid);
            }
        }

        foreach (['accounts' => 'user_update_managed_accounts', 'account_groups' => 'user_update_managed_account_groups'] as $field => $permission) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $relation = $field === 'accounts' ? 'domain_permissions' : 'domain_group_permissions';
            $column = $field === 'accounts' ? 'domain_uuid' : 'domain_group_uuid';
            $current = $creating ? [] : $user->$relation()->pluck($column)->sort()->values()->all();
            $desired = collect($data[$field])->unique()->sort()->values()->all();
            if ($current !== $desired) {
                $this->requirePermission($actor, $permission, $domainUuid);
                $domains = $field === 'accounts' ? $desired : DomainGroupRelations::query()
                    ->whereIn('domain_group_uuid', $desired)->pluck('domain_uuid')->all();
                foreach ($domains as $assignedDomain) {
                    if (! $this->permissions->userCanAccessDomain($actor, $assignedDomain)) {
                        throw new AuthorizationException('You do not have access to one or more selected accounts.');
                    }
                }
                // Delegate only account groups the actor controls, including future membership changes.
                if ($field === 'account_groups' && ! $this->permissions->userHasPermission($actor, 'domain_all')) {
                    $assignedGroups = $actor->domain_group_permissions()->pluck('domain_group_uuid');
                    if (collect($desired)->diff($assignedGroups)->isNotEmpty()) {
                        throw new AuthorizationException('You do not have access to one or more selected account groups.');
                    }
                }
            }
        }

        if (isset($data['locations'])) {
            $count = Location::query()->where('domain_uuid', $domainUuid)->whereKey($data['locations'])->count();
            if ($count !== count(array_unique($data['locations']))) {
                throw ValidationException::withMessages(['locations' => 'One or more selected locations are invalid.']);
            }
        }

        $attributes = Arr::only($data, ['user_email', 'user_enabled']);
        if (Schema::hasColumn('v_users', 'extension_uuid') && array_key_exists('extension_uuid', $data)) {
            $attributes['extension_uuid'] = $data['extension_uuid'];
        }
        $user->fill($attributes)->save();

        $names = Arr::only($data, ['first_name', 'last_name']);
        if ($names !== []) {
            $user->user_adv_fields()->updateOrCreate(['user_uuid' => $user->user_uuid], $names);
        }
        foreach (['language', 'time_zone'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $user->settings()->updateOrCreate([
                'domain_uuid' => $domainUuid,
                'user_setting_category' => 'domain',
                'user_setting_subcategory' => $field,
            ], [
                'user_setting_name' => $field === 'language' ? 'code' : 'name',
                'user_setting_value' => $data[$field],
                'user_setting_enabled' => true,
            ]);
        }

        if ($groups !== null) {
            $user->user_groups()->whereNotIn('group_uuid', $managedGroups)->delete();
            foreach ($groups as $group) {
                $user->user_groups()->create([
                    'domain_uuid' => $domainUuid,
                    'group_uuid' => $group->group_uuid,
                    'group_name' => $group->group_name,
                ]);
            }
        }
        foreach (['accounts' => ['domain_permissions', 'domain_uuid'], 'account_groups' => ['domain_group_permissions', 'domain_group_uuid']] as $field => [$relation, $column]) {
            if (array_key_exists($field, $data)) {
                $user->$relation()->delete();
                foreach (array_unique($data[$field]) as $uuid) {
                    $user->$relation()->create([$column => $uuid]);
                }
            }
        }
        if (array_key_exists('locations', $data)) {
            $user->locations()->sync($data['locations'] ?? []);
        }
    }

    private function authorize(User $actor, string $permission, string $domainUuid): void
    {
        if (! $this->permissions->userCanAccessDomain($actor, $domainUuid)) {
            throw new AuthorizationException('You do not have access to this domain.');
        }
        $this->requirePermission($actor, $permission, $domainUuid);
    }

    private function requirePermission(User $actor, string $permission, string $domainUuid): void
    {
        if (! $this->permissions->userHasPermission($actor, $permission, $domainUuid)) {
            throw new AuthorizationException("Missing permission: {$permission}.");
        }
    }

    public function actorLevel(User $actor): int
    {
        return (int) $actor->roles()->wherePivot('domain_uuid', $actor->domain_uuid)->max('group_level');
    }

    public function isSuperadmin(User $actor): bool
    {
        return $actor->roles()->wherePivot('domain_uuid', $actor->domain_uuid)
            ->superadmins()->where('group_level', '>=', 80)->exists();
    }

    public function canManageTarget(User $actor, User $user, string $domainUuid): bool
    {
        if ($user->domain_uuid !== $domainUuid) {
            return false;
        }
        if ($this->isSuperadmin($actor)) {
            return true;
        }

        return ! $user->roles()->protectedFrom($this->actorLevel($actor))->exists();
    }

    public function ensureCanManageTarget(User $actor, User $user, string $domainUuid): void
    {
        if (! $this->canManageTarget($actor, $user, $domainUuid)) {
            throw new AuthorizationException(__('You are not allowed to manage this user.'));
        }
    }

    public function allowedGroupsForActor(User $actor, array $groupUuids, string $domainUuid): Collection
    {
        $groups = Groups::query()->whereIn('group_uuid', $groupUuids)
            ->where(fn ($query) => $query->whereNull('domain_uuid')->orWhere('domain_uuid', $domainUuid))
            ->where('group_level', '<=', $this->actorLevel($actor))->get();

        if ($groups->count() !== count(array_unique($groupUuids))) {
            throw new AuthorizationException(__('One or more selected groups are not allowed.'));
        }
        if (! $this->isSuperadmin($actor) && $groups->contains(fn ($group) => strtolower($group->group_name) === 'superadmin')) {
            throw new AuthorizationException(__('You are not allowed to assign the superadmin group.'));
        }

        return $groups->keyBy('group_uuid');
    }

    public function emptyDirectoryManagement(): array
    {
        return [
            'managed' => false, 'directory_name' => null, 'manage_groups_locally' => true,
            'email_managed' => false, 'managed_roles' => [], 'extension_managed' => false, 'remote_extension' => null,
        ];
    }

    public function directoryManagement(User $user): array
    {
        if (! Schema::hasTable('ldap_directory_users')) {
            return $this->emptyDirectoryManagement();
        }

        $link = QueryBuilder::for($user->ldapDirectoryUsers())
            ->where('domain_uuid', $user->domain_uuid)
            ->with('directory:directory_uuid,name,manage_groups_locally')
            ->orderByDirectoryPriority()
            ->first(['directory_user_uuid', 'directory_uuid', 'email', 'extension']);
        if (! $link) {
            return $this->emptyDirectoryManagement();
        }

        $directory = $link->directory;
        $manageGroupsLocally = $directory?->manage_groups_locally ?? false;
        $managedRoles = [];
        if (! $manageGroupsLocally && Schema::hasTable('ldap_directory_user_group_assignments')) {
            $managedRoles = $link->managedRoles()->orderBy('group_name')->get()
                ->map(fn (Groups $group) => ['value' => $group->group_uuid, 'label' => $group->group_name])->all();
        }

        return [
            'managed' => true, 'directory_name' => $directory?->name,
            'manage_groups_locally' => $manageGroupsLocally,
            'email_managed' => filter_var($link->email, FILTER_VALIDATE_EMAIL) !== false,
            'managed_roles' => $managedRoles, 'extension_managed' => filled($link->extension),
            'remote_extension' => $link->extension,
        ];
    }
}
