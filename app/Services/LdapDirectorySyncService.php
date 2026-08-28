<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Extensions;
use App\Models\Groups;
use App\Models\FusionCache;
use App\Models\LdapDirectory;
use App\Models\LdapDirectoryGroup;
use App\Models\LdapDirectoryUser;
use App\Models\LdapSyncRun;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Voicemails;
use App\Rules\UniqueExtension;
use App\Services\Auth\UserSessionInvalidationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LdapDirectorySyncService
{
    private array $messages = [];

    public function applyMappedLocalGroups(LdapDirectory $directory): int
    {
        if ($directory->manage_groups_locally) {
            return 0;
        }

        $directoryUsers = LdapDirectoryUser::query()
            ->where('directory_uuid', $directory->directory_uuid)
            ->whereNotNull('user_uuid')
            ->get();

        DB::transaction(function () use ($directoryUsers) {
            foreach ($directoryUsers as $directoryUser) {
                $this->syncMappedLocalGroups($directoryUser);
            }
        });

        $userUuids = $directoryUsers->pluck('user_uuid')->filter()->unique()->values();

        if ($userUuids->isNotEmpty()) {
            app(UserSessionInvalidationService::class)->invalidateByUserUuids($userUuids);
        }

        return $userUuids->count();
    }

    public function sync(LdapDirectory $directory, bool $dryRun = false, bool $allowLargeRemoval = false): LdapSyncRun
    {
        $run = LdapSyncRun::create([
            'directory_uuid' => $directory->directory_uuid,
            'domain_uuid' => $directory->domain_uuid,
            'status' => 'running',
            'dry_run' => $dryRun,
            'started_at' => now(),
        ]);

        try {
            $domain = Domain::query()->find($directory->domain_uuid);

            if (! $domain || ! filter_var($domain->domain_enabled, FILTER_VALIDATE_BOOLEAN)) {
                throw new RuntimeException('The account for this directory is disabled or no longer exists.');
            }

            $client = new ActiveDirectoryClient($directory);
            $remoteUsers = $client->users();
            $remoteGroups = $client->groups();

            if ($remoteUsers === []) {
                throw new RuntimeException('The directory returned no users. No local records were changed.');
            }

            $linkedCount = LdapDirectoryUser::query()
                ->where('directory_uuid', $directory->directory_uuid)
                ->whereNotNull('user_uuid')
                ->count();

            if (! $allowLargeRemoval && $linkedCount >= 10 && count($remoteUsers) < (int) ceil($linkedCount * 0.5)) {
                throw new RuntimeException(
                    'The directory returned less than half of the previously linked users. '
                    . 'Review the Base DN and user filter, then use the explicit large-removal override if the reduction is intended.'
                );
            }

            if ($dryRun) {
                return $this->finishRun($run, 'completed', [
                    'users_seen' => count($remoteUsers),
                    'groups_seen' => count($remoteGroups),
                    'messages' => ['Directory query completed. Dry run did not change local records.'],
                ]);
            }

            $stats = DB::transaction(function () use ($directory, $domain, $remoteUsers, $remoteGroups) {
                return $this->apply($directory, $domain, $remoteUsers, $remoteGroups);
            });

            $directory->forceFill([
                'last_sync_status' => 'completed',
                'last_sync_message' => $this->messages === [] ? 'Synchronization completed.' : implode(' ', $this->messages),
                'last_sync_at' => now(),
                'next_sync_at' => now()->addMinutes($directory->sync_interval_minutes),
            ])->save();

            return $this->finishRun($run, 'completed', $stats + ['messages' => $this->messages]);
        } catch (Throwable $e) {
            $directory->forceFill([
                'last_sync_status' => 'failed',
                'last_sync_message' => $e->getMessage(),
                'last_sync_at' => now(),
                'next_sync_at' => now()->addMinutes($directory->sync_interval_minutes),
            ])->save();

            $this->finishRun($run, 'failed', ['messages' => [$e->getMessage()]]);
            throw $e;
        }
    }

    private function apply(LdapDirectory $directory, Domain $domain, array $remoteUsers, array $remoteGroups): array
    {
        $seenAt = now();
        $stats = [
            'users_seen' => 0,
            'users_created' => 0,
            'users_updated' => 0,
            'users_disabled' => 0,
            'users_conflicted' => 0,
            'groups_seen' => 0,
        ];

        $groupEntries = [];
        $groupsByDn = [];
        $groupsByPrimaryToken = [];

        foreach ($remoteGroups as $entry) {
            $externalId = ActiveDirectoryClient::externalId(
                ActiveDirectoryClient::first($entry, $directory->unique_identifier_attribute)
            );
            $name = ActiveDirectoryClient::first($entry, $directory->common_name_attribute);

            if (! $externalId || ! $name) {
                $this->messages[] = 'A group without its unique identifier or common name was skipped.';
                continue;
            }

            $group = LdapDirectoryGroup::query()->updateOrCreate(
                ['directory_uuid' => $directory->directory_uuid, 'external_id' => $externalId],
                [
                    'domain_uuid' => $directory->domain_uuid,
                    'distinguished_name' => $entry['dn'] ?? null,
                    'name' => $name,
                    'description' => ActiveDirectoryClient::first($entry, $directory->description_attribute),
                    'primary_group_token' => ActiveDirectoryClient::first($entry, 'primaryGroupToken'),
                    'local' => false,
                    'last_seen_at' => $seenAt,
                ]
            );

            $groupEntries[$group->directory_group_uuid] = $entry;
            $dn = $this->normalizeDn($group->distinguished_name);

            if ($dn !== '') {
                $groupsByDn[$dn] = $group;
            }

            if (filled($group->primary_group_token)) {
                $groupsByPrimaryToken[(string) $group->primary_group_token] = $group;
            }

            $stats['groups_seen']++;
        }

        $usersByDn = [];
        $userGroupDns = [];
        $userPrimaryTokens = [];
        $groupMembershipsByUser = [];
        $seenExternalIds = [];
        $changedUserUuids = [];

        foreach ($remoteUsers as $entry) {
            $externalId = ActiveDirectoryClient::externalId(
                ActiveDirectoryClient::first($entry, $directory->unique_identifier_attribute)
            );
            $username = ActiveDirectoryClient::first($entry, $directory->user_name_attribute);

            if (! $externalId || ! $username) {
                $stats['users_conflicted']++;
                $this->messages[] = 'A user without its unique identifier or username was skipped.';
                continue;
            }

            $seenExternalIds[] = $externalId;
            $stats['users_seen']++;

            $profile = $this->userProfile($directory, $entry);
            $directoryUser = LdapDirectoryUser::query()->firstOrNew([
                'directory_uuid' => $directory->directory_uuid,
                'external_id' => $externalId,
            ]);
            $previousDirectoryEmail = $profile['email'] === null
                && $directoryUser->exists
                && filter_var($directoryUser->email, FILTER_VALIDATE_EMAIL)
                    ? strtolower((string) $directoryUser->email)
                    : null;

            $directoryUser->fill([
                'domain_uuid' => $directory->domain_uuid,
                'distinguished_name' => $entry['dn'] ?? null,
                'username' => $username,
                'email' => $profile['email'],
                'first_name' => $profile['first_name'],
                'last_name' => $profile['last_name'],
                'display_name' => $profile['display_name'],
                'extension' => $profile['extension'],
                'remote_enabled' => $profile['remote_enabled'],
                'profile' => $profile,
                'last_seen_at' => $seenAt,
            ]);

            $localUser = $this->resolveLocalUser($directory, $directoryUser, $profile);

            if (! $localUser) {
                $stats['users_conflicted']++;
                continue;
            }

            $wasLinked = filled($directoryUser->user_uuid);
            $directoryUser->user_uuid = $localUser->user_uuid;
            $directoryUser->save();

            $this->projectUser($directory, $domain, $directoryUser, $localUser, $profile, $previousDirectoryEmail);
            $changedUserUuids[] = $localUser->user_uuid;

            if ($wasLinked) {
                $stats['users_updated']++;
            } else {
                $stats['users_created']++;
            }

            $dn = $this->normalizeDn($directoryUser->distinguished_name);

            if ($dn !== '') {
                $usersByDn[$dn] = $directoryUser;
            }

            $userGroupDns[$directoryUser->directory_user_uuid] = collect(
                ActiveDirectoryClient::values($entry, $directory->user_group_attribute)
            )->map(fn($value) => $this->normalizeDn($value))->filter()->all();
            $userPrimaryTokens[$directoryUser->directory_user_uuid] = ActiveDirectoryClient::first($entry, 'primaryGroupID');
        }

        foreach ($groupEntries as $directoryGroupUuid => $entry) {
            $memberUserUuids = collect(ActiveDirectoryClient::values($entry, $directory->group_members_attribute))
                ->map(fn($value) => $this->normalizeDn($value))
                ->map(fn($dn) => $usersByDn[$dn]->directory_user_uuid ?? null)
                ->filter()
                ->unique()
                ->values();

            foreach ($memberUserUuids as $directoryUserUuid) {
                $groupMembershipsByUser[$directoryUserUuid][] = $directoryGroupUuid;
            }
        }

        foreach ($usersByDn as $directoryUser) {
            $userAttributeGroupUuids = collect($userGroupDns[$directoryUser->directory_user_uuid] ?? [])
                ->map(fn($dn) => $groupsByDn[$dn]->directory_group_uuid ?? null)
                ->filter()
                ->all();
            $primaryToken = $userPrimaryTokens[$directoryUser->directory_user_uuid] ?? null;
            $primaryGroupUuid = null;

            if ($primaryToken !== null && isset($groupsByPrimaryToken[(string) $primaryToken])) {
                $primaryGroupUuid = $groupsByPrimaryToken[(string) $primaryToken]->directory_group_uuid;
            }

            $desiredGroupUuids = $this->mergeGroupMemberships(
                $groupMembershipsByUser[$directoryUser->directory_user_uuid] ?? [],
                $userAttributeGroupUuids,
                $primaryGroupUuid
            );

            $directoryUser->directoryGroups()->sync($desiredGroupUuids);
            if (! $directory->manage_groups_locally) {
                $this->syncMappedLocalGroups($directoryUser);
            }
        }

        $missingUsers = LdapDirectoryUser::query()
            ->where('directory_uuid', $directory->directory_uuid)
            ->whereNotIn('external_id', $seenExternalIds)
            ->whereNotNull('user_uuid')
            ->get();

        foreach ($missingUsers as $missingUser) {
            $localUser = $missingUser->user;

            if (! $directory->manage_groups_locally) {
                $this->removeManagedLocalGroups($missingUser);
            }

            if ($localUser && $localUser->user_enabled !== 'false') {
                $localUser->user_enabled = 'false';
                $localUser->save();
                $changedUserUuids[] = $localUser->user_uuid;
                $stats['users_disabled']++;
            }
        }

        $staleGroups = LdapDirectoryGroup::query()
            ->where('directory_uuid', $directory->directory_uuid)
            ->where('local', false)
            ->where('last_seen_at', '<', $seenAt)
            ->get();

        foreach ($staleGroups as $staleGroup) {
            DB::table('ldap_directory_group_members')->where('directory_group_uuid', $staleGroup->directory_group_uuid)->delete();
            DB::table('ldap_directory_group_mappings')->where('directory_group_uuid', $staleGroup->directory_group_uuid)->delete();
            $staleGroup->delete();
        }

        if ($changedUserUuids !== []) {
            app(UserSessionInvalidationService::class)->invalidateByUserUuids(array_values(array_unique($changedUserUuids)));
        }

        return $stats;
    }

    private function resolveLocalUser(LdapDirectory $directory, LdapDirectoryUser $directoryUser, array $profile): ?User
    {
        if ($directoryUser->user_uuid) {
            $linkedUser = User::query()
                ->where('domain_uuid', $directory->domain_uuid)
                ->find($directoryUser->user_uuid);

            if ($linkedUser) {
                return $linkedUser;
            }

            // The local user may be intentionally deleted while the remote
            // directory entry still exists. Clear that stale projection link
            // so this same sync can match or recreate the local user.
            $this->removeManagedLocalGroups($directoryUser);
            $directoryUser->user_uuid = null;
            $directoryUser->save();
            $this->messages[] = "{$profile['username']} was relinked because its local user no longer existed.";
        }

        $email = filled($profile['email']) ? strtolower((string) $profile['email']) : null;
        $emailOwner = $email === null
            ? null
            : User::query()->whereRaw('LOWER(user_email) = ?', [$email])->first();

        if ($emailOwner && $emailOwner->domain_uuid !== $directory->domain_uuid) {
            $this->messages[] = "{$profile['username']} was skipped because its email belongs to another account.";
            return null;
        }

        $user = $emailOwner ?: User::query()
            ->where('domain_uuid', $directory->domain_uuid)
            ->whereRaw('LOWER(username) = ?', [strtolower($profile['username'])])
            ->first();

        if ($user) {
            $alreadyManaged = LdapDirectoryUser::query()
                ->where('user_uuid', $user->user_uuid)
                ->where('directory_uuid', '!=', $directory->directory_uuid)
                ->exists();

            if ($alreadyManaged) {
                $this->messages[] = "{$profile['username']} is already managed by another directory.";
                return null;
            }

            return $user;
        }

        return User::query()->create([
            'username' => $profile['username'],
            'user_email' => $email,
            'password' => Hash::make(Str::random(64)),
            'domain_uuid' => $directory->domain_uuid,
            'user_enabled' => $profile['remote_enabled'] ? 'true' : 'false',
            'add_user' => 'ldap:' . $directory->directory_uuid,
        ]);
    }

    private function projectUser(
        LdapDirectory $directory,
        Domain $domain,
        LdapDirectoryUser $directoryUser,
        User $user,
        array $profile,
        ?string $previousDirectoryEmail = null
    ): void {
        $usernameOwner = User::query()
            ->where('domain_uuid', $directory->domain_uuid)
            ->whereRaw('LOWER(username) = ?', [strtolower($profile['username'])])
            ->where('user_uuid', '!=', $user->user_uuid)
            ->exists();

        if (! $usernameOwner) {
            $user->username = $profile['username'];
        }

        if (filled($profile['email'])) {
            $emailOwner = User::query()
                ->whereRaw('LOWER(user_email) = ?', [strtolower((string) $profile['email'])])
                ->where('user_uuid', '!=', $user->user_uuid)
                ->exists();

            if (! $emailOwner) {
                $user->user_email = strtolower((string) $profile['email']);
            }
        } elseif (
            $previousDirectoryEmail !== null
            && strtolower((string) $user->user_email) === $previousDirectoryEmail
        ) {
            $user->user_email = null;
        }

        $user->user_enabled = $profile['remote_enabled'] ? 'true' : 'false';
        $this->linkExtension($directory, $domain, $directoryUser, $user, $profile);
        $user->save();

        $user->user_adv_fields()->updateOrCreate(
            ['user_uuid' => $user->user_uuid],
            ['first_name' => $profile['first_name'], 'last_name' => $profile['last_name']]
        );

        foreach (['language' => get_domain_setting('language', $directory->domain_uuid) ?: 'en-us',
                     'time_zone' => get_local_time_zone($directory->domain_uuid)] as $field => $value) {
            $user->settings()->firstOrCreate(
                [
                    'domain_uuid' => $directory->domain_uuid,
                    'user_setting_category' => 'domain',
                    'user_setting_subcategory' => $field,
                ],
                [
                    'user_setting_name' => $field === 'language' ? 'code' : 'name',
                    'user_setting_value' => $value,
                    'user_setting_enabled' => true,
                ]
            );
        }
    }

    private function linkExtension(
        LdapDirectory $directory,
        Domain $domain,
        LdapDirectoryUser $directoryUser,
        User $user,
        array $profile
    ): void {
        $number = trim((string) $profile['extension']);

        if ($number === '') {
            return;
        }

        $extension = Extensions::query()
            ->where('domain_uuid', $directory->domain_uuid)
            ->where('extension', $number)
            ->first();

        if (! $extension && $directory->create_missing_extensions === 'default') {
            $extension = $this->createDefaultExtension($directory, $domain, $profile);
        }

        if (! $extension) {
            $this->messages[] = "Extension {$number} for {$profile['username']} does not exist in this account.";
            return;
        }

        if ($user->extension_uuid && $user->extension_uuid !== $extension->extension_uuid) {
            $this->messages[] = "{$profile['username']} already has a different manually assigned extension.";
            return;
        }

        $user->extension_uuid = $extension->extension_uuid;
    }

    private function createDefaultExtension(LdapDirectory $directory, Domain $domain, array $profile): ?Extensions
    {
        $number = (string) $profile['extension'];
        $error = null;
        (new UniqueExtension(null, $directory->domain_uuid))->validate('extension', $number, function ($message) use (&$error) {
            $error = $message;
        });

        if ($error !== null) {
            $this->messages[] = "Extension {$number} was not created: {$error}";
            return null;
        }

        $limit = get_limit_setting('extensions', $directory->domain_uuid);
        if ($limit !== null && Extensions::query()->where('domain_uuid', $directory->domain_uuid)->count() >= $limit) {
            $this->messages[] = "Extension {$number} was not created because the account extension limit was reached.";
            return null;
        }

        $name = trim($profile['display_name'] ?: ($profile['first_name'] . ' ' . $profile['last_name']));
        $extension = Extensions::query()->create([
            'domain_uuid' => $directory->domain_uuid,
            'extension' => $number,
            'password' => generate_sip_password(),
            'accountcode' => $domain->domain_name,
            'effective_caller_id_name' => $name ?: $number,
            'effective_caller_id_number' => $number,
            'directory_first_name' => $profile['first_name'] ?: $profile['username'],
            'directory_last_name' => $profile['last_name'],
            'directory_visible' => 'true',
            'directory_exten_visible' => 'true',
            'user_context' => $domain->domain_name,
            'description' => 'Created from directory synchronization',
        ]);

        $extension->advSettings()->create(['suspended' => false]);

        $voicemailPassword = get_domain_setting('password_complexity', $directory->domain_uuid) == 'true'
            ? str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT)
            : $number;

        Voicemails::query()->create([
            'domain_uuid' => $directory->domain_uuid,
            'voicemail_id' => $number,
            'voicemail_password' => $voicemailPassword,
            'voicemail_mail_to' => $profile['email'],
            'voicemail_transcription_enabled' => 'true',
            'voicemail_attach_file' => 'true',
            'voicemail_local_after_email' => 'true',
            'voicemail_enabled' => 'true',
            'voicemail_tutorial' => 'true',
            'voicemail_recording_instructions' => 'true',
            'voicemail_description' => $name,
        ]);

        FusionCache::clear("directory:{$number}@{$domain->domain_name}");

        return $extension;
    }

    private function syncMappedLocalGroups(LdapDirectoryUser $directoryUser): void
    {
        if (! $directoryUser->user_uuid) {
            return;
        }

        $desiredGroups = DB::table('ldap_directory_group_members as members')
            ->join('ldap_directory_group_mappings as mappings', 'mappings.directory_group_uuid', '=', 'members.directory_group_uuid')
            ->where('members.directory_user_uuid', $directoryUser->directory_user_uuid)
            ->pluck('mappings.group_uuid')
            ->unique()
            ->values();

        $assignments = DB::table('ldap_directory_user_group_assignments')
            ->where('directory_user_uuid', $directoryUser->directory_user_uuid)
            ->get()
            ->keyBy('group_uuid');

        foreach ($desiredGroups as $groupUuid) {
            if ($assignments->has($groupUuid)) {
                continue;
            }

            $group = Groups::query()->find($groupUuid);
            if (! $group || ($group->domain_uuid && $group->domain_uuid !== $directoryUser->domain_uuid)) {
                continue;
            }

            $membership = UserGroup::query()
                ->where('user_uuid', $directoryUser->user_uuid)
                ->where('group_uuid', $groupUuid)
                ->first();

            $created = false;
            if (! $membership) {
                UserGroup::query()->create([
                    'user_uuid' => $directoryUser->user_uuid,
                    'domain_uuid' => $directoryUser->domain_uuid,
                    'group_uuid' => $groupUuid,
                    'group_name' => $group->group_name,
                ]);
                $created = true;
            }

            DB::table('ldap_directory_user_group_assignments')->insert([
                'directory_user_uuid' => $directoryUser->directory_user_uuid,
                'group_uuid' => $groupUuid,
                'created_membership' => $created,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($assignments as $groupUuid => $assignment) {
            if ($desiredGroups->contains($groupUuid)) {
                continue;
            }

            if ($assignment->created_membership) {
                $managedElsewhere = DB::table('ldap_directory_user_group_assignments as assignments')
                    ->join('ldap_directory_users as directory_users', 'directory_users.directory_user_uuid', '=', 'assignments.directory_user_uuid')
                    ->where('group_uuid', $groupUuid)
                    ->where('assignments.directory_user_uuid', '!=', $directoryUser->directory_user_uuid)
                    ->where('directory_users.user_uuid', $directoryUser->user_uuid)
                    ->where('created_membership', true)
                    ->exists();

                if (! $managedElsewhere) {
                    UserGroup::query()
                        ->where('user_uuid', $directoryUser->user_uuid)
                        ->where('group_uuid', $groupUuid)
                        ->delete();
                }
            }

            DB::table('ldap_directory_user_group_assignments')
                ->where('directory_user_uuid', $directoryUser->directory_user_uuid)
                ->where('group_uuid', $groupUuid)
                ->delete();
        }
    }

    protected function userProfile(LdapDirectory $directory, array $entry): array
    {
        $username = (string) ActiveDirectoryClient::first($entry, $directory->user_name_attribute);
        $email = ActiveDirectoryClient::first($entry, $directory->user_email_attribute);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = null;
        }
        $displayName = ActiveDirectoryClient::first($entry, $directory->user_display_name_attribute);
        [$firstName, $lastName] = $this->normalizePersonName(
            ActiveDirectoryClient::first($entry, $directory->user_first_name_attribute),
            ActiveDirectoryClient::first($entry, $directory->user_last_name_attribute),
            $displayName
        );
        $userAccountControl = (int) (ActiveDirectoryClient::first($entry, 'userAccountControl') ?: 0);

        return [
            'username' => $username,
            'email' => $email === null ? null : strtolower($email),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $displayName,
            'description' => ActiveDirectoryClient::first($entry, $directory->description_attribute),
            'title' => ActiveDirectoryClient::first($entry, $directory->user_title_attribute),
            'company' => ActiveDirectoryClient::first($entry, $directory->user_company_attribute),
            'department' => ActiveDirectoryClient::first($entry, $directory->user_department_attribute),
            'home_phone' => ActiveDirectoryClient::first($entry, $directory->user_home_phone_attribute),
            'work_phone' => ActiveDirectoryClient::first($entry, $directory->user_work_phone_attribute),
            'cell_phone' => ActiveDirectoryClient::first($entry, $directory->user_cell_phone_attribute),
            'fax' => ActiveDirectoryClient::first($entry, $directory->user_fax_attribute),
            'extension' => ActiveDirectoryClient::first($entry, $directory->user_extension_attribute),
            'remote_enabled' => ($userAccountControl & 2) !== 2,
        ];
    }

    private function removeManagedLocalGroups(LdapDirectoryUser $directoryUser): void
    {
        $assignments = DB::table('ldap_directory_user_group_assignments')
            ->where('directory_user_uuid', $directoryUser->directory_user_uuid)
            ->get();

        foreach ($assignments as $assignment) {
            if ($assignment->created_membership) {
                $managedElsewhere = DB::table('ldap_directory_user_group_assignments as assignments')
                    ->join('ldap_directory_users as directory_users', 'directory_users.directory_user_uuid', '=', 'assignments.directory_user_uuid')
                    ->where('directory_users.user_uuid', $directoryUser->user_uuid)
                    ->where('assignments.group_uuid', $assignment->group_uuid)
                    ->where('assignments.directory_user_uuid', '!=', $directoryUser->directory_user_uuid)
                    ->where('assignments.created_membership', true)
                    ->exists();

                if (! $managedElsewhere) {
                    UserGroup::query()->where('user_uuid', $directoryUser->user_uuid)->where('group_uuid', $assignment->group_uuid)->delete();
                }
            }
        }

        DB::table('ldap_directory_user_group_assignments')
            ->where('directory_user_uuid', $directoryUser->directory_user_uuid)
            ->delete();
    }

    private function normalizeDn(?string $dn): string
    {
        return mb_strtolower(trim((string) $dn));
    }

    protected function mergeGroupMemberships(
        array $groupMemberAttributeUuids,
        array $userGroupAttributeUuids,
        ?string $primaryGroupUuid
    ): array
    {
        return collect($groupMemberAttributeUuids)
            ->merge($userGroupAttributeUuids)
            ->when($primaryGroupUuid !== null, fn ($groups) => $groups->push($primaryGroupUuid))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizePersonName(?string $firstName, ?string $lastName, ?string $displayName): array
    {
        $firstName = trim((string) $firstName);
        $lastName = trim((string) $lastName);
        $displayName = trim((string) $displayName);
        $fullName = $displayName !== '' ? $displayName : $firstName;

        if ($lastName !== '' && ($firstName === '' || mb_strtolower($firstName) === mb_strtolower($fullName))) {
            $derivedFirstName = preg_replace('/\s+'.preg_quote($lastName, '/').'$/iu', '', $fullName);

            if (is_string($derivedFirstName) && trim($derivedFirstName) !== '' && $derivedFirstName !== $fullName) {
                $firstName = trim($derivedFirstName);
            }
        }

        return [$firstName !== '' ? $firstName : null, $lastName !== '' ? $lastName : null];
    }

    private function finishRun(LdapSyncRun $run, string $status, array $values): LdapSyncRun
    {
        $run->fill($values + ['status' => $status, 'finished_at' => now()]);
        $run->save();

        return $run->fresh();
    }
}
