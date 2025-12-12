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
        $menuDescription = 'FS PBX Recommended Menu';

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

        // Define hierarchical menu items
        $categories = [
            [
                'title' => 'Home',
                'link' => null,
                'groups' => ['superadmin', 'admin', 'user', 'fax', 'agent'],
                'subcategories' => [
                    ['title' => 'Account Settings', 'link' => '/core/users/user_edit.php?id=user', 'groups' => ['superadmin', 'admin', 'user', 'agent']],
                    ['title' => 'Dashboard', 'link' => '/dashboard', 'groups' => ['superadmin', 'admin', 'user', 'agent']],
                    ['title' => 'Logout', 'link' => '/logout', 'groups' => ['superadmin', 'admin', 'user', 'fax', 'agent']],
                ],
            ],
            [
                'title' => 'Accounts',
                'link' => null,
                'groups' => ['superadmin', 'admin'],
                'subcategories' => [
                    ['title' => 'Devices', 'link' => '/devices','groups' => ['superadmin', 'admin']],
                    ['title' => 'Extensions', 'link' => '/extensions', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'Gateways', 'link' => '/app/gateways/gateways.php', 'groups' => ['superadmin']],
                    ['title' => 'Users', 'link' => '/users', 'groups' => ['superadmin', 'admin']],
                ],
            ],
            [
                'title' => 'Dialplan',
                'link' => null,
                'groups' => ['superadmin', 'admin'],
                'subcategories' => [
                    ['title' => 'Dialplan Manager', 'link' => '/app/dialplans/dialplans.php', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'Phone Numbers', 'link' => '/phone-numbers','groups' => ['superadmin', 'admin']],
                    ['title' => 'Inbound Routes', 'link' => '/app/dialplans/dialplans.php?app_uuid=c03b422e-13a8-bd1b-e42b-b6b9b4d27ce4', 'groups' => ['superadmin']],
                    ['title' => 'Outbound Routes', 'link' => '/app/dialplans/dialplans.php?app_uuid=8c914ec3-9fc0-8ab5-4cda-6c9288bdc9a3', 'groups' => ['superadmin']],
                ],
            ],
            [
                'title' => 'Applications',
                'link' => null,
                'groups' => ['superadmin', 'admin', 'user', 'fax', 'agent'],
                'subcategories' => [
                    ['title' => 'Bridges', 'link' => '/app/bridges/bridges.php', 'groups' => ['superadmin']],
                    ['title' => 'Call Block', 'link' => '/app/call_block/call_block.php', 'groups' => ['superadmin', 'admin', 'user']],
                    ['title' => 'Call History', 'link' => '/call-detail-records', 'groups' => ['superadmin', 'admin', 'user']],
                    ['title' => 'Call Flows', 'link' => '/app/call_flows/call_flows.php', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'Conference Centers', 'link' => '/app/conference_centers/conference_centers.php', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'Conference Controls', 'link' => '/app/conference_controls/conference_controls.php', 'groups' => ['superadmin']],
                    ['title' => 'Conference Profiles', 'link' => '/app/conference_profiles/conference_profiles.php', 'groups' => ['superadmin']],
                    ['title' => 'Conferences', 'link' => '/app/conferences/conferences.php', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'Faxes', 'link' => '/faxes', 'groups' => ['superadmin', 'admin', 'fax', 'user']],
                    ['title' => 'Virtual Receptionists', 'link' => '/virtual-receptionists', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'Messages', 'link' => '/messages', 'groups' => ['superadmin']],
                    ['title' => 'Music on Hold', 'link' => '/app/music_on_hold/music_on_hold.php','groups' => ['superadmin']],
                    ['title' => 'Operator Panel', 'link' => '/app/basic_operator_panel/index.php', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'Recordings', 'link' => '/app/recordings/recordings.php', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'Ring Groups', 'link' => '/ring-groups', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'Streams', 'link' => '/app/streams/streams.php', 'groups' => ['superadmin']],
                    ['title' => 'Business Hours', 'link' => '/business-hours', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'Voicemails', 'link' => '/voicemails', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'Wakeup Calls', 'link' => '/wakeup-calls', 'groups' => ['superadmin', 'admin']],
                ],
            ],
            [
                'title' => 'Status',
                'link' => null,
                'groups' => ['superadmin', 'admin'],
                'subcategories' => [
                    ['title' => 'Active Calls', 'link' => '/active-calls', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'Active Conferences', 'link' => '/app/conferences_active/conferences_active.php', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'Active Queues', 'link' => '/app/fifo_list/fifo_list.php', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'Extension Statistics', 'link' => '/extension-statistics', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'Firewall', 'link' => '/firewall', 'groups' => ['superadmin']],
                    ['title' => 'Fax Queue', 'link' => '/faxqueue', 'groups' => ['superadmin']],
                    ['title' => 'Logs', 'link' => '/logs', 'groups' => ['superadmin']],
                    ['title' => 'Log Viewer', 'link' => '/app/log_viewer/log_viewer.php', 'groups' => ['superadmin']],
                    ['title' => 'Registrations', 'link' => '/registrations', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'SIP Status', 'link' => '/app/sip_status/sip_status.php', 'groups' => ['superadmin']],
                    ['title' => 'System Status', 'link' => '/app/system/system.php', 'groups' => ['superadmin']],
                    ['title' => 'User Logs', 'link' => '/user-logs', 'groups' => ['superadmin']],
                ],
            ],
            [
                'title' => 'Advanced',
                'link' => null,
                'groups' => ['superadmin'],
                'subcategories' => [
                    ['title' => 'Access Control', 'link' => '/app/access_controls/access_controls.php', 'groups' => ['superadmin']],
                    ['title' => 'Default Settings', 'link' => '/core/default_settings/default_settings.php', 'groups' => ['superadmin']],
                    ['title' => 'Domains', 'link' => '/domains', 'groups' => ['superadmin']],
                    ['title' => 'Email templates', 'link' => '/app/email_templates/email_templates.php', 'groups' => ['superadmin']],
                    ['title' => 'Group Manager', 'link' => '/groups', 'groups' => ['superadmin']],
                    ['title' => 'Menu Manager', 'link' => '/core/menu/menu.php', 'groups' => ['superadmin']],
                    ['title' => 'Message Settings', 'link' => '/message-settings', 'groups' => ['superadmin']],
                    ['title' => 'Modules', 'link' => '/app/modules/modules.php', 'groups' => ['superadmin']],
                    ['title' => 'Pro Features', 'link' => '/pro-features', 'groups' => ['superadmin']],
                    ['title' => 'Provision Templates', 'link' => '/app/edit/index.php?dir=provision', 'groups' => ['superadmin']],
                    ['title' => 'Ringotel App Settings', 'link' => '/apps', 'groups' => ['superadmin', 'admin']],
                    ['title' => 'SIP Profiles', 'link' => '/app/sip_profiles/sip_profiles.php', 'groups' => ['superadmin']],
                    ['title' => 'System Settings', 'link' => '/system-settings', 'groups' => ['superadmin']],
                    ['title' => 'Transactions', 'link' => '/app/database_transactions/database_transactions.php', 'groups' => ['superadmin']],
                    ['title' => 'Variables', 'link' => '/app/vars/vars.php', 'groups' => ['superadmin']],
                ],
            ],
        ];

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
