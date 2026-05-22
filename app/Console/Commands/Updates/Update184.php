<?php

namespace App\Console\Commands\Updates;

use App\Models\Groups;
use App\Models\GroupPermissions;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use App\Models\Permissions;
use Illuminate\Support\Str;
use Throwable;

class Update184
{
    private const VERSION = '1.8.4';
    private const BASIC_DIALER_TITLE = 'Basic Dialer';
    private const BASIC_DIALER_LINK = '/basic-dialer';
    private const LEGACY_CALL_BROADCAST_LINK = '/app/call_broadcast/call_broadcast.php';

    public function apply(): bool
    {
        try {
            $this->ensurePermissions();
            $this->updateLegacyCallBroadcastMenuItems();
            $this->ensureBasicDialerApplicationMenuItem();

            echo "Update " . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function updateLegacyCallBroadcastMenuItems(): void
    {
        $menuItemUuids = MenuItem::query()
            ->where(function ($query) {
                $query->where('menu_item_link', self::LEGACY_CALL_BROADCAST_LINK)
                    ->orWhere('menu_item_title', 'Call Broadcast');
            })
            ->pluck('menu_item_uuid')
            ->all();

        if ($menuItemUuids === []) {
            echo "No Call Broadcast menu items required updating.\n";
            return;
        }

        $updatedMenuItems = MenuItem::query()
            ->whereIn('menu_item_uuid', $menuItemUuids)
            ->update([
                'menu_item_title' => self::BASIC_DIALER_TITLE,
                'menu_item_link' => self::BASIC_DIALER_LINK,
            ]);

        $updatedMenuLanguages = MenuLanguage::query()
            ->whereIn('menu_item_uuid', $menuItemUuids)
            ->where('menu_item_title', 'Call Broadcast')
            ->update([
                'menu_item_title' => self::BASIC_DIALER_TITLE,
            ]);

        echo "Updated {$updatedMenuItems} Call Broadcast menu item(s) and {$updatedMenuLanguages} menu language row(s) to " . self::BASIC_DIALER_TITLE . ".\n";
    }

    private function ensurePermissions(): void
    {
        $permissions = [
            'basic_dialer_view',
            'basic_dialer_create',
            'basic_dialer_update',
            'basic_dialer_delete',
            'basic_dialer_start',
        ];
        $now = date('Y-m-d H:i:s');
        $existingPermissions = Permissions::query()
            ->whereIn('permission_name', $permissions)
            ->pluck('permission_name')
            ->all();

        $permissionRows = collect($permissions)
            ->diff($existingPermissions)
            ->map(fn ($permissionName) => [
                'permission_uuid' => (string) Str::uuid(),
                'application_name' => self::BASIC_DIALER_TITLE,
                'permission_name' => $permissionName,
                'insert_date' => $now,
            ])
            ->values()
            ->all();

        if ($permissionRows !== []) {
            Permissions::query()->insert($permissionRows);
            echo "Created " . count($permissionRows) . " " . self::BASIC_DIALER_TITLE . " permission row(s).\n";
        } else {
            echo self::BASIC_DIALER_TITLE . " permissions already exist.\n";
        }

        foreach (['superadmin', 'admin'] as $groupName) {
            $group = Groups::query()
                ->where('group_name', $groupName)
                ->first();

            if (! $group) {
                echo "Group '{$groupName}' not found; " . self::BASIC_DIALER_TITLE . " permissions not assigned to it.\n";
                continue;
            }

            $existingGroupPermissions = GroupPermissions::query()
                ->where('group_uuid', $group->group_uuid)
                ->whereIn('permission_name', $permissions)
                ->pluck('permission_name')
                ->all();

            $groupPermissionRows = collect($permissions)
                ->diff($existingGroupPermissions)
                ->map(fn ($permissionName) => [
                    'group_permission_uuid' => (string) Str::uuid(),
                    'group_uuid' => $group->group_uuid,
                    'group_name' => $groupName,
                    'permission_name' => $permissionName,
                    'permission_protected' => 'true',
                    'permission_assigned' => 'true',
                    'insert_date' => $now,
                ])
                ->values()
                ->all();

            if ($groupPermissionRows === []) {
                echo self::BASIC_DIALER_TITLE . " permissions already assigned to group '{$groupName}'.\n";
                continue;
            }

            GroupPermissions::query()->insert($groupPermissionRows);
            echo "Assigned " . count($groupPermissionRows) . " " . self::BASIC_DIALER_TITLE . " permission(s) to group '{$groupName}'.\n";
        }
    }

    private function ensureBasicDialerApplicationMenuItem(): void
    {
        $menu = Menu::query()
            ->where('menu_name', 'fspbx')
            ->first();

        if (! $menu) {
            echo "Menu 'fspbx' was not found; skipping " . self::BASIC_DIALER_TITLE . " menu item.\n";
            return;
        }

        $applicationsItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_title', 'Applications')
            ->whereNull('menu_item_parent_uuid')
            ->first();

        if (! $applicationsItem) {
            echo "Applications menu item was not found in menu '{$menu->menu_name}'; skipping " . self::BASIC_DIALER_TITLE . " menu item.\n";
            return;
        }

        $menuItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where($this->basicDialerMenuMatcher())
            ->orderByRaw('case when menu_item_link = ? then 0 else 1 end', [self::LEGACY_CALL_BROADCAST_LINK])
            ->first();

        if ($menuItem) {
            $menuItem->forceFill([
                'menu_item_title' => self::BASIC_DIALER_TITLE,
                'menu_item_link' => self::BASIC_DIALER_LINK,
                'menu_item_parent_uuid' => $applicationsItem->menu_item_uuid,
                'menu_item_category' => $menuItem->menu_item_category ?: 'internal',
                'menu_item_protected' => $menuItem->menu_item_protected ?: 'false',
                'menu_item_order' => $menuItem->menu_item_parent_uuid === $applicationsItem->menu_item_uuid && $menuItem->menu_item_order
                    ? $menuItem->menu_item_order
                    : $this->nextMenuItemOrder($menu, $applicationsItem),
            ])->save();

            echo self::BASIC_DIALER_TITLE . " menu item already exists; ensured it is under Applications with the correct title and link.\n";
        } else {
            $menuItem = MenuItem::query()->create([
                'menu_item_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_parent_uuid' => $applicationsItem->menu_item_uuid,
                'menu_item_title' => self::BASIC_DIALER_TITLE,
                'menu_item_link' => self::BASIC_DIALER_LINK,
                'menu_item_icon' => '',
                'menu_item_category' => 'internal',
                'menu_item_protected' => 'false',
                'menu_item_order' => $this->nextMenuItemOrder($menu, $applicationsItem),
            ]);

            echo "Added " . self::BASIC_DIALER_TITLE . " menu item under Applications.\n";
        }

        $this->ensureMenuLanguage($menu, $menuItem);
        $this->ensureMenuItemGroups($menu, $menuItem, ['superadmin', 'admin']);
    }

    private function basicDialerMenuMatcher(): callable
    {
        return function ($query) {
            $query->whereIn('menu_item_link', [
                self::LEGACY_CALL_BROADCAST_LINK,
                self::BASIC_DIALER_LINK,
            ])->orWhereIn('menu_item_title', [
                'Call Broadcast',
                self::BASIC_DIALER_TITLE,
            ]);
        };
    }

    private function nextMenuItemOrder(Menu $menu, MenuItem $parentItem): int
    {
        return ((int) MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_parent_uuid', $parentItem->menu_item_uuid)
            ->max('menu_item_order')) + 1;
    }

    private function ensureMenuLanguage(Menu $menu, MenuItem $menuItem): void
    {
        $language = MenuLanguage::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_uuid', $menuItem->menu_item_uuid)
            ->where('menu_language', 'en-us')
            ->first();

        if ($language) {
            if ($language->menu_item_title !== self::BASIC_DIALER_TITLE) {
                $language->forceFill([
                    'menu_item_title' => self::BASIC_DIALER_TITLE,
                ])->save();
            }

            return;
        }

        MenuLanguage::query()->create([
            'menu_language_uuid' => (string) Str::uuid(),
            'menu_uuid' => $menu->menu_uuid,
            'menu_item_uuid' => $menuItem->menu_item_uuid,
            'menu_language' => 'en-us',
            'menu_item_title' => self::BASIC_DIALER_TITLE,
        ]);
    }

    private function ensureMenuItemGroups(Menu $menu, MenuItem $menuItem, array $groupNames): void
    {
        foreach ($groupNames as $groupName) {
            $group = Groups::query()
                ->where('group_name', $groupName)
                ->first();

            if (! $group) {
                echo "Group '{$groupName}' not found; " . self::BASIC_DIALER_TITLE . " menu access not created for it.\n";
                continue;
            }

            $exists = MenuItemGroup::query()
                ->where('menu_item_uuid', $menuItem->menu_item_uuid)
                ->where('group_uuid', $group->group_uuid)
                ->exists();

            if ($exists) {
                echo self::BASIC_DIALER_TITLE . " menu access already exists for group '{$groupName}'.\n";
                continue;
            }

            MenuItemGroup::query()->create([
                'menu_item_group_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_uuid' => $menuItem->menu_item_uuid,
                'group_name' => $groupName,
                'group_uuid' => $group->group_uuid,
            ]);

            echo "Granted " . self::BASIC_DIALER_TITLE . " menu access to group '{$groupName}'.\n";
        }
    }
}
