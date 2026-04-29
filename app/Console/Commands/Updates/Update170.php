<?php

namespace App\Console\Commands\Updates;

use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\FusionCache;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

class Update170
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
                $this->updateMenuItems();
                $this->updateManualRecordingDialplans();
            });

            FusionCache::clear('dialplan.*');

            return true;
        } catch (\Throwable $e) {
            echo "Error applying update 170: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function updateMenuItems(): void
    {
        $menuItems = [
            [
                'title' => 'Gateways',
                'old_link' => '/app/gateways/gateways.php',
                'new_link' => '/gateways',
            ],
            [
                'title' => 'Access Control',
                'old_link' => '/app/access_controls/access_controls.php',
                'new_link' => '/access-controls',
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

    private function updateManualRecordingDialplans(): void
    {
        $oldScript = 'recordings_custom.lua';
        $newScript = 'lua/manual_recordings.lua';

        $dialplans = Dialplans::query()
            ->where('dialplan_xml', 'like', "%{$oldScript}%")
            ->get(['dialplan_uuid', 'dialplan_name', 'dialplan_xml']);

        $updatedDialplans = 0;

        foreach ($dialplans as $dialplan) {
            $xml = (string) $dialplan->dialplan_xml;
            $newXml = str_replace($oldScript, $newScript, $xml);

            if ($newXml === $xml) {
                continue;
            }

            $dialplan->dialplan_xml = $newXml;
            $dialplan->save();
            $updatedDialplans++;
        }

        $updatedDetails = DialplanDetails::query()
            ->where('dialplan_detail_tag', 'action')
            ->where('dialplan_detail_type', 'lua')
            ->where('dialplan_detail_data', $oldScript)
            ->update(['dialplan_detail_data' => $newScript]);

        echo "Updated {$updatedDialplans} manual recording dialplan XML record(s).\n";
        echo "Updated {$updatedDetails} manual recording dialplan detail record(s).\n";
    }
}
