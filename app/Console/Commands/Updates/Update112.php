<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class Update112
{
    protected $fileUrl = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/switch/resources/scripts/app/ring_groups/index.lua';
    protected $filePath;


    public function __construct()
    {
        $this->filePath = base_path('/usr/share/freeswitch/scripts/app/ring_groups/index.lua');
    }

    /**
     *
     * @return bool
     */
    public function apply()
    {
        if (!$this->downloadAndReplaceFile($this->fileUrl, $this->filePath, 'index.lua')) {
            return false;
        }

        // Restart Horizon queue workers (graceful first, then Supervisor fallback)
        echo "Restarting Horizon queue workers...\n";
        $this->restartHorizon();

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
     * Run a shell command and throw on failure (unless $ignoreFailure = true).
     */
    protected function run(array $cmd, bool $ignoreFailure = false): void
    {
        $proc = new Process($cmd);
        $proc->setTimeout(60);
        $proc->run();

        if (!$proc->isSuccessful() && !$ignoreFailure) {
            throw new \RuntimeException(sprintf(
                "Command failed: %s\nExit: %s\nStdErr: %s",
                implode(' ', $cmd),
                $proc->getExitCode(),
                trim($proc->getErrorOutput())
            ));
        }
    }

    /**
     * Gracefully restart Horizon workers. If supervised, `horizon:terminate` will
     * trigger a respawn. Also try common Supervisor names as a fallback.
     * Non-fatal if Horizon/Supervisor aren't present.
     */
    protected function restartHorizon(): void
    {
        // 1) Graceful terminate (lets running jobs finish)
        $artisan = base_path('artisan');
        if (file_exists($artisan)) {
            echo "[Update110] php artisan horizon:terminate\n";
            $this->run(['php', $artisan, 'horizon:terminate'], true);
        } else {
            echo "[Update110] WARNING: artisan not found; skipping graceful terminate.\n";
        }

        // 2) Fallback: try restarting common Supervisor program names
        // Your system shows `horizon:horizon_00` in `supervisorctl status`,
        // so include that plus common names.
        $candidates = [
            'horizon:*',
            'horizon',
            'laravel-horizon',
            'horizon:horizon_00',
        ];

        foreach ($candidates as $name) {
            echo "[Update110] supervisorctl restart {$name}\n";
            $this->run(['supervisorctl', 'restart', $name], true); // ignore failure if not defined
        }
    }
}
