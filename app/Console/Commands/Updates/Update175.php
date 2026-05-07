<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

class Update175
{
    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        try {
            DB::transaction(function () {
                $this->updateActiveConferenceMenuItems();
                $this->updateConferenceMenuItems();
                $this->updateConferenceCenterMenuItems();
                $this->removeConferenceControlMenuItems();
                $this->removeConferenceProfileMenuItems();
                $this->updateConferenceRoomMenuItems();
            });

            return true;
        } catch (\Throwable $e) {
            echo "Error applying update 175: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function updateConferenceCenterMenuItems(): void
    {
        $this->updateMenuItemLink(
            'Conference Centers',
            '/app/conference_centers/conference_centers.php',
            '/conference-centers'
        );
    }

    private function updateConferenceMenuItems(): void
    {
        $this->updateMenuItemLink(
            'Conferences',
            '/app/conferences/conferences.php',
            '/conferences'
        );
    }

    private function updateActiveConferenceMenuItems(): void
    {
        $this->updateMenuItemLink(
            'Active Conferences',
            '/app/conferences_active/conferences_active.php',
            '/active-conferences'
        );
    }

    private function removeConferenceControlMenuItems(): void
    {
        $menuItemIds = MenuItem::query()
            ->where(function ($query) {
                $query->where('menu_item_title', 'Conference Controls')
                    ->orWhereIn('menu_item_link', [
                        '/app/conference_controls/conference_controls.php',
                        '/conference-controls',
                    ]);
            })
            ->pluck('menu_item_uuid');

        if ($menuItemIds->isEmpty()) {
            echo "No Conference Controls menu items required removal.\n";
            return;
        }

        DB::table('v_menu_item_groups')
            ->whereIn('menu_item_uuid', $menuItemIds)
            ->delete();

        DB::table('v_menu_languages')
            ->whereIn('menu_item_uuid', $menuItemIds)
            ->delete();

        $deletedMenuItems = MenuItem::query()
            ->whereIn('menu_item_uuid', $menuItemIds)
            ->delete();

        echo "Removed {$deletedMenuItems} Conference Controls menu item(s).\n";
    }

    private function removeConferenceProfileMenuItems(): void
    {
        $menuItemIds = MenuItem::query()
            ->where(function ($query) {
                $query->where('menu_item_title', 'Conference Profiles')
                    ->orWhereIn('menu_item_link', [
                        '/app/conference_profiles/conference_profiles.php',
                        '/conference-profiles',
                    ]);
            })
            ->pluck('menu_item_uuid');

        if ($menuItemIds->isEmpty()) {
            echo "No Conference Profiles menu items required removal.\n";
            return;
        }

        DB::table('v_menu_item_groups')
            ->whereIn('menu_item_uuid', $menuItemIds)
            ->delete();

        DB::table('v_menu_languages')
            ->whereIn('menu_item_uuid', $menuItemIds)
            ->delete();

        $deletedMenuItems = MenuItem::query()
            ->whereIn('menu_item_uuid', $menuItemIds)
            ->delete();

        echo "Removed {$deletedMenuItems} Conference Profiles menu item(s).\n";
    }

    private function updateConferenceRoomMenuItems(): void
    {
        $this->updateMenuItemLink(
            'Conference Rooms',
            '/app/conference_centers/conference_rooms.php',
            '/conference-rooms'
        );
    }

    private function updateMenuItemLink(string $title, string $from, string $to): void
    {
        $updatedMenuItems = MenuItem::query()
            ->where('menu_item_title', $title)
            ->where('menu_item_link', $from)
            ->update([
                'menu_item_link' => $to,
            ]);

        echo $updatedMenuItems === 0
            ? "No {$title} menu items required updating.\n"
            : "Updated {$updatedMenuItems} {$title} menu item(s).\n";
    }
}
