<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class Update188
{
    private const VERSION = '1.8.8';
    private const SERVICE = 'php8.4-fpm.service';
    private const DROP_IN_DIR = '/etc/systemd/system/php8.4-fpm.service.d';
    private const DROP_IN_PATH = self::DROP_IN_DIR . '/override.conf';
    private const OVERRIDE = <<<'INI'
[Service]
RuntimeDirectory=php
RuntimeDirectoryMode=0755
ReadWritePaths=/etc/freeswitch /usr/share/freeswitch /var/lib/freeswitch
INI;

    public function apply(): bool
    {
        try {
            if (! $this->commandExists('systemctl')) {
                echo "systemctl not found; skipping php8.4-fpm systemd override.\n";
                return true;
            }

            if (! $this->serviceExists()) {
                echo self::SERVICE . " not found; skipping php8.4-fpm systemd override.\n";
                return true;
            }

            File::ensureDirectoryExists(self::DROP_IN_DIR);
            File::put(self::DROP_IN_PATH, self::OVERRIDE . "\n");
            echo "Wrote " . self::DROP_IN_PATH . ".\n";

            $this->runSystemctl(['daemon-reload'], 'Reloaded systemd manager configuration.');
            $this->runSystemctl(['restart', self::SERVICE], 'Restarted ' . self::SERVICE . '.');

            echo "Update " . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function serviceExists(): bool
    {
        $process = new Process(['systemctl', 'status', self::SERVICE]);
        $process->setTimeout(30);
        $process->run();

        return $process->getExitCode() !== 4;
    }

    private function runSystemctl(array $arguments, string $successMessage): void
    {
        $process = new Process(['systemctl', ...$arguments]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            $output = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new \RuntimeException($output ?: 'systemctl ' . implode(' ', $arguments) . ' failed.');
        }

        echo $successMessage . "\n";
    }

    private function commandExists(string $command): bool
    {
        $process = Process::fromShellCommandline('command -v ' . escapeshellarg($command));
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful();
    }
}
