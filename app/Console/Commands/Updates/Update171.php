<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

class Update171
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
                $this->updateSystemStatusMenuItem();
                $this->removeLegacyUserProfileMenuItem();
            });

            return true;
        } catch (\Throwable $e) {
            echo "Error applying update 171: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function updateSystemStatusMenuItem(): void
    {
        $updatedMenuItems = MenuItem::query()
            ->where('menu_item_title', 'System Status')
            ->where('menu_item_link', '/app/system/system.php')
            ->update([
                'menu_item_link' => '/system',
            ]);

        if ($updatedMenuItems === 0) {
            echo "No System Status menu items required updating.\n";
            return;
        }

        echo "Updated {$updatedMenuItems} System Status menu item(s).\n";
    }

    private function removeLegacyUserProfileMenuItem(): void
    {
        $deletedMenuItems = MenuItem::query()
            ->where('menu_item_link', '/core/users/user_edit.php?id=user')
            ->delete();

        if ($deletedMenuItems === 0) {
            echo "No legacy user profile menu items required removal.\n";
            return;
        }

        echo "Removed {$deletedMenuItems} legacy user profile menu item(s).\n";
    }
}
