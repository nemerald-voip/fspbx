<?php

namespace App\Console\Commands;

use App\Jobs\QueueAiProviderToolSyncs;
use Illuminate\Console\Command;

class SyncAiProviderTools extends Command
{
    protected $signature = 'ai-agents:sync-provider-tools
        {--delay=0 : Delay dispatch by this many seconds}
        {--force : Verify provider state even when the local catalog is current}
        {--reason=manual : Reason recorded with the coordinator job}';

    protected $description = 'Queue synchronization of FS PBX-managed tools to AI providers';

    public function handle(): int
    {
        $delay = max(0, (int) $this->option('delay'));
        $dispatch = QueueAiProviderToolSyncs::dispatch(
            (bool) $this->option('force'),
            trim((string) $this->option('reason')) ?: 'manual',
        );

        if ($delay > 0) {
            $dispatch->delay(now()->addSeconds($delay));
        }

        unset($dispatch);

        $this->info($delay > 0
            ? "AI provider tool synchronization queued with a {$delay}-second delay."
            : 'AI provider tool synchronization queued.');

        return self::SUCCESS;
    }
}
