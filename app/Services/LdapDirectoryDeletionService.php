<?php

namespace App\Services;

use App\Models\LdapDirectory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LdapDirectoryDeletionService
{
    /**
     * Delete a directory and the local user projections created by it.
     *
     * Users that existed locally before the directory linked to them are
     * deliberately preserved because their add_user value does not identify
     * this directory as their owner.
     *
     * @return Collection<int, string> Deleted user UUIDs
     */
    public function delete(LdapDirectory $directory): Collection
    {
        return DB::transaction(function () use ($directory) {
            $ownedUserUuids = DB::table('v_users')
                ->where('domain_uuid', $directory->domain_uuid)
                ->where('add_user', 'ldap:' . $directory->directory_uuid)
                ->pluck('user_uuid');

            $directoryUserIds = DB::table('ldap_directory_users')
                ->where('directory_uuid', $directory->directory_uuid)
                ->pluck('directory_user_uuid');

            $managedMemberships = DB::table('ldap_directory_user_group_assignments as assignments')
                ->join('ldap_directory_users as directory_users', 'directory_users.directory_user_uuid', '=', 'assignments.directory_user_uuid')
                ->where('directory_users.directory_uuid', $directory->directory_uuid)
                ->where('assignments.created_membership', true)
                ->get(['directory_users.user_uuid', 'assignments.group_uuid']);

            foreach ($managedMemberships as $membership) {
                $managedElsewhere = DB::table('ldap_directory_user_group_assignments as assignments')
                    ->join('ldap_directory_users as directory_users', 'directory_users.directory_user_uuid', '=', 'assignments.directory_user_uuid')
                    ->where('directory_users.user_uuid', $membership->user_uuid)
                    ->where('assignments.group_uuid', $membership->group_uuid)
                    ->where('directory_users.directory_uuid', '!=', $directory->directory_uuid)
                    ->where('assignments.created_membership', true)
                    ->exists();

                if (! $managedElsewhere) {
                    DB::table('v_user_groups')
                        ->where('user_uuid', $membership->user_uuid)
                        ->where('group_uuid', $membership->group_uuid)
                        ->delete();
                }
            }

            $directoryGroupIds = DB::table('ldap_directory_groups')
                ->where('directory_uuid', $directory->directory_uuid)
                ->pluck('directory_group_uuid');

            DB::table('ldap_directory_user_group_assignments')->whereIn('directory_user_uuid', $directoryUserIds)->delete();
            DB::table('ldap_directory_group_members')->whereIn('directory_user_uuid', $directoryUserIds)->delete();
            DB::table('ldap_directory_group_members')->whereIn('directory_group_uuid', $directoryGroupIds)->delete();
            DB::table('ldap_directory_group_mappings')->where('directory_uuid', $directory->directory_uuid)->delete();
            DB::table('ldap_sync_runs')->where('directory_uuid', $directory->directory_uuid)->delete();
            DB::table('ldap_directory_users')->where('directory_uuid', $directory->directory_uuid)->delete();
            DB::table('ldap_directory_groups')->where('directory_uuid', $directory->directory_uuid)->delete();

            $users = User::query()
                ->without(['user_adv_fields', 'settings'])
                ->where('domain_uuid', $directory->domain_uuid)
                ->whereIn('user_uuid', $ownedUserUuids)
                ->where('add_user', 'ldap:' . $directory->directory_uuid)
                ->get();

            foreach ($users as $user) {
                if (Schema::hasTable('personal_access_tokens')) {
                    $user->tokens()->delete();
                }
                $user->user_adv_fields()->delete();
                $user->settings()->delete();
                $user->user_groups()->delete();
                $user->domain_permissions()->delete();
                $user->domain_group_permissions()->delete();
                $user->delete();
            }

            DB::table('locationables')
                ->where('locationable_type', User::class)
                ->whereIn('locationable_id', $ownedUserUuids)
                ->delete();

            $directory->delete();

            return $users->pluck('user_uuid')->values();
        });
    }
}
