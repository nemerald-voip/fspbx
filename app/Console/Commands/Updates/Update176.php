<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

class Update176
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
                $this->updateBridgeMenuItems();
            });

            return true;
        } catch (\Throwable $e) {
            echo "Error applying update 176: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function updateBridgeMenuItems(): void
    {
        $this->updateMenuItemLink(
            'Bridges',
            '/app/bridges/bridges.php',
            '/bridges'
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
