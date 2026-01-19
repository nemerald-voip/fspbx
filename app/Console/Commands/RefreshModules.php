<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProFeaturesService;

class RefreshModules extends Command
{
    protected $signature = 'modules:refresh';
    protected $description = 'Refresh enabled modules using the FS PBX Pro Feature license';

    public function handle(ProFeaturesService $svc)
    {
        try {
            $result = $svc->refreshModules();
        } catch (\Throwable $e) {
            $this->warn("⚠️  modules:refresh encountered an unexpected error: {$e->getMessage()}");
            return self::SUCCESS;
        }

        foreach ($result['updated'] as $msg) $this->line("✅ {$msg}");
        foreach ($result['skipped'] as $msg) $this->line("↩️  {$msg}");
        foreach ($result['errors'] as $msg) $this->warn("⚠️  {$msg}");

        // Always successful exit code
        return self::SUCCESS;
    }

}
