<?php

namespace App\Console\Commands\Updates;

use App\Models\Dialplans;
use App\Models\DialplanDetails;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use App\Models\FusionCache;

class Update121
{

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {

        // --- Update voicemail regex in existing vmain dialplan(s) ---

        $oldExpr = '^(vmain$|^\*4000$|^\*98|voicemail\+)(\d{2,12})$';
        $newExpr = '^(?:\*98|voicemail\+|vm)(\d{2,12})$';

        // Only grab dialplans that actually contain the old pattern (safe)
        $dialplans = Dialplans::query()
            ->where('dialplan_name', 'vmain')   // or any other safe filter you want
            ->get(['dialplan_uuid', 'dialplan_name', 'dialplan_xml'])
            ->filter(fn($dp) => str_contains((string) $dp->dialplan_xml, $oldExpr));

        foreach ($dialplans as $dp) {
            $xml = (string) $dp->dialplan_xml;
            $newXml = $xml;

            // 1) Replace old regex with new
            $newXml = str_replace($oldExpr, $newExpr, $newXml);

            // 2) New regex has only ONE capture group, so voicemail_id must switch $2 -> $1
            $newXml = str_replace('voicemail_id=$2', 'voicemail_id=$1', $newXml);

            if ($newXml !== $xml) {
                $dp->dialplan_xml = $newXml;
                $dp->save();
            }
        }

        // Update dialplan_details that store the old regex
        DialplanDetails::query()
            ->where('dialplan_detail_tag', 'condition')
            ->where('dialplan_detail_type', 'destination_number')
            ->where('dialplan_detail_data', $oldExpr)
            ->update(['dialplan_detail_data' => $newExpr]);

        // Update action that references the old $2 capture
        DialplanDetails::query()
            ->where('dialplan_detail_tag', 'action')
            ->where('dialplan_detail_type', 'set')
            ->where('dialplan_detail_data', 'voicemail_id=$2')
            ->update(['dialplan_detail_data' => 'voicemail_id=$1']);

        // Clear FusionPBX cache so dialplan reload sees updates
        FusionCache::clear("dialplan.*");

        echo "Voicemail dialplan is updated successfully.\n";

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
