<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

class Update174
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
                $this->updateConferenceCenterMenuItems();
            });

            return true;
        } catch (\Throwable $e) {
            echo "Error applying update 174: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function updateConferenceCenterMenuItems(): void
    {
        $updatedMenuItems = MenuItem::query()
            ->where('menu_item_title', 'Conference Centers')
            ->where('menu_item_link', '/app/conference_centers/conference_centers.php')
            ->update([
                'menu_item_link' => '/conference-centers',
            ]);

        if ($updatedMenuItems === 0) {
            echo "No Conference Centers menu items required updating.\n";
            return;
        }

        echo "Updated {$updatedMenuItems} Conference Centers menu item(s).\n";
    }
}
