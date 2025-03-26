<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class Update0940
{
    protected $fileUrl = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/vars/app_defaults.php';
    protected $filePath;


    public function __construct()
    {
        $this->filePath = base_path('public/app/vars/app_defaults.php');
    }

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        if (!$this->downloadAndReplaceFile($this->fileUrl, $this->filePath, 'app_defaults.php')) {
            return false;
        }

        // Enable and restart required systemd services
        $this->enableAndRestartServices(['email_queue', 'fax_queue', 'event_guard']);

        // Run the upgrade script
        $this->executeUpgradeScript();

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
     * Check if systemd services are enabled and enable/restart them if necessary.
     *
     * @param array $services
     */
    protected function enableAndRestartServices(array $services)
    {
        foreach ($services as $service) {
            $process = new Process(['systemctl', 'is-enabled', $service]);
            $process->run();

            if (trim($process->getOutput()) !== 'enabled') {
                // Enable the service if it's not enabled
                $enableProcess = new Process(['systemctl', 'enable', '--now', $service]);
                $enableProcess->run();

                if ($enableProcess->isSuccessful()) {
                    echo "✅ Service '$service' enabled and started successfully.\n";
                } else {
                    echo "⚠️ Failed to enable service '$service'.\n";
                }
            } else {
                // Restart the service if it's already enabled
                $restartProcess = new Process(['systemctl', 'restart', $service]);
                $restartProcess->run();

                if ($restartProcess->isSuccessful()) {
                    echo "🔄 Service '$service' restarted successfully.\n";
                } else {
                    echo "⚠️ Failed to restart service '$service'.\n";
                }
            }
        }
    }

    /**
     * Execute the FreePBX upgrade script.
     */
    protected function executeUpgradeScript()
    {
        echo "🚀 Running FS PBX upgrade script...\n";
        $output = shell_exec("cd /var/www/fspbx && /usr/bin/php /var/www/fspbx/public/core/upgrade/upgrade.php > /dev/null 2>&1");

        if ($output === null) {
            echo "✅ FS PBX upgrade script executed successfully.\n";
        } else {
            echo "⚠️ FS PBX upgrade script may have encountered an issue. Check logs for details.\n";
        }
    }
}
