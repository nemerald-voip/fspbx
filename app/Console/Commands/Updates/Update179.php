<?php

namespace App\Console\Commands\Updates;

use App\Models\Groups;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class Update179
{
    private const OBSOLETE_FSPBX_MENU_LINKS = [
        '/emailqueue',
        '/app/applications/applications.php',
        '/app/time_conditions/time_conditions.php',
        '/core/databases/databases.php',
        '/core/notifications/notification_edit.php',
        '/app/fifo_list/fifo_list.php',
    ];

    private const OBSOLETE_ALL_MENU_LINKS = [
        '/core/upgrade/index.php',
        '/app/xml_cdr/xml_cdr_statistics.php',
        '/app/basic_operator_panel/index.php'
    ];

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply(): bool
    {
        $this->updateBasicQueueMenuItems();
        $this->updateBasicQueueAgentStatusMenuItems();
        $this->updateActiveBasicQueueMenuItems();
        $this->ensureBasicQueuesApplicationMenuItem();
        $this->ensureActiveBasicQueuesStatusMenuItem();
        $this->removeObsoleteFspbxMenuItems();
        $this->removeObsoleteMenuItemsFromAllMenus();
        $this->patchLocalStreamXmlGenerator();

        echo "Update 1.7.9 completed successfully.\n";
        return true;
    }

    private function updateBasicQueueMenuItems(): void
    {
        $updated = MenuItem::query()
            ->where('menu_item_link', '/app/call_centers/call_center_queues.php')
            ->update([
                'menu_item_title' => 'Basic Queues',
                'menu_item_link' => '/basic-queues',
            ]);

        echo $updated === 0
            ? "No Basic Queue menu items required updating.\n"
            : "Updated {$updated} Basic Queue menu item(s).\n";
    }

    private function updateBasicQueueAgentStatusMenuItems(): void
    {
        $updated = MenuItem::query()
            ->where('menu_item_link', '/app/call_centers/call_center_agent_status.php')
            ->update([
                'menu_item_title' => 'Agent Status',
                'menu_item_link' => '/basic-queues/agent-status',
            ]);

        echo $updated === 0
            ? "No Agent Status menu items required updating.\n"
            : "Updated {$updated} Agent Status menu item(s).\n";
    }

    private function updateActiveBasicQueueMenuItems(): void
    {
        $updated = MenuItem::query()
            ->where('menu_item_link', '/app/call_center_active/call_center_queue.php')
            ->update([
                'menu_item_title' => 'Active Basic Queues',
                'menu_item_link' => '/active-basic-queues',
            ]);

        echo $updated === 0
            ? "No Active Basic Queues menu items required updating.\n"
            : "Updated {$updated} Active Basic Queues menu item(s).\n";
    }

    private function ensureBasicQueuesApplicationMenuItem(): void
    {
        $menu = Menu::query()
            ->where('menu_name', 'fspbx')
            ->first();

        if (! $menu) {
            echo "Menu 'fspbx' was not found; skipping Basic Queues menu item.\n";
            return;
        }

        $applicationsItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_title', 'Applications')
            ->where(function ($query) {
                $query->whereNull('menu_item_parent_uuid')
                    ->orWhere('menu_item_parent_uuid', '');
            })
            ->first();

        if (! $applicationsItem) {
            echo "Applications menu item was not found in menu '{$menu->menu_name}'; skipping Basic Queues menu item.\n";
            return;
        }

        $menuItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_parent_uuid', $applicationsItem->menu_item_uuid)
            ->where($this->basicQueuesMenuMatcher())
            ->first();
        $menuItemIsUnderApplications = (bool) $menuItem;

        if (! $menuItem) {
            $menuItem = MenuItem::query()
                ->where('menu_uuid', $menu->menu_uuid)
                ->where($this->basicQueuesMenuMatcher())
                ->first();
        }

        if ($menuItem) {
            $menuItem->forceFill([
                'menu_item_title' => 'Basic Queues',
                'menu_item_link' => '/basic-queues',
                'menu_item_parent_uuid' => $applicationsItem->menu_item_uuid,
                'menu_item_category' => $menuItem->menu_item_category ?: 'internal',
                'menu_item_protected' => $menuItem->menu_item_protected ?: 'false',
                'menu_item_order' => $menuItemIsUnderApplications && $menuItem->menu_item_order
                    ? $menuItem->menu_item_order
                    : $this->nextMenuItemOrder($menu, $applicationsItem),
            ])->save();

            echo "Basic Queues menu item already exists; ensured it is under Applications with the correct title and link.\n";
        } else {
            $menuItem = MenuItem::query()->create([
                'menu_item_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_parent_uuid' => $applicationsItem->menu_item_uuid,
                'menu_item_title' => 'Basic Queues',
                'menu_item_link' => '/basic-queues',
                'menu_item_icon' => '',
                'menu_item_category' => 'internal',
                'menu_item_protected' => 'false',
                'menu_item_order' => $this->nextMenuItemOrder($menu, $applicationsItem),
            ]);

            echo "Added Basic Queues menu item under Applications.\n";
        }

        $this->ensureMenuLanguage($menu, $menuItem);
        $this->ensureMenuItemGroups($menu, $menuItem, ['superadmin', 'admin']);
    }

    private function ensureActiveBasicQueuesStatusMenuItem(): void
    {
        $menu = Menu::query()
            ->where('menu_name', 'fspbx')
            ->first();

        if (! $menu) {
            echo "Menu 'fspbx' was not found; skipping Active Basic Queues menu item.\n";
            return;
        }

        $statusItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_title', 'Status')
            ->where(function ($query) {
                $query->whereNull('menu_item_parent_uuid')
                    ->orWhere('menu_item_parent_uuid', '');
            })
            ->first();

        if (! $statusItem) {
            echo "Status menu item was not found in menu '{$menu->menu_name}'; skipping Active Basic Queues menu item.\n";
            return;
        }

        $menuItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_parent_uuid', $statusItem->menu_item_uuid)
            ->where($this->activeBasicQueuesMenuMatcher())
            ->first();
        $menuItemIsUnderStatus = (bool) $menuItem;

        if (! $menuItem) {
            $menuItem = MenuItem::query()
                ->where('menu_uuid', $menu->menu_uuid)
                ->where($this->activeBasicQueuesMenuMatcher())
                ->first();
        }

        if ($menuItem) {
            $menuItem->forceFill([
                'menu_item_title' => 'Active Basic Queues',
                'menu_item_link' => '/active-basic-queues',
                'menu_item_parent_uuid' => $statusItem->menu_item_uuid,
                'menu_item_category' => $menuItem->menu_item_category ?: 'internal',
                'menu_item_protected' => $menuItem->menu_item_protected ?: 'false',
                'menu_item_order' => $menuItemIsUnderStatus && $menuItem->menu_item_order
                    ? $menuItem->menu_item_order
                    : $this->nextMenuItemOrder($menu, $statusItem),
            ])->save();

            echo "Active Basic Queues menu item already exists; ensured it is under Status with the correct title and link.\n";
        } else {
            $menuItem = MenuItem::query()->create([
                'menu_item_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_parent_uuid' => $statusItem->menu_item_uuid,
                'menu_item_title' => 'Active Basic Queues',
                'menu_item_link' => '/active-basic-queues',
                'menu_item_icon' => '',
                'menu_item_category' => 'internal',
                'menu_item_protected' => 'false',
                'menu_item_order' => $this->nextMenuItemOrder($menu, $statusItem),
            ]);

            echo "Added Active Basic Queues menu item under Status.\n";
        }

        $this->ensureMenuLanguage($menu, $menuItem);
        $this->ensureMenuItemGroups($menu, $menuItem, ['superadmin', 'admin']);
    }

    private function basicQueuesMenuMatcher(): callable
    {
        return function ($query) {
            $query->where('menu_item_link', '/basic-queues')
                ->orWhereIn('menu_item_title', ['Basic Queue', 'Basic Queues']);
        };
    }

    private function activeBasicQueuesMenuMatcher(): callable
    {
        return function ($query) {
            $query->where('menu_item_link', '/active-basic-queues')
                ->orWhereIn('menu_item_title', ['Active Basic Queues', 'Active Call Center']);
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
            if ($language->menu_item_title !== $menuItem->menu_item_title) {
                $language->forceFill([
                    'menu_item_title' => $menuItem->menu_item_title,
                ])->save();
            }

            return;
        }

        MenuLanguage::query()->create([
            'menu_language_uuid' => (string) Str::uuid(),
            'menu_uuid' => $menu->menu_uuid,
            'menu_item_uuid' => $menuItem->menu_item_uuid,
            'menu_language' => 'en-us',
            'menu_item_title' => $menuItem->menu_item_title,
        ]);
    }

    private function ensureMenuItemGroups(Menu $menu, MenuItem $menuItem, array $groupNames): void
    {
        foreach ($groupNames as $groupName) {
            $group = Groups::query()
                ->where('group_name', $groupName)
                ->first();

            if (! $group) {
                echo "Group '{$groupName}' not found; Basic Queues menu access not created for it.\n";
                continue;
            }

            $exists = MenuItemGroup::query()
                ->where('menu_item_uuid', $menuItem->menu_item_uuid)
                ->where('group_uuid', $group->group_uuid)
                ->exists();

            if ($exists) {
                echo "Basic Queues menu access already exists for group '{$groupName}'.\n";
                continue;
            }

            MenuItemGroup::query()->create([
                'menu_item_group_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_uuid' => $menuItem->menu_item_uuid,
                'group_name' => $groupName,
                'group_uuid' => $group->group_uuid,
            ]);

            echo "Granted Basic Queues menu access to group '{$groupName}'.\n";
        }
    }

    private function removeObsoleteFspbxMenuItems(): void
    {
        $menu = Menu::query()
            ->where('menu_name', 'fspbx')
            ->first();

        if (! $menu) {
            echo "Menu 'fspbx' was not found; skipping obsolete menu item cleanup.\n";
            return;
        }

        $menuItemUuids = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->whereIn('menu_item_link', self::OBSOLETE_FSPBX_MENU_LINKS)
            ->pluck('menu_item_uuid')
            ->all();

        if ($menuItemUuids === []) {
            echo "No obsolete fspbx menu items found.\n";
            return;
        }

        $menuItemUuids = $this->withMenuItemDescendants($menu, $menuItemUuids);

        MenuLanguage::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->whereIn('menu_item_uuid', $menuItemUuids)
            ->delete();

        MenuItemGroup::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->whereIn('menu_item_uuid', $menuItemUuids)
            ->delete();

        $deleted = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->whereIn('menu_item_uuid', $menuItemUuids)
            ->delete();

        echo "Removed {$deleted} obsolete fspbx menu item(s) and associated access/language rows.\n";
    }

    private function withMenuItemDescendants(Menu $menu, array $menuItemUuids): array
    {
        $allMenuItemUuids = array_values(array_unique(array_filter($menuItemUuids)));
        $pendingParentUuids = $allMenuItemUuids;

        while ($pendingParentUuids !== []) {
            $childUuids = MenuItem::query()
                ->where('menu_uuid', $menu->menu_uuid)
                ->whereIn('menu_item_parent_uuid', $pendingParentUuids)
                ->pluck('menu_item_uuid')
                ->all();

            $newChildUuids = array_values(array_diff($childUuids, $allMenuItemUuids));

            if ($newChildUuids === []) {
                break;
            }

            $allMenuItemUuids = array_merge($allMenuItemUuids, $newChildUuids);
            $pendingParentUuids = $newChildUuids;
        }

        return $allMenuItemUuids;
    }

    private function removeObsoleteMenuItemsFromAllMenus(): void
    {
        $menuItemUuids = MenuItem::query()
            ->whereIn('menu_item_link', self::OBSOLETE_ALL_MENU_LINKS)
            ->pluck('menu_item_uuid')
            ->all();

        if ($menuItemUuids === []) {
            echo "No obsolete global menu items found.\n";
            return;
        }

        $menuItemUuids = $this->withMenuItemDescendantsAcrossMenus($menuItemUuids);

        MenuLanguage::query()
            ->whereIn('menu_item_uuid', $menuItemUuids)
            ->delete();

        MenuItemGroup::query()
            ->whereIn('menu_item_uuid', $menuItemUuids)
            ->delete();

        $deleted = MenuItem::query()
            ->whereIn('menu_item_uuid', $menuItemUuids)
            ->delete();

        echo "Removed {$deleted} obsolete menu item(s) from all menus and associated access/language rows.\n";
    }

    private function withMenuItemDescendantsAcrossMenus(array $menuItemUuids): array
    {
        $allMenuItemUuids = array_values(array_unique(array_filter($menuItemUuids)));
        $pendingParentUuids = $allMenuItemUuids;

        while ($pendingParentUuids !== []) {
            $childUuids = MenuItem::query()
                ->whereIn('menu_item_parent_uuid', $pendingParentUuids)
                ->pluck('menu_item_uuid')
                ->all();

            $newChildUuids = array_values(array_diff($childUuids, $allMenuItemUuids));

            if ($newChildUuids === []) {
                break;
            }

            $allMenuItemUuids = array_merge($allMenuItemUuids, $newChildUuids);
            $pendingParentUuids = $newChildUuids;
        }

        return $allMenuItemUuids;
    }

    private function patchLocalStreamXmlGenerator(): void
    {
        $targets = [
            'future-install Music on Hold XML generator' => base_path('public/app/switch/resources/scripts/app/xml_handler/resources/scripts/configuration/local_stream.conf.lua'),
            'installed Music on Hold XML generator' => '/usr/share/freeswitch/scripts/app/xml_handler/resources/scripts/configuration/local_stream.conf.lua',
        ];

        foreach ($targets as $label => $path) {
            $this->patchLocalStreamXmlGeneratorFile($path, $label);
        }
    }

    private function patchLocalStreamXmlGeneratorFile(string $path, string $label): void
    {
        if (! File::exists($path)) {
            echo ucfirst($label) . " was not found; skipping patch.\n";
            return;
        }

        $contents = File::get($path);
        $original = $contents;

        $contents = str_replace(
            'sql = sql .. "order by s.music_on_hold_name asc "',
            'sql = sql .. "order by d.domain_name asc, s.music_on_hold_name asc, s.music_on_hold_rate asc "',
            $contents
        );

        $contents = str_replace(
            <<<'LUA'
				--combine the name, domain_name and the rate 
				name = '';
				if (row.domain_uuid ~= nil and string.len(row.domain_uuid) > 0) then
					name = row.domain_name..'/';
				end
				name = name .. row.music_on_hold_name;
				if (row.music_on_hold_rate ~= nil and #row.music_on_hold_rate > 0) then
					name = name .. '/' .. row.music_on_hold_rate;
				end
LUA,
            <<<'LUA'
				--combine the name and domain_name
				name = '';
				if (row.domain_uuid ~= nil and string.len(row.domain_uuid) > 0) then
					name = row.domain_name..'/';
				end
				name = name .. row.music_on_hold_name;
LUA,
            $contents
        );

        $contents = str_replace(
            <<<'LUA'
				rate = row.music_on_hold_rate;
				if rate == '' then
					rate = '48000';
				end
LUA,
            <<<'LUA'
				rate = row.music_on_hold_rate;
				if rate == nil or rate == '' then
					rate = '48000';
				end

				--set channels and interval
				channels = row.music_on_hold_channels;
				if channels == nil or channels == '' then
					channels = '1';
				end
				interval = row.music_on_hold_interval;
				if interval == nil or interval == '' then
					interval = '20';
				end
LUA,
            $contents
        );

        $contents = str_replace(
            <<<'LUA'
				xml:append([[			<param name="channels" value="1"/>]]);
				xml:append([[			<param name="interval" value="20"/>]]);
LUA,
            <<<'LUA'
				xml:append([[			<param name="channels" value="]] .. xml.sanitize(channels) .. [["/>]]);
				xml:append([[			<param name="interval" value="]] .. xml.sanitize(interval) .. [["/>]]);
LUA,
            $contents
        );

        if ($contents === $original) {
            echo ucfirst($label) . " already up to date.\n";
            return;
        }

        try {
            File::put($path, $contents);
            echo "Patched {$label}.\n";
        } catch (Throwable $exception) {
            echo "Could not patch {$label}: {$exception->getMessage()}\n";
        }
    }
}
