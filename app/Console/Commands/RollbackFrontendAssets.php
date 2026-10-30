<?php

namespace App\Console\Commands;

use App\Services\FrontendAssets;
use Illuminate\Console\Command;

class RollbackFrontendAssets extends Command
{
    protected $signature = 'frontend:rollback';
    protected $description = 'Restore the previous frontend assets for the same application and module versions';

    public function handle(FrontendAssets $assets): int
    {
        try {
            $assets->rollback((require base_path('config/version.php'))['release']);
            $this->info('Previous frontend assets restored. Application source was not changed.');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }
}
