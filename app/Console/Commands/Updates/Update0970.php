<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class Update0970
{
    // FreeSWITCH scripts are now committed under resources/freeswitch_scripts
    // and deployed through the symlink created by Update183.
    // protected $file1 = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/switch/resources/scripts/app/voicemail/resources/functions/send_email.lua';
    protected $filePath1;
    protected $filePath2;


    public function __construct()
    {
        // $this->filePath1 = base_path('public/app/switch/resources/scripts/app/voicemail/resources/functions/send_email.lua');
        // $this->filePath2 = '/usr/share/freeswitch/scripts/app/voicemail/resources/functions/send_email.lua';
    }

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        // The current committed send_email.lua is deployed by Update183's symlink.

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
