<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\File;

class Update172
{
    protected string $deviceLogsPath;
    protected string $provisionIndexPath;

    public function __construct()
    {
        $this->deviceLogsPath = base_path('public/app/device_logs/resources/device_logs.php');
        $this->provisionIndexPath = base_path('public/app/provision/index.php');
    }

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        try {
            $this->patchDeviceLogsPermissions();
            $this->guardProvisionDeviceLogInclude();

            return true;
        } catch (\Throwable $e) {
            echo "Error patching device log provisioning: " . $e->getMessage() . "\n";
            return false;
        }
    }

    protected function patchDeviceLogsPermissions(): void
    {
        if (!File::exists($this->deviceLogsPath)) {
            echo "Device logs app not installed; skipping permissions patch.\n";
            return;
        }

        $contents = File::get($this->deviceLogsPath);
        $patched = preg_replace('/permissions\s*::\s*new\s*\(\s*\)/', 'new permissions', $contents, -1, $count);

        if ($count === 0) {
            echo "No legacy device logs permissions instantiation found.\n";
            return;
        }

        File::put($this->deviceLogsPath, $patched);
        echo "Patched device logs permissions instantiation.\n";
    }

    protected function guardProvisionDeviceLogInclude(): void
    {
        if (!File::exists($this->provisionIndexPath)) {
            echo "Provision index not found; skipping device log include guard.\n";
            return;
        }

        $contents = File::get($this->provisionIndexPath);

        if (str_contains($contents, 'Device log write failed during provisioning')) {
            echo "Provision device log include guard already applied.\n";
            return;
        }

        $new = <<<'PHP'
//device logs
	if (file_exists($_SERVER["PROJECT_ROOT"]."/app/device_logs/app_config.php")){
		try {
			require_once "app/device_logs/resources/device_logs.php";
		}
		catch (\Throwable $e) {
			error_log('Device log write failed during provisioning: '.$e->getMessage());
		}
	}
PHP;

        $pattern = <<<'REGEX'
#//device logs\s*if\s*\(\s*file_exists\(\s*\$_SERVER\["PROJECT_ROOT"\]\s*\.\s*"/app/device_logs/app_config\.php"\s*\)\s*\)\s*\{\s*require_once\s+"app/device_logs/resources/device_logs\.php"\s*;\s*\}#s
REGEX;

        $patched = preg_replace_callback($pattern, fn () => $new, $contents, -1, $count);

        if ($count === 0) {
            echo "Provision device log include block not found; skipping guard.\n";
            return;
        }

        File::put($this->provisionIndexPath, $patched);
        echo "Guarded provision device log include.\n";
    }
}
