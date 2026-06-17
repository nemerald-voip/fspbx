<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class Update183
{
    private const VERSION = '1.8.3';

    private const SOURCE_DIR = '/var/www/fspbx/resources/freeswitch_scripts';
    private const TARGET_LINK = '/usr/share/freeswitch/scripts';

    public function apply(): bool
    {
        try {
            /*
             * Existing installs may have /usr/share/freeswitch/scripts
             * as a real directory from older installs. Remove it and recreate
             * it as a symlink to the FS PBX-managed scripts directory.
             */
            if (is_link(self::TARGET_LINK)) {
                unlink(self::TARGET_LINK);
            } elseif (File::isDirectory(self::TARGET_LINK)) {
                File::deleteDirectory(self::TARGET_LINK);
            } elseif (file_exists(self::TARGET_LINK)) {
                unlink(self::TARGET_LINK);
            }

            File::ensureDirectoryExists(self::SOURCE_DIR);

            if (! symlink(self::SOURCE_DIR, self::TARGET_LINK)) {
                echo "Error creating symlink from " . self::TARGET_LINK . " to " . self::SOURCE_DIR . ".\n";
                return false;
            }

            /*
             * Match the install script behavior exactly.
             * This avoids PHP symlink recursion differences.
             */
            $this->runCommand(['chown', '-R', 'www-data:www-data', self::SOURCE_DIR]);
            $this->runCommand(['chown', '-R', 'www-data:www-data', self::TARGET_LINK]);

            echo "Update " . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function runCommand(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(
                'Command failed: ' . implode(' ', $command) . "\n" .
                trim($process->getErrorOutput() ?: $process->getOutput())
            );
        }
    }
}