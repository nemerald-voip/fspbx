<?php

namespace App\Console\Commands\Updates;

use App\Models\Groups;
use App\Models\MenuItem;
use Illuminate\Support\Str;
use App\Models\MenuItemGroup;
use App\Models\DefaultSettings;

class Update0949
{

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {

        $defaultSetting = DefaultSettings::where('default_setting_subcategory', 'menu')
            ->where('default_setting_name', 'uuid')
            ->first();

        if (!$defaultSetting) {
            echo "Default setting for 'menu -> uuid' not found.\n";
            return true;
        }

        $menuUuid = $defaultSetting->default_setting_value;

        // 2) determine the next order position
        $maxOrder = MenuItem::where('menu_uuid', $menuUuid)
            ->max('menu_item_order');

        $nextOrder = (is_numeric($maxOrder) ? $maxOrder : 0) + 1;

        // 3) find the "Advanced" menu item to be the parent
        $advancedItem = MenuItem::where('menu_uuid', $menuUuid)
            ->where('menu_item_title', 'Advanced')
            ->first();

        if (! $advancedItem) {
            echo "Menu item 'Advanced' not found for menu {$menuUuid}\n";
            return true;
        }
        // 4) build and insert the new menu item
        $newMenuItemUuid = Str::uuid()->toString();

        MenuItem::insert([
            'menu_item_uuid'        => $newMenuItemUuid,
            'menu_uuid'             => $menuUuid,
            'menu_item_parent_uuid' => $advancedItem->menu_item_uuid,
            'menu_item_title'       => 'System Settings',
            'menu_item_link'        => '/system-settings',
            'menu_item_icon'        => '',
            'menu_item_category'    => 'internal',
            'menu_item_protected'   => 'false',
            'menu_item_order'       => $nextOrder,

        ]);
        echo "New menu item 'System Settings' created (UUID: {$newMenuItemUuid})\n";



        // 5) now create the permission for a specific group
        $groupName = 'superadmin';  // ← change to the group you want
        $group     = Groups::where('group_name', $groupName)->first();

        if ($group) {
            MenuItemGroup::create([
                'menu_item_group_uuid' => Str::uuid()->toString(),
                'menu_uuid'            => $menuUuid,
                'menu_item_uuid'       => $newMenuItemUuid,
                'group_name'           => $groupName,
                'group_uuid'           => $group->group_uuid,
            ]);
            echo "Granted 'System Settings' access to group '{$groupName}'.\n";
        } else {
            echo "Group '{$groupName}' not found — permission not created.\n";
        }


        return true;
    }
}
