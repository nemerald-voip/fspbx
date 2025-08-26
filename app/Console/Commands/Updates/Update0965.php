<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Artisan;

class Update0965
{
    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply(): bool
    {
        // Step 1: Create provisioning symlinks
        echo "Creating provisioning symlinks...\n";
        $exitCode = Artisan::call('provisioning:link-templates');
        echo Artisan::output();
        if ($exitCode !== 0) {
            echo "Symlink creation finished with non-zero exit code: {$exitCode}\n";
            return false;
        }

        // Step 2: Seed device vendors
        echo "Running DeviceVendorsSeeder...\n";
        $exitCode = Artisan::call('db:seed', [
            '--class'          => \Database\Seeders\DeviceVendorsSeeder::class,
            '--force'          => true,
            '--no-interaction' => true,
        ]);
        echo Artisan::output();
        if ($exitCode !== 0) {
            echo "Seeding DeviceVendorsSeeder failed with exit code: {$exitCode}\n";
            return false;
        }

        echo "Update 0.9.65 completed successfully.\n";
        return true;
    }
}
