<?php

namespace App\Console\Commands\Updates;

use App\Models\Dialplans;
use App\Models\FusionCache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;

class Update0966
{
    protected $file1 = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/switch/resources/scripts/app/ring_groups/index.lua';
    protected $file2 = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/dialplans/resources/switch/conf/dialplan/511_hotel-room-status-update.xml';
    protected $filePath1;
    protected $filePath2;
    protected $filePath3;
    protected string $dpAppUuid = '9f1cb908-0345-4587-88fb-0b7f384d8587';


    public function __construct()
    {
        $this->filePath1 = base_path('public/app/switch/resources/scripts/app/ring_groups/index.lua');
        $this->filePath2 = '/usr/share/freeswitch/scripts/app/ring_groups/index.lua';
        $this->filePath3 = 'public/app/dialplans/resources/switch/conf/dialplan/511_hotel-room-status-update.xml';
    }

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        if (!$this->downloadAndReplaceFile($this->file1, $this->filePath1, 'index.lua')) {
            return false;
        }
        if (!$this->downloadAndReplaceFile($this->file1, $this->filePath2, 'index.lua')) {
            return false;
        }
        if (!$this->downloadAndReplaceFile($this->file2, $this->filePath3, '511_hotel-room-status-update.xml')) {
            return false;
        }

        if (!$this->upsertHotelRoomStatusDialplan()) return false;

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

    protected function upsertHotelRoomStatusDialplan(): bool
    {
        // Check by app_uuid + context to avoid dupes
        $exists = Dialplans::query()
            ->where('app_uuid', $this->dpAppUuid)
            ->where('dialplan_context', 'global')
            ->exists();

        if ($exists) {
            echo "Dialplan already exists. Skipping insert.\n";
            return true;
        }

        $dpUuid = (string) Str::uuid();

        // Minimal XML; captures 1–2 digits after *26 into $1
        $xml = <<<XML
<extension name="hotel-room-status-update" continue="false" uuid="{$dpUuid}">
    <condition field="destination_number" expression="^\\*26(\\d{1,2})$">
        <action application="answer" data=""/>
        <action application="set" data="room_status=$1"/>
        <action application="lua" data="lua/hotel_room_status_update.lua"/>
    </condition>
</extension>
XML;

        Dialplans::create([
            'dialplan_uuid'        => $dpUuid,                        // must match XML uuid
            'app_uuid'             => $this->dpAppUuid,
            'dialplan_name'        => 'hotel-room-status-update',
            'dialplan_number'      => '*26[0-9]{1,2}',                // label for UI
            'dialplan_context'     => 'global',
            'dialplan_continue'    => 'false',
            'dialplan_xml'         => $xml,
            'dialplan_order'       => 511,
            'dialplan_enabled'     => true,
            'dialplan_description' => 'Set room_status from *26 + 1–2 digits, then run Lua',
            // domain_uuid left null for a global dialplan; your model constructor is CLI-safe
        ]);

        echo "Dialplan created: hotel-room-status-update ({$dpUuid}).\n";

        //clear fusionpbx cache
        FusionCache::clear("dialplan.*");

        return true;
    }
}
