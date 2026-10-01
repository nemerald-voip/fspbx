<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Process;
use Throwable;

class Update204
{
    public function apply(): bool
    {
        try {
            // Install the native packages before recording 2.0.4 as applied. All PHP
            // dependencies already exist; photo processing uses native tools.
            Process::timeout(600)->run([
                'bash', base_path('install/install_message_photos.sh'),
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
