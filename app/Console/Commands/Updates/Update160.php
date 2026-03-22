<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class Update160
{

    protected $file1 = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/switch/resources/scripts/intercept.lua';
    protected $filePath1;
    protected $filePath2;

    protected $file2 = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/switch/resources/scripts/app/ring_groups/index.lua';
    protected $filePath3;
    protected $filePath4;

    public function __construct()
    {
        $this->filePath1 = base_path('public/app/switch/resources/scripts/intercept.lua');
        $this->filePath2 = '/usr/share/freeswitch/scripts/app/switch/resources/scripts/intercept.lua';

        $this->filePath3 = base_path('public/app/switch/resources/scripts/app/ring_groups/index.lua');
        $this->filePath4 = '/usr/share/freeswitch/scripts/app/switch/resources/scripts/app/ring_groups/index.lua';
    }

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        if (!$this->downloadAndReplaceFile($this->file1, $this->filePath1, 'intercept.lua')) {
            return false;
        }
        if (!$this->downloadAndReplaceFile($this->file1, $this->filePath2, 'intercept.lua')) {
            return false;
        }

        if (!$this->downloadAndReplaceFile($this->file2, $this->filePath3, 'index.lua')) {
            return false;
        }
        if (!$this->downloadAndReplaceFile($this->file2, $this->filePath4, 'index.lua')) {
            return false;
        }



        return true;
    }

    public function getSupervisorProgramsToRestart(): array
    {
        return [
            'fs-cdr-service',
            'fs-esl-listener-emergency',
        ];
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
