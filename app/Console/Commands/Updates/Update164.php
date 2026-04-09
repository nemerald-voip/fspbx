<?php

namespace App\Console\Commands\Updates;

use App\Models\FusionCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class Update164
{

    protected $file1 = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/call_flows/call_flow_edit.php';
    protected $filePath1;
    protected $filePath2;


    public function __construct()
    {
        $this->filePath1 = base_path('public/app/call_flows/call_flow_edit.php');
    }

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        if (!$this->downloadAndReplaceFile($this->file1, $this->filePath1, 'call_flow_edit.php')) {
            return false;
        }

        if (!$this->updateFlowToggleDialplans()) {
            return false;
        }

        return true;
    }

    protected function updateFlowToggleDialplans(): bool
    {
        try {
            $dialplans = DB::table('v_dialplans')
                ->select('dialplan_uuid', 'dialplan_xml')
                ->where('dialplan_xml', 'like', '%feature_code=true%')
                ->where('dialplan_xml', 'like', '%call_flow.lua%')
                ->get();

            $updatedCount = 0;

            foreach ($dialplans as $dialplan) {
                $originalXml = $dialplan->dialplan_xml;

                $updatedXml = preg_replace_callback(
                    '/<condition\b[^>]*>.*?<\/condition>/s',
                    function ($matches) {
                        $conditionXml = $matches[0];

                        // Only modify the feature-code condition
                        if (
                            str_contains($conditionXml, 'feature_code=true') &&
                            str_contains($conditionXml, 'data="call_flow.lua"')
                        ) {
                            return preg_replace(
                                '/data="call_flow\.lua"/',
                                'data="lua/flow_toggle.lua"',
                                $conditionXml,
                                1
                            );
                        }

                        return $conditionXml;
                    },
                    $originalXml
                );

                if ($updatedXml !== null && $updatedXml !== $originalXml) {
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

            echo "Dialplan XML update complete. Updated {$updatedCount} dialplan(s).\n";

            FusionCache::clear("dialplan.*");

            return true;
        } catch (\Exception $e) {
            echo "Error updating dialplans: " . $e->getMessage() . "\n";
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
