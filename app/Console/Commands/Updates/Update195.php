<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class Update195
{
    private const VERSION = '1.9.5';
    private const PROGRAM = 'fs-esl-listener-call-webhooks';

    public function apply(): bool
    {
        try {
            $this->installSupervisorConfiguration();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function installSupervisorConfiguration(): void
    {
        $source = base_path('install/' . self::PROGRAM . '.conf');
        $destination = '/etc/supervisor/conf.d/' . self::PROGRAM . '.conf';

        if (! File::exists($source)) {
            echo "WARNING: Supervisor source file not found at {$source}; listener was not installed.\n";
            return;
        }

        try {
            if (! File::copy($source, $destination)) {
                echo "WARNING: Unable to copy the Supervisor configuration to {$destination}.\n";
                return;
            }
            echo "Installed call webhook Supervisor configuration at {$destination}; the listener is disabled by default.\n";
        } catch (Throwable $exception) {
            echo "WARNING: Unable to install {$destination}: {$exception->getMessage()}\n";
            return;
        }

        foreach ([['supervisorctl', 'reread'], ['supervisorctl', 'update']] as $command) {
            $process = new Process($command);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()) {
                echo 'WARNING: ' . implode(' ', $command) . ' failed: ' . trim($process->getErrorOutput()) . "\n";
            }
        }
    }
}
