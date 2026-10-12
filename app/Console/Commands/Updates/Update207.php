<?php

namespace App\Console\Commands\Updates;

use App\Models\Groups;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use Illuminate\Support\Str;
use Throwable;

class Update207
{
    private const VERSION = '2.0.7';

    public function apply(): bool
    {
        try {
            $this->ensureMenuItem();

            echo "Update " . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function ensureMenuItem(): void
    {
        $menu = Menu::query()->where('menu_name', 'fspbx')->first();
        if (! $menu) {
            echo "Menu 'fspbx' was not found; skipping Paging Groups menu item.\n";
            return;
        }

        $parent = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_title', 'Applications')
            ->whereNull('menu_item_parent_uuid')
            ->first();
        if (! $parent) {
            echo "Applications menu item was not found; skipping Paging Groups menu item.\n";
            return;
        }

        $item = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_link', '/paging-groups')
            ->first();

        if (! $item) {
            $item = MenuItem::query()->create([
                'menu_item_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_parent_uuid' => $parent->menu_item_uuid,
                'menu_item_title' => 'Paging Groups',
                'menu_item_link' => '/paging-groups',
                'menu_item_icon' => '',
                'menu_item_category' => 'internal',
                'menu_item_protected' => 'false',
                'menu_item_order' => ((int) MenuItem::query()
                    ->where('menu_uuid', $menu->menu_uuid)
                    ->where('menu_item_parent_uuid', $parent->menu_item_uuid)
                    ->max('menu_item_order')) + 1,
            ]);
        }

        MenuLanguage::query()->firstOrCreate([
            'menu_uuid' => $menu->menu_uuid,
            'menu_item_uuid' => $item->menu_item_uuid,
            'menu_language' => 'en-us',
        ], [
            'menu_language_uuid' => (string) Str::uuid(),
            'menu_item_title' => 'Paging Groups',
        ]);

        foreach (['superadmin', 'admin'] as $groupName) {
            $group = Groups::query()->where('group_name', $groupName)->first();
            if (! $group) {
                continue;
            }

            MenuItemGroup::query()->firstOrCreate([
                'menu_item_uuid' => $item->menu_item_uuid,
                'group_uuid' => $group->group_uuid,
            ], [
                'menu_item_group_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'group_name' => $groupName,
            ]);
        }

        echo "Ensured the Paging Groups menu item.\n";
    }
}
