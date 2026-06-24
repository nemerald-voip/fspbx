<?php

namespace App\Console\Commands\Updates;

use App\Models\Groups;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use Illuminate\Support\Str;
use Throwable;

class Update192
{
    private const VERSION = '1.9.2';
    private const DIALPLAN_TEMPLATE_DIR = 'public/app/dialplans/resources/switch/conf/dialplan';

    private const DIALPLAN_TEMPLATE_FILES = [
        'public/app/dialplans/resources/switch/conf/dialplan/100_e911_peerless.xml',
        'public/app/dialplans/resources/switch/conf/dialplan/100_e911_synch.xml',
        'public/app/dialplans/resources/switch/conf/dialplan/100_e911_thinq.xml',
    ];

    public function apply(): bool
    {
        try {
            $removed = $this->removeLegacyE911DialplanTemplates();
            echo "Removed {$removed} legacy E911 dialplan template file(s).\n";

            $this->ensureMenuItem(
                parentTitle: 'Applications',
                title: 'Basic Queues',
                link: '/basic-queues',
                matchingTitles: ['Basic Queue', 'Basic Queues'],
                groupNames: ['superadmin', 'admin'],
            );

            $this->ensureMenuItem(
                parentTitle: 'Status',
                title: 'Active Basic Queues',
                link: '/active-basic-queues',
                matchingTitles: ['Active Basic Queues', 'Active Call Center'],
                groupNames: ['superadmin', 'admin'],
            );

            echo "Menu changes are applied to new sessions; users may need to log out and back in to refresh their top menu.\n";
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ': ' . $exception->getMessage() . "\n";

            return false;
        }
    }

    private function removeLegacyE911DialplanTemplates(): int
    {
        $removed = 0;

        $this->ensureDialplanTemplateDirectoryIsWritable();

        foreach (self::DIALPLAN_TEMPLATE_FILES as $relativePath) {
            $path = base_path($relativePath);

            if (! file_exists($path)) {
                continue;
            }

            if (! is_file($path)) {
                throw new \RuntimeException($path . ' exists but is not a file.');
            }

            $this->ensureLegacyE911TemplateIsWritable($path);

            if (! @unlink($path)) {
                throw new \RuntimeException(
                    'Unable to remove ' . $path . '. Check ownership and write permissions on its containing directory.'
                );
            }

            $removed++;
        }

        return $removed;
    }

    private function ensureDialplanTemplateDirectoryIsWritable(): void
    {
        $directory = base_path(self::DIALPLAN_TEMPLATE_DIR);

        if (! is_dir($directory)) {
            throw new \RuntimeException($directory . ' does not exist or is not a directory.');
        }

        @chown($directory, 'www-data');
        @chgrp($directory, 'www-data');
        @chmod($directory, 0775);
        clearstatcache(true, $directory);

        if (! is_writable($directory)) {
            throw new \RuntimeException(
                'Unable to make ' . $directory . ' writable. Run this update with permissions to change that directory owner/mode.'
            );
        }
    }

    private function ensureLegacyE911TemplateIsWritable(string $path): void
    {
        @chown($path, 'www-data');
        @chgrp($path, 'www-data');
        @chmod($path, 0664);
        clearstatcache(true, $path);
    }

    private function ensureMenuItem(
        string $parentTitle,
        string $title,
        string $link,
        array $matchingTitles,
        array $groupNames
    ): void {
        $menu = Menu::query()
            ->where('menu_name', 'fspbx')
            ->first();

        if (! $menu) {
            echo "Menu 'fspbx' was not found; skipping {$title} menu item.\n";
            return;
        }

        $parentItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_title', $parentTitle)
            ->whereNull('menu_item_parent_uuid')
            ->first();

        if (! $parentItem) {
            echo "{$parentTitle} menu item was not found in menu '{$menu->menu_name}'; skipping {$title} menu item.\n";
            return;
        }

        $menuItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_parent_uuid', $parentItem->menu_item_uuid)
            ->where(fn ($query) => $this->matchMenuItem($query, $link, $matchingTitles))
            ->first();

        $menuItemIsUnderParent = (bool) $menuItem;

        if (! $menuItem) {
            $menuItem = MenuItem::query()
                ->where('menu_uuid', $menu->menu_uuid)
                ->where(fn ($query) => $this->matchMenuItem($query, $link, $matchingTitles))
                ->first();
        }

        if ($menuItem) {
            $menuItem->forceFill([
                'menu_item_title' => $title,
                'menu_item_link' => $link,
                'menu_item_parent_uuid' => $parentItem->menu_item_uuid,
                'menu_item_category' => $menuItem->menu_item_category ?: 'internal',
                'menu_item_protected' => $menuItem->menu_item_protected ?: 'false',
                'menu_item_order' => $menuItemIsUnderParent && $menuItem->menu_item_order
                    ? $menuItem->menu_item_order
                    : $this->nextMenuItemOrder($menu, $parentItem),
            ])->save();

            echo "{$title} menu item already exists; ensured it is under {$parentTitle} with the correct title and link.\n";
        } else {
            $menuItem = MenuItem::query()->create([
                'menu_item_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_parent_uuid' => $parentItem->menu_item_uuid,
                'menu_item_title' => $title,
                'menu_item_link' => $link,
                'menu_item_icon' => '',
                'menu_item_category' => 'internal',
                'menu_item_protected' => 'false',
                'menu_item_order' => $this->nextMenuItemOrder($menu, $parentItem),
            ]);

            echo "Added {$title} menu item under {$parentTitle}.\n";
        }

        $this->ensureMenuLanguage($menu, $menuItem);
        $this->ensureMenuItemGroups($menu, $menuItem, $groupNames);
    }

    private function matchMenuItem($query, string $link, array $matchingTitles): void
    {
        $query->where('menu_item_link', $link)
            ->orWhereIn('menu_item_title', $matchingTitles);
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
                echo "Group '{$groupName}' not found; {$menuItem->menu_item_title} menu access not created for it.\n";
                continue;
            }

            $exists = MenuItemGroup::query()
                ->where('menu_item_uuid', $menuItem->menu_item_uuid)
                ->where('group_uuid', $group->group_uuid)
                ->exists();

            if ($exists) {
                continue;
            }

            MenuItemGroup::query()->create([
                'menu_item_group_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_uuid' => $menuItem->menu_item_uuid,
                'group_name' => $groupName,
                'group_uuid' => $group->group_uuid,
            ]);

            echo "Granted {$menuItem->menu_item_title} menu access to group '{$groupName}'.\n";
        }
    }
}
