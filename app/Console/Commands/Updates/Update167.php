<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use App\Models\MenuLanguage;
use Illuminate\Support\Facades\DB;

class Update167
{
    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        $oldTitle = 'Recordings';
        $newTitle = 'Recordings Manager';
        $oldLink = '/app/recordings/recordings.php';
        $newLink = '/recordings-manager';

        try {
            DB::transaction(function () use ($oldTitle, $newTitle, $oldLink, $newLink) {
                $menuItemIds = MenuItem::query()
                    ->where('menu_item_title', $oldTitle)
                    ->where('menu_item_link', $oldLink)
                    ->pluck('menu_item_uuid');

                if ($menuItemIds->isEmpty()) {
                    echo "No Recordings menu items required updating.\n";
                    return;
                }

                $updatedMenuItems = MenuItem::query()
                    ->whereIn('menu_item_uuid', $menuItemIds)
                    ->update([
                        'menu_item_title' => $newTitle,
                        'menu_item_link' => $newLink,
                    ]);

                $updatedMenuLanguages = MenuLanguage::query()
                    ->whereIn('menu_item_uuid', $menuItemIds)
                    ->where('menu_item_title', $oldTitle)
                    ->update([
                        'menu_item_title' => $newTitle,
                    ]);

                echo "Updated {$updatedMenuItems} menu item(s) and {$updatedMenuLanguages} menu language record(s).\n";
            });

            return true;
        } catch (\Throwable $e) {
            echo "Error updating Recordings menu items: " . $e->getMessage() . "\n";
            return false;
        }
    }
}
