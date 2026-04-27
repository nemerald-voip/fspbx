<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

class Update169
{
    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        $title = 'Call Flows';
        $oldLink = '/app/call_flows/call_flows.php';
        $newLink = '/call-flows';

        try {
            DB::transaction(function () use ($title, $oldLink, $newLink) {
                $updatedMenuItems = MenuItem::query()
                    ->where('menu_item_title', $title)
                    ->where('menu_item_link', $oldLink)
                    ->update([
                        'menu_item_link' => $newLink,
                    ]);

                if ($updatedMenuItems === 0) {
                    echo "No Call Flows menu items required updating.\n";
                    return;
                }

                echo "Updated {$updatedMenuItems} Call Flows menu item(s).\n";
            });

            return true;
        } catch (\Throwable $e) {
            echo "Error updating Call Flows menu items: " . $e->getMessage() . "\n";
            return false;
        }
    }
}
