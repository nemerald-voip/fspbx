<?php

namespace App\Console\Commands\Updates;

use App\Models\Dialplans;
use App\Models\DialplanDetails;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use App\Models\FusionCache;

class Update166
{

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {

        // --- Update voicemail regex in existing vmain dialplan(s) ---
       $dialplans = Dialplans::query()
        ->where('dialplan_name', 'vmain')
        ->where('dialplan_xml', 'like', '%voicemail_id=$2%')
        ->get(['dialplan_uuid', 'dialplan_name', 'dialplan_xml']);

        foreach ($dialplans as $dp) {
            $xml = (string) $dp->dialplan_xml;
            $newXml = str_replace('voicemail_id=$2', 'voicemail_id=$1', $xml);

            if ($newXml !== $xml) {
                $dp->dialplan_xml = $newXml;
                $dp->save();
            }
        }

        // Reapply action that references the old $2 capture
        DialplanDetails::query()
            ->where('dialplan_detail_tag', 'action')
            ->where('dialplan_detail_type', 'set')
            ->where('dialplan_detail_data', 'voicemail_id=$2')
            ->update(['dialplan_detail_data' => 'voicemail_id=$1']);

        // Clear FusionPBX cache
        FusionCache::clear("dialplan.*");

        echo "Voicemail capture group fix reapplied successfully.\n";

        return true;
    }

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
