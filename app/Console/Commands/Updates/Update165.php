<?php

namespace App\Console\Commands\Updates;

use App\Models\FusionCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class Update165
{

    // protected $file1 = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/call_flows/call_flow_edit.php';
    // protected $filePath1;
    // protected $filePath2;


    public function __construct()
    {
        // $this->filePath1 = base_path('public/app/call_flows/call_flow_edit.php');
    }

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        // if (!$this->downloadAndReplaceFile($this->file1, $this->filePath1, 'call_flow_edit.php')) {
        //     return false;
        // }

        if (!$this->updateFaxHangupDialplans()) {
            return false;
        }

        return true;
    }

    protected function updateFaxHangupDialplans(): bool
    {
        try {
            $oldValue = 'data="api_hangup_hook=lua app/fax/resources/scripts/hangup_rx.lua"';
            $newValue = 'data="api_hangup_hook=lua lua/fax_hangup.lua"';

            $dialplans = DB::table('v_dialplans')
                ->select('dialplan_uuid', 'dialplan_xml')
                ->where('dialplan_xml', 'like', '%app/fax/resources/scripts/hangup_rx.lua%')
                ->get();

            $updatedCount = 0;

            foreach ($dialplans as $dialplan) {
                $originalXml = $dialplan->dialplan_xml;
                $updatedXml = str_replace($oldValue, $newValue, $originalXml);

                if ($updatedXml !== $originalXml) {
                    DB::table('v_dialplans')
                        ->where('dialplan_uuid', $dialplan->dialplan_uuid)
                        ->update([
                            'dialplan_xml' => $updatedXml,
                            'update_date' => now(),
                        ]);

                    $updatedCount++;
                    echo "Updated dialplan {$dialplan->dialplan_uuid}\n";
                }
            }

            echo "Fax hangup dialplan update complete. Updated {$updatedCount} dialplan(s).\n";

            FusionCache::clear("dialplan.*");

            return true;
        } catch (\Exception $e) {
            echo "Error updating fax hangup dialplans: " . $e->getMessage() . "\n";
            return false;
        }
    }

    // public function getSupervisorProgramsToRestart(): array
    // {
    //     return [
    //         'fs-cdr-service',
    //     ];
    // }

    /**
     * Download a file from a URL and replace the local file.
     *
     * @return bool
     */
    protected function downloadAndReplaceFile($url, $filePath, $fileName)
    {
        try {
            $response = Http::get($url);

            if ($response->successful()) {
                File::put($filePath, $response->body());
                echo "$fileName file downloaded and replaced successfully.\n";
                return true;
            } else {
                echo "Error downloading $fileName. Status Code: " . $response->status() . "\n";
                return false;
            }
        } catch (\Exception $e) {
            echo "Error downloading $fileName: " . $e->getMessage() . "\n";
            return false;
        }
    }
}
