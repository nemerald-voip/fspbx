<?php

namespace App\Console\Commands\Updates;

use App\Models\Groups;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use App\Models\ProvisioningTemplate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class Update194
{
    private const VERSION = '1.9.4';
    private const TITLE = 'Phonebooks';
    private const LINK = '/phonebooks';
    private const YEALINK_VENDOR = 'yealink';
    private const YEALINK_TEMPLATE_RENAMES = [
        't34w' => 'T3',
        't44u' => 'T4',
        't44w' => 'T4W',
        't74u' => 'T7',
        't74w' => 'T7W',
        't85w' => 'T8W',
    ];

    public function apply(): bool
    {
        try {
            // Permissions, group assignment, and the phonebook_default_uuids
            // provision setting are handled by DatabaseSeeder (which runs on every
            // update). This step only adds the application menu item, which the
            // seeder does not manage.
            $this->ensureApplicationMenuItem();
            $this->migrateYealinkFamilyTemplates();

            echo "Update " . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function ensureApplicationMenuItem(): void
    {
        $menu = Menu::query()->where('menu_name', 'fspbx')->first();

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
                $language->forceFill(['menu_item_title' => self::TITLE])->save();
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
            $group = Groups::query()->where('group_name', $groupName)->first();

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

    private function migrateYealinkFamilyTemplates(): void
    {
        if (! Schema::hasTable('provisioning_templates')) {
            echo "Provisioning templates table not found; skipping Yealink family template migration.\n";
            return;
        }

        if (! Schema::hasTable('v_devices')) {
            echo "Devices table not found; skipping Yealink family template migration.\n";
            return;
        }

        if (! Schema::hasColumn('v_devices', 'device_template_uuid')) {
            echo "Device template UUID column not found; skipping Yealink family template migration.\n";
            return;
        }

        $exitCode = Artisan::call('prov:templates:seed --vendor=yealink --no-interaction');

        if ($exitCode !== 0) {
            throw new \RuntimeException('Unable to seed Yealink provisioning templates before migration.');
        }

        echo "Seeded Yealink family provisioning templates.\n";

        foreach (self::YEALINK_TEMPLATE_RENAMES as $oldName => $newName) {
            $this->migrateYealinkTemplate($oldName, $newName);
        }
    }

    private function migrateYealinkTemplate(string $oldName, string $newName): void
    {
        DB::transaction(function () use ($oldName, $newName) {
            $newTemplate = $this->findDefaultYealinkTemplate($newName);

            if (! $newTemplate) {
                throw new \RuntimeException("New Yealink default template '{$newName}' was not found.");
            }

            $oldTemplateUuids = ProvisioningTemplate::query()
                ->where('vendor', self::YEALINK_VENDOR)
                ->where('type', 'default')
                ->whereRaw('LOWER(name) = ?', [strtolower($oldName)])
                ->pluck('template_uuid')
                ->all();

            $uuidDevicesUpdated = 0;

            if ($oldTemplateUuids !== []) {
                $uuidDevicesUpdated = DB::table('v_devices')
                    ->whereIn('device_template_uuid', $oldTemplateUuids)
                    ->update([
                        'device_template_uuid' => $newTemplate->template_uuid,
                    ]);
            }

            $customTemplatesUpdated = ProvisioningTemplate::query()
                ->where('vendor', self::YEALINK_VENDOR)
                ->where('type', 'custom')
                ->whereRaw('LOWER(base_template) = ?', [strtolower($oldName)])
                ->update(['base_template' => $newName]);

            $defaultTemplatesDeleted = $oldTemplateUuids === []
                ? 0
                : ProvisioningTemplate::query()
                    ->whereIn('template_uuid', $oldTemplateUuids)
                    ->delete();

            echo "Migrated Yealink {$oldName} to {$newName}: "
                . "{$uuidDevicesUpdated} UUID-backed device(s), "
                . "{$customTemplatesUpdated} custom base reference(s), "
                . "{$defaultTemplatesDeleted} old default row(s) removed.\n";
        });
    }

    private function findDefaultYealinkTemplate(string $name): ?ProvisioningTemplate
    {
        return ProvisioningTemplate::query()
            ->where('vendor', self::YEALINK_VENDOR)
            ->where('type', 'default')
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->first();
    }
}
