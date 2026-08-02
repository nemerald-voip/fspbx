<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class Update111
{
    // FreeSWITCH scripts are now committed under resources/freeswitch_scripts
    // and deployed through the symlink created by Update183.
    // protected $fileUrl = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/switch/resources/scripts/app/ring_groups/index.lua';
    protected $filePath;


    public function __construct()
    {
        // $this->filePath = base_path('public/app/switch/resources/scripts/app/ring_groups/index.lua');
    }

    /**
     *
     * @return bool
     */
    public function apply()
    {
        // The current committed ring group script is deployed by Update183's symlink.

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
