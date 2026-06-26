<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Find the parent menu item that Virtual Receptionists belongs to (typically "Applications")
        $virtualReceptionist = DB::table('v_menu_items')
            ->where('menu_item_link', '/virtual-receptionists')
            ->first();

        $parentUuid = $virtualReceptionist?->menu_item_parent_uuid;
        $menuUuid = $virtualReceptionist?->menu_uuid;

        // If we can't find the parent, try to find any top-level menu
        if (!$parentUuid || !$menuUuid) {
            $menuUuid = DB::table('v_menu_items')->value('menu_uuid');
            // Use the same parent as Ring Groups as a fallback
            $ringGroup = DB::table('v_menu_items')
                ->where('menu_item_title', 'Ring Groups')
                ->orWhere('menu_item_link', '/app/ring_groups/ring_groups.php')
                ->first();
            $parentUuid = $ringGroup?->menu_item_parent_uuid;
        }

        if (!$menuUuid) {
            return; // No menu system found, skip
        }

        $menuItemUuid = (string) Str::uuid();

        // Check if AI Agents menu item already exists
        $exists = DB::table('v_menu_items')
            ->where('menu_item_title', 'AI Agents')
            ->exists();

        if ($exists) {
            return;
        }

        // Insert the menu item
        DB::table('v_menu_items')->insert([
            'menu_item_uuid' => $menuItemUuid,
            'menu_uuid' => $menuUuid,
            'menu_item_title' => 'AI Agents',
            'menu_item_link' => '/ai-agents',
            'menu_item_category' => 'internal',
            'menu_item_icon' => null,
            'menu_item_parent_uuid' => $parentUuid,
            'menu_item_order' => 15,
            'menu_item_description' => 'ElevenLabs Conversational AI Agents',
            'insert_date' => now(),
            'insert_user' => null,
        ]);

        // Grant access to superadmin and admin groups
        $groups = DB::table('v_groups')
            ->whereIn('group_name', ['superadmin', 'admin'])
            ->pluck('group_uuid', 'group_name');

        foreach ($groups as $groupName => $groupUuid) {
            DB::table('v_menu_item_groups')->insert([
                'menu_item_group_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menuUuid,
                'menu_item_uuid' => $menuItemUuid,
                'group_uuid' => $groupUuid,
                'group_name' => $groupName,
                'insert_date' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $menuItem = DB::table('v_menu_items')
            ->where('menu_item_title', 'AI Agents')
            ->where('menu_item_link', '/ai-agents')
            ->first();

        if ($menuItem) {
            DB::table('v_menu_item_groups')
                ->where('menu_item_uuid', $menuItem->menu_item_uuid)
                ->delete();

            DB::table('v_menu_items')
                ->where('menu_item_uuid', $menuItem->menu_item_uuid)
                ->delete();
        }
    }
};
