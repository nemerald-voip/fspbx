<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class Update0924
{
    protected $xmlCdrFileUrl = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/xml_cdr/resources/classes/xml_cdr.php';
    protected $xmlCdrFilePath;

    protected $dialplanFileUrl = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/dialplans/resources/classes/dialplan.php';
    protected $dialplanFilePath;

    protected $faxSendFileUrl = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/fax_queue/resources/job/fax_send.php';
    protected $faxSendFilePath;


    public function __construct()
    {
        $this->dialplanFilePath = base_path('public/app/dialplans/resources/classes/dialplan.php');
        $this->xmlCdrFilePath = base_path('public/app/xml_cdr/resources/classes/xml_cdr.php');
        $this->functionsFilePath = base_path('public/app/fax_queue/resources/job/fax_send.php');
    }

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        if (!$this->downloadAndReplaceFile($this->dialplanFileUrl, $this->dialplanFilePath, 'dialplan.php')) {
            return false;
        }

        if (!$this->downloadAndReplaceFile($this->xmlCdrFileUrl, $this->xmlCdrFilePath, 'xml_cdr.php')) {
            return false;
        }

        if (!$this->downloadAndReplaceFile($this->faxSendFileUrl, $this->faxSendFilePath, 'fax_send.php')) {
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
