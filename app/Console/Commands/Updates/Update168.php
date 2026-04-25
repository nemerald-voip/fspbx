<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

class Update168
{
    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        $title = 'SIP Status';
        $oldLink = '/app/sip_status/sip_status.php';
        $newLink = '/sip-status';

        try {
            DB::transaction(function () use ($title, $oldLink, $newLink) {
                $updatedMenuItems = MenuItem::query()
                    ->where('menu_item_title', $title)
                    ->where('menu_item_link', $oldLink)
                    ->update([
                        'menu_item_link' => $newLink,
                    ]);

                if ($updatedMenuItems === 0) {
                    echo "No SIP Status menu items required updating.\n";
                    return;
                }

                echo "Updated {$updatedMenuItems} SIP Status menu item(s).\n";
            });

            return true;
        } catch (\Throwable $e) {
            echo "Error updating SIP Status menu items: " . $e->getMessage() . "\n";
            return false;
        }
    }
}
