<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

class Update172
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
                $this->updateDialplanMenuItems();
            });

            return true;
        } catch (\Throwable $e) {
            echo "Error applying update 172: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function updateDialplanMenuItems(): void
    {
        $menuItems = [
            [
                'title' => 'Dialplan Manager',
                'old_link' => '/app/dialplans/dialplans.php',
                'new_link' => '/dialplans',
            ],
            [
                'title' => 'Inbound Routes',
                'old_link' => '/app/dialplans/dialplans.php?app_uuid=c03b422e-13a8-bd1b-e42b-b6b9b4d27ce4',
                'new_link' => '/dialplans?category=inbound',
            ],
            [
                'title' => 'Outbound Routes',
                'old_link' => '/app/dialplans/dialplans.php?app_uuid=8c914ec3-9fc0-8ab5-4cda-6c9288bdc9a3',
                'new_link' => '/dialplans?category=outbound',
            ],
        ];

        foreach ($menuItems as $menuItem) {
            $updatedMenuItems = MenuItem::query()
                ->where('menu_item_title', $menuItem['title'])
                ->where('menu_item_link', $menuItem['old_link'])
                ->update([
                    'menu_item_link' => $menuItem['new_link'],
                ]);

            if ($updatedMenuItems === 0) {
                echo "No {$menuItem['title']} menu items required updating.\n";
                continue;
            }

            echo "Updated {$updatedMenuItems} {$menuItem['title']} menu item(s).\n";
        }
    }
}
