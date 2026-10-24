<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Process;
use Throwable;

class Update206
{
    public function apply(): bool
    {
        try {
            // Defer photo dependencies on unsupported operating systems so the
            // application update can finish before the OS is upgraded.
            Process::timeout(600)->run([
                'bash', base_path('install/install_message_photos.sh'), '--skip-unsupported',
            ], function (string $type, string $output) {
                echo $output;
            })->throw();

            return true;
        } catch (Throwable $exception) {
            echo 'Photo processing setup failed: '.$exception->getMessage()."\n";
            return false;
        }
    }
}
