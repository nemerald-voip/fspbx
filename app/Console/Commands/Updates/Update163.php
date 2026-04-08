<?php

namespace App\Console\Commands\Updates;

use App\Models\Dialplans;
use Illuminate\Support\Str;
use App\Models\DialplanDetails;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use App\Models\FusionCache;

class Update163
{
    // protected $file1 = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/fax_queue/resources/job/fax_send.php';
    // protected $filePath1;

    public function __construct()
    {
        // $this->filePath1 = base_path('public/app/fax_queue/resources/job/fax_send.php');
    }

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        // if (!$this->downloadAndReplaceFile($this->file1, $this->filePath1, 'fax_send.php')) {
        //     return false;
        // }

        // New Call Flow toggle dialplan
        $dialplanUuid = '1e2b2c79-7e2e-4a4f-92f7-0e5bb86e2fd7';
        $appUuid      = 'a9f6c8d3-5b41-4b2c-8c0a-3fd9e7a41b6e'; 

        // Avoid duplicate insert if this update runs more than once
        if (! Dialplans::where('dialplan_uuid', $dialplanUuid)->exists()) {

            $dialplanXml = <<<XML
<extension name="toggle-call-flow" continue="false" uuid="{$dialplanUuid}">
    <condition field="destination_number" expression="^flow(\\d+)$">
        <action application="lua" data="lua/flow_toggle.lua"/>
    </condition>
</extension>
XML;

            Dialplans::create([
                'dialplan_uuid'        => $dialplanUuid,
                'app_uuid'             => $appUuid,
                'domain_uuid'          => null,
                'hostname'             => null,
                'dialplan_context'     => 'global',
                'dialplan_name'        => 'toggle-call-flow',
                'dialplan_number'      => 'flow[ext]',
                'dialplan_destination' => null,
                'dialplan_continue'    => 'false',
                'dialplan_xml'         => $dialplanXml,
                'dialplan_order'       => 508,
                'dialplan_enabled'     => 'true',
                'dialplan_description' => null,
            ]);
        }

        // ---- Add dialplan_details rows (condition + action) ----

        // condition: destination_number ^flow(\d+)$
        DialplanDetails::create([
            'dialplan_detail_uuid'    => (string) Str::uuid(),
            'domain_uuid'             => null,                 
            'dialplan_uuid'           => $dialplanUuid,
            'dialplan_detail_tag'     => 'condition',
            'dialplan_detail_type'    => 'destination_number',
            'dialplan_detail_data'    => '^flow(\d+)$',
            'dialplan_detail_break'   => null,
            'dialplan_detail_inline'  => null,
            'dialplan_detail_group'   => 0,
            'dialplan_detail_order'   => 5,
            'dialplan_detail_enabled' => 'true',
        ]);

        // action: lua flow_toggle.lua
        DialplanDetails::create([
            'dialplan_detail_uuid'    => (string) Str::uuid(),
            'domain_uuid'             => null,
            'dialplan_uuid'           => $dialplanUuid,
            'dialplan_detail_tag'     => 'action',
            'dialplan_detail_type'    => 'lua',
            'dialplan_detail_data'    => 'lua/flow_toggle.lua',
            'dialplan_detail_break'   => null,
            'dialplan_detail_inline'  => null,
            'dialplan_detail_group'   => 0,
            'dialplan_detail_order'   => 10,
            'dialplan_detail_enabled' => 'true',
        ]);

        //clear fusionpbx cache
        FusionCache::clear("dialplan.*");

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
