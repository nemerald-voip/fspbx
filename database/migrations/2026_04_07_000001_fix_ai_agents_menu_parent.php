<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $vr = DB::table('v_menu_items')
            ->where('menu_item_link', '/virtual-receptionists')
            ->first();

        if (!$vr) {
            return;
        }

        $aiAgents = DB::table('v_menu_items')
            ->where('menu_item_link', '/ai-agents')
            ->first();

        if (!$aiAgents) {
            return;
        }

        if ($aiAgents->menu_item_parent_uuid === $vr->menu_item_parent_uuid
            && $aiAgents->menu_item_order !== null) {
            return;
        }

        DB::table('v_menu_items')
            ->where('menu_item_uuid', $aiAgents->menu_item_uuid)
            ->update([
                'menu_uuid'             => $vr->menu_uuid,
                'menu_item_parent_uuid' => $vr->menu_item_parent_uuid,
                'menu_item_order'       => 15,
            ]);

        DB::table('v_menu_item_groups')
            ->where('menu_item_uuid', $aiAgents->menu_item_uuid)
            ->update(['menu_uuid' => $vr->menu_uuid]);
    }

    public function down(): void
    {
        // no-op: don't re-break the menu
    }
};
