<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\GroupPermissions;
use App\Models\DomainGroupRelations;
use App\Models\UserDomainPermission;
use Illuminate\Support\Facades\Cache;
use App\Models\UserDomainGroupPermissions;

class PermissionService
{

    public function userHasPermission(User $user, string $permissionName, ?string $domainUuid = null): bool
    {
        // If no explicit domain provided, default to user's own domain
        $domainUuid = $domainUuid ?: (string) $user->domain_uuid;

        $cacheKey = "perm:$permissionName";

        return Cache::tags([
            'auth',
            "user:{$user->user_uuid}",
            "domain:$domainUuid",
            'permissions',
        ])->remember($cacheKey, now()->addMinutes(10), function () use ($user, $permissionName, $domainUuid) {

            $groupUuids = UserGroup::query()
                ->where('user_uuid', $user->user_uuid)
                ->whereNotNull('group_uuid')
                ->pluck('group_uuid')
                ->unique()
                ->values();

            if ($groupUuids->isEmpty()) {
                return false;
            }

            return GroupPermissions::query()
                ->whereIn('group_uuid', $groupUuids)
                ->where('permission_name', $permissionName)
                ->where('permission_assigned', 'true')
                ->exists();
        });

    }

    public function userCanAccessDomain(User $user, string $targetDomainUuid): bool
    {
        // If user has domain_all permission, allow all domains
        // IMPORTANT: check domain_all in user's own domain context
        if ($this->userHasPermission($user, 'domain_all', (string) $user->domain_uuid)) {
            return true;
        }

        return Cache::tags([
            'auth',
            "user:{$user->user_uuid}",
            'domain_access',
        ])->remember("can_access:$targetDomainUuid", now()->addMinutes(10), function () use ($user, $targetDomainUuid) {

            // Union of domains from:
            // 1) user_domain_group_permissions -> domain_group_relations
            $domainGroupUuids = UserDomainGroupPermissions::query()
                ->where('user_uuid', $user->user_uuid)
                ->pluck('domain_group_uuid')
                ->unique()
                ->values();

            $domainsFromGroups = collect();
            if ($domainGroupUuids->isNotEmpty()) {
                $domainsFromGroups = DomainGroupRelations::query()
                    ->whereIn('domain_group_uuid', $domainGroupUuids)
                    ->pluck('domain_uuid')
                    ->unique()
                    ->values();
            }

            // 2) user_domain_permission
            $domainsFromUser = UserDomainPermission::query()
                ->where('user_uuid', $user->user_uuid)
                ->pluck('domain_uuid')
                ->unique()
                ->values();

            $assigned = $domainsFromGroups->merge($domainsFromUser)->unique()->values();

            // If ANY assignments exist, allowed domains are ONLY those (even if own domain not included)
            if ($assigned->isNotEmpty()) {
                return $assigned->contains($targetDomainUuid);
            }

            // Otherwise, only their own domain
            return (string) $user->domain_uuid === (string) $targetDomainUuid;
        });
    }

    public function allowedDomainUuids(User $user): ?\Illuminate\Support\Collection
    {
        // domain_all => all domains
        if ($this->userHasPermission($user, 'domain_all', (string) $user->domain_uuid)) {
            return null;
        }

        return Cache::tags(['auth', "user:{$user->user_uuid}", 'domain_access'])
            ->remember('allowed_domains', now()->addMinutes(10), function () use ($user) {

                $domainGroupUuids = $user->domain_group_permissions()
                    ->pluck('domain_group_uuid')
                    ->unique()
                    ->values();

                $domainsFromGroups = $domainGroupUuids->isEmpty()
                    ? collect()
                    : DomainGroupRelations::query()
                    ->whereIn('domain_group_uuid', $domainGroupUuids)
                    ->pluck('domain_uuid')
                    ->unique()
                    ->values();

                $domainsFromUser = $user->domain_permissions()
                    ->pluck('domain_uuid')
                    ->unique()
                    ->values();

                $assigned = $domainsFromGroups->merge($domainsFromUser)->unique()->values();

                // If ANY assignments exist, allowed domains are ONLY those (even if own domain not included)
                if ($assigned->isNotEmpty()) {
                    return $assigned;
                }

                // Otherwise fallback to own domain only
                return collect([(string) $user->domain_uuid]);
            });
    }
}
