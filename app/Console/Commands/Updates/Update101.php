<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Artisan;

class Update101
{
    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply(): bool
    {

        // Step 1: Seed device vendors
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

        echo "Update 1.0.1 completed successfully.\n";
        return true;
    }
}
