<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Process;
use Throwable;

class Update207
{
    public function apply(): bool
    {
        try {
            // Re-run the shared, version-aware installer even on servers that
            // completed 2.0.6 with Debian 12's older HEIC decoder. Unsupported
            // operating systems can defer photo setup until their OS upgrade.
            Process::timeout(600)->run([
                'bash', base_path('install/install_message_photos.sh'), '--skip-unsupported',
            ], function (string $type, string $output) {
                echo $output;
            })->throw();

            return true;
        } catch (Throwable $exception) {
            echo 'HEIC compatibility setup failed: '.$exception->getMessage()."\n";

            return false;
        }
    }
}
