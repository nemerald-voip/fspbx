<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class Update097
{
    protected $xmlCdrFileUrl = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/xml_cdr/resources/classes/xml_cdr.php';
    protected $xmlCdrFilePath;

    protected $functionsFileUrl = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/resources/functions.php';
    protected $functionsFilePath;


    public function __construct()
    {
        $this->xmlCdrFilePath = base_path('public/app/xml_cdr/resources/classes/xml_cdr.php');
        $this->functionsFilePath = base_path('public/resources/functions.php');
    }

    /**
     * Apply the 0.9.7 update steps.
     *
     * @return bool
     */
    public function apply()
    {
        // Download and replace xml_cdr.php file
        if (!$this->downloadAndReplaceFile($this->xmlCdrFileUrl, $this->xmlCdrFilePath, 'xml_cdr.php')) {
            return false;
        }

        // Download and replace functions.php file
        if (!$this->downloadAndReplaceFile($this->functionsFileUrl, $this->functionsFilePath, 'functions.php')) {
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
