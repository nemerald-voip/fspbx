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
        $result = $svc->refreshModules();

        foreach ($result['updated'] as $msg) $this->line("✅ {$msg}");
        foreach ($result['skipped'] as $msg) $this->line("↩️  {$msg}");
        foreach ($result['errors'] as $msg) $this->error("❌ {$msg}");

        return empty($result['errors']) ? self::SUCCESS : self::FAILURE;
    }
}
