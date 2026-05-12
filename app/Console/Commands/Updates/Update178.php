<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Artisan;

class Update178
{
    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply(): bool
    {
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

        $this->updateMusicOnHoldMenuItems();

        echo "Update 1.7.8 completed successfully.\n";
        return true;
    }

    private function updateMusicOnHoldMenuItems(): void
    {
        $updated = MenuItem::query()
            ->where('menu_item_title', 'Music on Hold')
            ->where('menu_item_link', '/app/music_on_hold/music_on_hold.php')
            ->update([
                'menu_item_link' => '/music-on-hold',
            ]);

        echo $updated === 0
            ? "No Music on Hold menu items required updating.\n"
            : "Updated {$updated} Music on Hold menu item(s).\n";
    }
}
