<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use App\Models\Groups;
use App\Models\GroupPermissions;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use App\Models\Permissions;
use Illuminate\Support\Str;
use Throwable;

class Update189
{
    private const VERSION = '1.8.9';
    private const TITLE = 'Scheduled Announcements';
    private const LINK = '/scheduled-announcements';

    public function apply(): bool
    {
        try {
            $this->ensurePermissions();
            $this->ensureScheduledJobSettings();
            $this->ensureApplicationMenuItem();

            echo "Update " . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function ensurePermissions(): void
    {
        $permissions = [
            'scheduled_announcements_list_view',
            'scheduled_announcements_create',
            'scheduled_announcements_update',
            'scheduled_announcements_delete',
            'scheduled_announcements_execute',
            'scheduled_announcements_manage_settings',
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
                'application_name' => self::TITLE,
                'permission_name' => $permissionName,
                'insert_date' => $now,
            ])
            ->values()
            ->all();

        if ($permissionRows !== []) {
            Permissions::query()->insert($permissionRows);
            echo "Created " . count($permissionRows) . " " . self::TITLE . " permission row(s).\n";
        } else {
            echo self::TITLE . " permissions already exist.\n";
        }

        $assignments = [
            'superadmin' => $permissions,
            'admin' => [
                'scheduled_announcements_list_view',
                'scheduled_announcements_create',
                'scheduled_announcements_update',
                'scheduled_announcements_delete',
                'scheduled_announcements_execute',
            ],
        ];

        foreach ($assignments as $groupName => $groupPermissions) {
            $group = Groups::query()
                ->where('group_name', $groupName)
                ->first();

            if (! $group) {
                echo "Group '{$groupName}' not found; " . self::TITLE . " permissions not assigned to it.\n";
                continue;
            }

            $existingGroupPermissions = GroupPermissions::query()
                ->where('group_uuid', $group->group_uuid)
                ->whereIn('permission_name', $groupPermissions)
                ->pluck('permission_name')
                ->all();

            $groupPermissionRows = collect($groupPermissions)
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
                echo self::TITLE . " permissions already assigned to group '{$groupName}'.\n";
                continue;
            }

            GroupPermissions::query()->insert($groupPermissionRows);
            echo "Assigned " . count($groupPermissionRows) . " " . self::TITLE . " permission(s) to group '{$groupName}'.\n";
        }
    }

    private function ensureScheduledJobSettings(): void
    {
        $settings = [
            'scheduled_announcements' => [
                'name' => 'boolean',
                'value' => 'false',
                'description' => 'Enable or disable the processing of scheduled announcements.',
            ],
            'scheduled_announcements_active_fqdn' => [
                'name' => 'text',
                'value' => '',
                'description' => 'Optional active-node FQDN override. When blank, APP_URL is used.',
            ],
            'scheduled_announcements_authoritative_zone' => [
                'name' => 'text',
                'value' => '',
                'description' => 'Optional DNS zone override for authoritative lookup discovery.',
            ],
            'scheduled_announcements_node_ips' => [
                'name' => 'text',
                'value' => '',
                'description' => 'Optional comma-separated public IP override for this node. When blank, FS PBX discovers local and external IPs.',
            ],
            'scheduled_announcements_dns_timeout_ms' => [
                'name' => 'numeric',
                'value' => '800',
                'description' => 'DNS guard timeout in milliseconds.',
            ],
            'scheduled_announcements_fire_window_seconds' => [
                'name' => 'numeric',
                'value' => '15',
                'description' => 'Maximum seconds after the scheduled time that an announcement may still run.',
            ],
        ];
        $created = 0;

        foreach ($settings as $subcategory => $setting) {
            $exists = DefaultSettings::query()
                ->where('default_setting_category', 'scheduled_jobs')
                ->where('default_setting_subcategory', $subcategory)
                ->exists();

            if ($exists) {
                continue;
            }

            DefaultSettings::query()->create([
                'default_setting_uuid' => (string) Str::uuid(),
                'default_setting_category' => 'scheduled_jobs',
                'default_setting_subcategory' => $subcategory,
                'default_setting_name' => $setting['name'],
                'default_setting_value' => $setting['value'],
                'default_setting_enabled' => true,
                'default_setting_description' => $setting['description'],
                'insert_date' => date('Y-m-d H:i:s'),
            ]);
            $created++;
        }

        echo $created === 0
            ? self::TITLE . " scheduled job settings already exist.\n"
            : "Created {$created} " . self::TITLE . " scheduled job setting(s).\n";
    }

    private function ensureApplicationMenuItem(): void
    {
        $menu = Menu::query()
            ->where('menu_name', 'fspbx')
            ->first();

        if (! $menu) {
            echo "Menu 'fspbx' was not found; skipping " . self::TITLE . " menu item.\n";
            return;
        }

        $applicationsItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_title', 'Applications')
            ->whereNull('menu_item_parent_uuid')
            ->first();

        if (! $applicationsItem) {
            echo "Applications menu item was not found in menu '{$menu->menu_name}'; skipping " . self::TITLE . " menu item.\n";
            return;
        }

        $menuItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where(function ($query) {
                $query->where('menu_item_link', self::LINK)
                    ->orWhere('menu_item_title', self::TITLE);
            })
            ->first();

        if ($menuItem) {
            $menuItem->forceFill([
                'menu_item_title' => self::TITLE,
                'menu_item_link' => self::LINK,
                'menu_item_parent_uuid' => $applicationsItem->menu_item_uuid,
                'menu_item_category' => $menuItem->menu_item_category ?: 'internal',
                'menu_item_protected' => $menuItem->menu_item_protected ?: 'false',
                'menu_item_order' => $menuItem->menu_item_parent_uuid === $applicationsItem->menu_item_uuid && $menuItem->menu_item_order
                    ? $menuItem->menu_item_order
                    : $this->nextMenuItemOrder($menu, $applicationsItem),
            ])->save();

            echo self::TITLE . " menu item already exists; ensured it is under Applications with the correct title and link.\n";
        } else {
            $menuItem = MenuItem::query()->create([
                'menu_item_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_parent_uuid' => $applicationsItem->menu_item_uuid,
                'menu_item_title' => self::TITLE,
                'menu_item_link' => self::LINK,
                'menu_item_icon' => '',
                'menu_item_category' => 'internal',
                'menu_item_protected' => 'false',
                'menu_item_order' => $this->nextMenuItemOrder($menu, $applicationsItem),
            ]);

            echo "Added " . self::TITLE . " menu item under Applications.\n";
        }

        $this->ensureMenuLanguage($menu, $menuItem);
        $this->ensureMenuItemGroups($menu, $menuItem, ['superadmin', 'admin']);
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
            if ($language->menu_item_title !== self::TITLE) {
                $language->forceFill([
                    'menu_item_title' => self::TITLE,
                ])->save();
            }

            return;
        }

        MenuLanguage::query()->create([
            'menu_language_uuid' => (string) Str::uuid(),
            'menu_uuid' => $menu->menu_uuid,
            'menu_item_uuid' => $menuItem->menu_item_uuid,
            'menu_language' => 'en-us',
            'menu_item_title' => self::TITLE,
        ]);
    }

    private function ensureMenuItemGroups(Menu $menu, MenuItem $menuItem, array $groupNames): void
    {
        foreach ($groupNames as $groupName) {
            $group = Groups::query()
                ->where('group_name', $groupName)
                ->first();

            if (! $group) {
                echo "Group '{$groupName}' not found; " . self::TITLE . " menu access not created for it.\n";
                continue;
            }

            $exists = MenuItemGroup::query()
                ->where('menu_item_uuid', $menuItem->menu_item_uuid)
                ->where('group_uuid', $group->group_uuid)
                ->exists();

            if ($exists) {
                echo self::TITLE . " menu access already exists for group '{$groupName}'.\n";
                continue;
            }

            MenuItemGroup::query()->create([
                'menu_item_group_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_uuid' => $menuItem->menu_item_uuid,
                'group_name' => $groupName,
                'group_uuid' => $group->group_uuid,
            ]);

            echo "Granted " . self::TITLE . " menu access to group '{$groupName}'.\n";
        }
    }
}
