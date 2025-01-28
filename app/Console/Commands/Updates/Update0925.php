<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class Update0925
{
    protected $ivrConfFileUrl = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/switch/resources/scripts/app/xml_handler/resources/scripts/configuration/ivr.conf.lua';
    protected $ivrConfFilePath;
    protected $ivrConfFilePath2;


    public function __construct()
    {
        $this->ivrConfFilePath = base_path('public/app/switch/resources/scripts/app/xml_handler/resources/scripts/configuration/ivr.conf.lua');
        $this->ivrConfFilePath2 = base_path('/usr/share/freeswitch/scripts/app/xml_handler/resources/scripts/configuration/ivr.conf.lua');
    }

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        if (!$this->downloadAndReplaceFile($this->ivrConfFileUrl, $this->ivrConfFilePath, 'ivr.conf.lua')) {
            return false;
        }
        if (!$this->downloadAndReplaceFile($this->ivrConfFileUrl, $this->ivrConfFilePath2, 'ivr.conf.lua')) {
            return false;
        }

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
