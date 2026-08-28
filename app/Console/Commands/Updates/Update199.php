<?php

namespace App\Console\Commands\Updates;

use App\Models\Groups;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use Illuminate\Support\Str;
use Throwable;

class Update199
{
    private const TITLE = 'Dynamic Routes';

    private const LINK = '/dynamic-routes';

    public function apply(): bool
    {
        try {
            $this->ensureApplicationMenuItem();

            echo "Update 1.9.9 completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update 1.9.9: {$exception->getMessage()}\n";
            return false;
        }
    }

    private function ensureApplicationMenuItem(): void
    {
        $menu = Menu::query()->where('menu_name', 'fspbx')->first();

        if (! $menu) {
            echo "Menu 'fspbx' was not found; skipping ".self::TITLE." menu item.\n";
            return;
        }

        $applicationsItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_title', 'Applications')
            ->whereNull('menu_item_parent_uuid')
            ->first();

        if (! $applicationsItem) {
            echo "Applications menu item was not found; skipping ".self::TITLE." menu item.\n";
            return;
        }

        $menuItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where(function ($query) {
                $query->where('menu_item_link', self::LINK)
                    ->orWhere('menu_item_title', self::TITLE);
            })
            ->first();

        $values = [
            'menu_uuid' => $menu->menu_uuid,
            'menu_item_parent_uuid' => $applicationsItem->menu_item_uuid,
            'menu_item_title' => self::TITLE,
            'menu_item_link' => self::LINK,
            'menu_item_icon' => '',
            'menu_item_category' => 'internal',
            'menu_item_protected' => 'false',
        ];

        if ($menuItem) {
            $menuItem->forceFill($values)->save();
        } else {
            $menuItem = MenuItem::query()->create($values + [
                'menu_item_uuid' => (string) Str::uuid(),
                'menu_item_order' => ((int) MenuItem::query()
                    ->where('menu_uuid', $menu->menu_uuid)
                    ->where('menu_item_parent_uuid', $applicationsItem->menu_item_uuid)
                    ->max('menu_item_order')) + 1,
            ]);
        }

        $language = MenuLanguage::query()->firstOrCreate([
            'menu_uuid' => $menu->menu_uuid,
            'menu_item_uuid' => $menuItem->menu_item_uuid,
            'menu_language' => 'en-us',
        ], [
            'menu_language_uuid' => (string) Str::uuid(),
            'menu_item_title' => self::TITLE,
        ]);
        $language->forceFill(['menu_item_title' => self::TITLE])->save();

        foreach (['superadmin', 'admin'] as $groupName) {
            $group = Groups::query()->where('group_name', $groupName)->first();

            if (! $group) {
                echo "Group '{$groupName}' was not found; skipping ".self::TITLE." menu access for it.\n";
                continue;
            }

            MenuItemGroup::query()->firstOrCreate([
                'menu_item_uuid' => $menuItem->menu_item_uuid,
                'group_uuid' => $group->group_uuid,
            ], [
                'menu_item_group_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'group_name' => $groupName,
            ]);
        }

        echo "Ensured the ".self::TITLE." menu item.\n";
    }
}
