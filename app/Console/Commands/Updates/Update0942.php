<?php

namespace App\Console\Commands\Updates;

use App\Models\Dialplans;
use App\Models\FusionCache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class Update0942
{
    protected $fileUrl = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/dialplans/resources/switch/conf/dialplan/440_wake-up.xml';
    protected $filePath;

    protected $fileUrl2 = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/dialplans/resources/switch/conf/dialplan/441_remote-wake-up.xml';
    protected $filePath2;

    public function __construct()
    {
        $this->filePath = base_path('public/app/dialplans/resources/switch/conf/dialplan/440_wake-up.xml');
        $this->filePath2 = base_path('public/app/dialplans/resources/switch/conf/dialplan/441_remote-wake-up.xml');
    }

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        if (!$this->downloadAndReplaceFile($this->fileUrl, $this->filePath, '440_wake-up.xml')) {
            return false;
        }

        if (!$this->downloadAndReplaceFile($this->fileUrl2, $this->filePath2, '441_remote-wake-up.xml')) {
            return false;
        }

        // Remove Dialplans records where dialplan_number equals "*925" and their associated dialplan_details
        $this->removeDialplans();

        $this->runUpgradeDefaults();

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

    /**
     * Remove all Dialplans records with dialplan_number "*925"
     * along with their associated dialplan_details.
     */
    protected function removeDialplans()
    {
        $dialplans = Dialplans::where('dialplan_number', '*925')->get();

        if ($dialplans->isEmpty()) {
            echo "ℹ️ No dialplan records with dialplan_number '*925' found.\n";
        } else {
            foreach ($dialplans as $dialplan) {
                // Delete associated dialplan_details
                $dialplan->dialplan_details()->delete();

                // Delete the dialplan record
                $dialplan->delete();
            }
        }

        //clear fusionpbx cache
        FusionCache::clear("dialplan.*");
    }

    private function runUpgradeDefaults()
    {
        echo "Running upgrade defaults script...";
        shell_exec("cd /var/www/fspbx && /usr/bin/php /var/www/fspbx/public/core/upgrade/upgrade.php > /dev/null 2>&1");
        echo "Upgrade defaults executed successfully.";
    }
}
