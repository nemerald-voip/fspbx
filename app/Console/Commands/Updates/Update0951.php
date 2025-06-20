<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Artisan;
use App\Models\FusionCache;

class Update0951
{
    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply(): bool
    {
        $result = $this->runMenuUpdate();

        return $result === 0;
    }

    /**
     * Run the artisan command to update the FS PBX menu.
     *
     * @return int Exit code of the Artisan call
     */
    protected function runMenuUpdate(): int
    {
        echo "Running menu:update (menu:create-fspbx --update)...\n";
        $exitCode = Artisan::call('menu:create-fspbx', ['--update' => true]);
        $output   = Artisan::output();
        echo $output;

        if ($exitCode !== 0) {
            echo "Error: Menu update command failed with exit code $exitCode.\n";
        } else {
            echo "Menu update completed successfully.\n";
        }

        return $exitCode;
    }
}
