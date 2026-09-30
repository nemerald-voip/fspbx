<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\Groups;
use App\Models\DefaultSettings;
use App\Models\MenuLanguage;
use App\Support\DefaultMenu;

class CreateFSPBXMenu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'menu:create-fspbx {--update : Update existing menu and items if they exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update the FS PBX Recommended Menu with predefined items.';

    /**
     * Whether to update existing records.
     *
     * @var bool
     */
    private bool $shouldUpdate = false;
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->shouldUpdate = $this->option('update');
        $menuName = 'fspbx';
        $menuDescription = 'FS PBX Default Menu';

        // Check if the menu already exists
        $menu = Menu::where('menu_name', $menuName)->first();

        if (!$menu) {
            $this->info("Creating menu: $menuName");

            // Create the menu
            $menu = Menu::create([
                'menu_uuid' => Str::uuid(),
                'menu_name' => $menuName,
                'menu_language' => 'en-us',
                'menu_description' => $menuDescription,
            ]);

            $this->info("Menu created with UUID: {$menu->menu_uuid}");
        } else {
            if ($this->shouldUpdate) {
                $menu->menu_description = $menuDescription;
                $menu->save();
                $this->info("Updated menu description for '$menuName'.");
            } else {
                $this->info("Menu '$menuName' already exists with UUID: {$menu->menu_uuid}");
            }
        }

        // Update the default menu setting
        if (! $this->shouldUpdate) {
            $this->updateDefaultMenuSetting($menu->menu_uuid);
        }

        $categories = DefaultMenu::categories();

        $this->info('Adding menu items...');
        $categoryOrder = 5; // Start category order at 5

        foreach ($categories as $category) {
            $parentUuid = $this->addMenuItem($menu, $category, $categoryOrder);
            $categoryOrder += 5;

            $subcategoryOrder = 1;
            foreach ($category['subcategories'] as $subcategory) {
                $this->addMenuItem($menu, $subcategory, $subcategoryOrder, $parentUuid);
                $subcategoryOrder++;
            }
        }

        $this->info("Menu '$menuName' and items processed successfully.");
        return Command::SUCCESS;
    }

    /**
     * Add a menu item and assign groups.
     *
     * @param Menu $menu
     * @param array $itemData
     * @param int $order
     * @param string|null $parentUuid
     * @return string
     */
    private function addMenuItem(Menu $menu, array $itemData, int $order, string $parentUuid = null): string
    {
        $existingItem = MenuItem::where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_title', $itemData['title'])
            ->where('menu_item_parent_uuid', $parentUuid)
            ->first();

        if ($existingItem) {
            if ($this->shouldUpdate) {
                $existingItem->update([
                    'menu_item_link'   => $itemData['link'],
                    'menu_item_order'  => $order,
                    'menu_item_category' => $itemData['category'] ?? $existingItem->menu_item_category,
                    'menu_item_protected' => $itemData['protected'] ?? $existingItem->menu_item_protected,
                ]);
                $this->info(" - Updated menu item: {$existingItem->menu_item_title} (UUID: {$existingItem->menu_item_uuid})");
            } else {
                $this->warn(" - Skipped: {$itemData['title']} already exists (UUID: {$existingItem->menu_item_uuid})");
            }
            $menuItemUuid = $existingItem->menu_item_uuid;
        } else {
            $menuItem = MenuItem::create([
                'menu_item_uuid'       => Str::uuid(),
                'menu_uuid'            => $menu->menu_uuid,
                'menu_item_title'      => $itemData['title'],
                'menu_item_link'       => $itemData['link'],
                'menu_item_order'      => $order,
                'menu_item_parent_uuid'=> $parentUuid,
                'menu_item_category'   => $itemData['category'] ?? 'internal',
                'menu_item_protected'  => $itemData['protected'] ?? 'false',
            ]);

            $this->info(" - Added menu item: {$menuItem->menu_item_title} with UUID: {$menuItem->menu_item_uuid}");
            $menuItemUuid = $menuItem->menu_item_uuid;
            $this->addMenuLanguage($menu, $menuItem);
        }

        // Assign permission groups
        $this->assignGroupsToMenuItem($menu, $menuItemUuid, $itemData['groups'] ?? []);

        return $menuItemUuid;
    }

    /**
     * Assign groups to a menu item.
     *
     * @param Menu $menu
     * @param string $menuItemUuid
     * @param array $groupNames
     */
    private function assignGroupsToMenuItem(Menu $menu, string $menuItemUuid, array $groupNames)
    {
        foreach ($groupNames as $groupName) {
            // Find the group
            $group = Groups::where('group_name', $groupName)->first();

            if (!$group) {
                $this->warn("   - Group '$groupName' not found. Skipping.");
                continue;
            }

            // Check for existing permission
            $existingPermission = MenuItemGroup::where('menu_item_uuid', $menuItemUuid)
                ->where('group_uuid', $group->group_uuid)
                ->first();

            if ($existingPermission) {
                $this->warn("   - Permission for group '$groupName' already exists. Skipping.");
                continue;
            }

            // Add permission group
            MenuItemGroup::create([
                'menu_item_group_uuid' => Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_uuid' => $menuItemUuid,
                'group_name' => $groupName,
                'group_uuid' => $group->group_uuid,
            ]);

            $this->info("   - Assigned group '$groupName' to menu item UUID: $menuItemUuid.");
        }
    }

    /**
     * Update the default menu UUID in v_default_settings.
     *
     * @param string $newMenuUuid
     */
    private function updateDefaultMenuSetting(string $newMenuUuid)
    {
        $defaultSetting = DefaultSettings::where('default_setting_subcategory', 'menu')
            ->where('default_setting_name', 'uuid')
            ->first();

        if (!$defaultSetting) {
            $this->error("Default setting for 'menu -> uuid' not found.");
            return;
        }

        $oldValue = $defaultSetting->default_setting_value;

        if ($oldValue === $newMenuUuid) {
            $this->info("Default menu UUID is already set to the new menu UUID. No changes made.");
            return;
        }

        $defaultSetting->default_setting_value = $newMenuUuid;
        $defaultSetting->update();

        $this->info("Updated default menu UUID from '$oldValue' to '$newMenuUuid'.");
    }

    /**
     * Add a menu item to v_menu_languages.
     *
     * @param Menu $menu
     * @param MenuItem $menuItem
     */
    private function addMenuLanguage(Menu $menu, MenuItem $menuItem)
    {
        $existingLanguage = MenuLanguage::where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_uuid', $menuItem->menu_item_uuid)
            ->where('menu_language', 'en-us')
            ->first();

        if ($existingLanguage) {
            $this->warn("   - Language entry for '{$menuItem->menu_item_title}' already exists. Skipping.");
            return;
        }

        MenuLanguage::create([
            'menu_language_uuid' => Str::uuid(),
            'menu_uuid' => $menu->menu_uuid,
            'menu_item_uuid' => $menuItem->menu_item_uuid,
            'menu_language' => 'en-us',
            'menu_item_title' => $menuItem->menu_item_title,
        ]);

        $this->info("   - Added language entry for '{$menuItem->menu_item_title}'.");
    }
}
