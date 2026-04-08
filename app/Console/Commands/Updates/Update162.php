<?php

namespace App\Console\Commands\Updates;

use App\Models\ProvisioningTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class Update162
{

    // protected $file1 = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/switch/resources/scripts/app/voicemail/index.lua';
    // protected $filePath1;
    // protected $filePath2;


    public function __construct()
    {
        // $this->filePath1 = base_path('public/app/switch/resources/scripts/app/voicemail/index.lua');
        // $this->filePath2 = '/usr/share/freeswitch/scripts/app/voicemail/index.lua';
    }

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        // if (!$this->downloadAndReplaceFile($this->file1, $this->filePath1, 'index.lua')) {
        //     return false;
        // }
        // if (!$this->downloadAndReplaceFile($this->file1, $this->filePath2, 'index.lua')) {
        //     return false;
        // }

        if (!$this->renameGrandstreamTemplates()) {
            return false;
        }


        return true;
    }

    // public function getSupervisorProgramsToRestart(): array
    // {
    //     return [
    //         'fs-cdr-service',
    //     ];
    // }

    /**
     * Rename incorrect default Grandstream template names from GPX* to GXP*.
     *
     * @return bool
     */
    protected function renameGrandstreamTemplates()
    {
        $map = [
            'GPX16xx'  => 'GXP16xx',
            'GPX21xx'  => 'GXP21xx',
            'GPX260x'  => 'GXP260x',
            'GPX261x'  => 'GXP261x',
        ];

        try {
            DB::transaction(function () use ($map) {
                foreach ($map as $oldName => $newName) {
                    $updated = ProvisioningTemplate::query()
                        ->where('vendor', 'grandstream')
                        ->where('type', 'default')
                        ->where('name', $oldName)
                        ->update([
                            'name' => $newName,
                        ]);

                    if ($updated > 0) {
                        echo "Renamed default template {$oldName} to {$newName}.\n";
                    }
                }

                // Optional but recommended:
                // keep custom templates in sync if they were cloned from the old default names
                foreach ($map as $oldName => $newName) {
                    $updated = ProvisioningTemplate::query()
                        ->where('vendor', 'grandstream')
                        ->where('type', 'custom')
                        ->where('base_template', $oldName)
                        ->update([
                            'base_template' => $newName,
                        ]);

                    if ($updated > 0) {
                        echo "Updated custom templates base_template from {$oldName} to {$newName}.\n";
                    }
                }
            });

            return true;
        } catch (\Exception $e) {
            echo "Error renaming Grandstream templates: " . $e->getMessage() . "\n";
            return false;
        }
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
