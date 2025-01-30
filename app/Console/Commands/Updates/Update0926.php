<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class Update0926
{
    protected $file1 = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/fax_queue/resources/job/fax_send.php';
    protected $file2 = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/switch/resources/scripts/app/fax/resources/scripts/hangup_tx.lua';
    protected $filePath1;
    protected $filePath2;
    protected $filePath3;


    public function __construct()
    {
        $this->filePath3 = base_path('public/app/app/fax_queue/resources/job/fax_send.php');
        $this->filePath1 = base_path('public/app/switch/resources/scripts/app/switch/resources/scripts/app/fax/resources/scripts/hangup_tx.lua');
        $this->filePath2 = '/usr/share/freeswitch/scripts/app/fax/resources/scripts/hangup_tx.lua';
    }

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        if (!$this->downloadAndReplaceFile($this->file1, $this->filePath3, 'fax_send.php')) {
            return false;
        }
        if (!$this->downloadAndReplaceFile($this->file2, $this->filePath1, 'hangup_tx.lua')) {
            return false;
        }
        if (!$this->downloadAndReplaceFile($this->file2, $this->filePath2, 'hangup_tx.lua')) {
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
