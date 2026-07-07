<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserSessionInvalidationService
{
    public function invalidateByUserUuids(iterable $userUuids): void
    {
        $userUuids = collect($userUuids)
            ->filter(fn ($uuid) => is_string($uuid) && $uuid !== '')
            ->unique()
            ->values();

        if ($userUuids->isEmpty()) {
            return;
        }

        foreach ($userUuids as $userUuid) {
            Cache::tags(['auth', "user:{$userUuid}", 'permissions'])->flush();
            Cache::tags(['auth', "user:{$userUuid}", 'domain_access'])->flush();
        }

        $currentUserUuid = optional(Auth::user())->user_uuid;
        $currentSessionId = request()->hasSession()
            ? request()->session()->getId()
            : null;

        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            DB::table('sessions')
                ->whereIn('user_id', $userUuids)
                ->when(
                    $currentUserUuid && $currentSessionId && $userUuids->contains($currentUserUuid),
                    fn ($query) => $query->where('id', '!=', $currentSessionId)
                )
                ->delete();
        }

        if ($currentUserUuid && $userUuids->contains($currentUserUuid)) {
            $this->refreshCurrentUserAuthorizationSession($currentUserUuid);
        }
    }

    private function refreshCurrentUserAuthorizationSession(string $userUuid): void
    {
        if (!request()->hasSession()) {
            return;
        }

        $user = Auth::user();

        if (!$user || (string) $user->user_uuid !== $userUuid) {
            return;
        }

        $userDomainUuid = (string) $user->domain_uuid;
        $currentDomainUuid = (string) (session('domain_uuid') ?: $userDomainUuid);

        $groups = DB::table('v_user_groups')
            ->join('v_groups', 'v_user_groups.group_uuid', '=', 'v_groups.group_uuid')
            ->where('v_user_groups.user_uuid', $userUuid)
            ->where('v_user_groups.domain_uuid', $userDomainUuid)
            ->get([
                'v_user_groups.group_uuid',
                'v_user_groups.domain_uuid',
                'v_user_groups.user_uuid',
                'v_user_groups.group_name',
                'v_groups.group_level',
            ]);

        session()->put('user.groups', $groups);

        $highestGroup = $groups->sortByDesc('group_level')->first();

        session()->put('user.group_level', (int) ($highestGroup->group_level ?? 0));

        if ($highestGroup) {
            session()->put('user.group_name', $highestGroup->group_name);
            $_SESSION['user']['group_level'] = $highestGroup->group_level;
        } else {
            session()->forget('user.group_name');
            unset($_SESSION['user']['group_level']);
        }

        $groupUuids = $groups->pluck('group_uuid')->filter()->unique()->values();
        $permissions = collect();

        $this->refreshCurrentUserMenu($userUuid, $userDomainUuid, $groupUuids);

        if ($groupUuids->isNotEmpty()) {
            $permissions = DB::table('v_permissions')
                ->join('v_group_permissions', 'v_permissions.permission_name', '=', 'v_group_permissions.permission_name')
                ->whereIn('v_group_permissions.group_uuid', $groupUuids)
                ->where('v_group_permissions.permission_assigned', 'true')
                ->where(function ($query) use ($currentDomainUuid) {
                    $query->where('v_group_permissions.domain_uuid', $currentDomainUuid)
                        ->orWhereNull('v_group_permissions.domain_uuid');
                })
                ->distinct()
                ->get([
                    'v_permissions.permission_uuid',
                    'v_permissions.permission_name',
                ]);
        }

        session()->put('permissions', $permissions);
        unset($_SESSION['permissions'], $_SESSION['user']['permissions']);

        foreach ($permissions as $permission) {
            $_SESSION['permissions'][$permission->permission_name] = true;
            $_SESSION['user']['permissions'][$permission->permission_name] = true;
        }
    }

    private function refreshCurrentUserMenu(string $userUuid, string $userDomainUuid, $groupUuids): void
    {
        $menuUuid = DB::table('v_user_settings')
            ->where('user_uuid', $userUuid)
            ->where('user_setting_subcategory', 'menu')
            ->value('user_setting_value');

        if (!$menuUuid) {
            $menuUuid = DB::table('v_domain_settings')
                ->where('domain_uuid', $userDomainUuid)
                ->where('domain_setting_category', 'domain')
                ->where('domain_setting_subcategory', 'menu')
                ->where('domain_setting_enabled', true)
                ->value('domain_setting_value');
        }

        if (!$menuUuid) {
            $menuUuid = DB::table('v_default_settings')
                ->where('default_setting_category', 'domain')
                ->where('default_setting_subcategory', 'menu')
                ->value('default_setting_value');
        }

        if (!$menuUuid || $groupUuids->isEmpty()) {
            session()->put('menu', collect());
            return;
        }

        session()->put('user.menu_uuid', $menuUuid);
        $_SESSION['domain']['menu']['uuid'] = $menuUuid;

        $mainMenu = DB::table('v_menu_items')
            ->join('v_menu_item_groups', 'v_menu_item_groups.menu_item_uuid', '=', 'v_menu_items.menu_item_uuid')
            ->where('v_menu_items.menu_uuid', $menuUuid)
            ->whereNull('v_menu_items.menu_item_parent_uuid')
            ->whereIn('v_menu_item_groups.group_uuid', $groupUuids)
            ->distinct()
            ->orderBy('menu_item_order')
            ->get([
                'v_menu_items.menu_item_uuid',
                'v_menu_items.menu_item_title',
                'v_menu_items.menu_item_link',
                'menu_item_icon',
                'menu_item_order',
            ]);

        foreach ($mainMenu as $menu) {
            $menu->child_menu = DB::table('v_menu_items')
                ->join('v_menu_item_groups', 'v_menu_item_groups.menu_item_uuid', '=', 'v_menu_items.menu_item_uuid')
                ->where('v_menu_items.menu_item_parent_uuid', $menu->menu_item_uuid)
                ->whereIn('v_menu_item_groups.group_uuid', $groupUuids)
                ->distinct()
                ->orderBy('v_menu_items.menu_item_title')
                ->get([
                    'v_menu_items.menu_item_uuid',
                    'v_menu_items.menu_item_title',
                    'v_menu_items.menu_item_link',
                    'menu_item_icon',
                    'menu_item_order',
                ]);
        }

        session()->put('menu', $mainMenu);
    }
}
