<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Process;
use Throwable;

class Update210
{
    private bool $phpUpdated = false;

    public function apply(): bool
    {
        $this->phpUpdated = false;

        try {
            Process::timeout(1800)->run([
                'bash', base_path('install/install_php.sh'), '--upgrade-only',
            ], function (string $type, string $output) {
                echo $output;
            })->throw();

            $this->phpUpdated = true;
        } catch (Throwable $exception) {
            echo 'WARNING: PHP security update was not verified: '.$exception->getMessage()."\n";
            echo "Continuing the application update. PHP may still be vulnerable.\n";
            echo 'Resolve the error above, then retry: sudo bash '.base_path('install/install_php.sh')." --upgrade-only\n";
        }

        // Host package failures must not prevent the application update.
        return true;
    }

    public function getSupervisorProgramsToRestart(): array
    {
        // Horizon is already recycled by UpdateApp. Keep the opt-in call webhook
        // listener out of unconditional restarts.
        return $this->phpUpdated
            ? ['fs-cdr-service', 'fs-esl-listener-emergency', 'reverb']
            : [];
    }
}
