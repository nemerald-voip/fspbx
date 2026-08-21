<?php

namespace App\Jobs;

use App\Services\AiTools\AiProviderToolCatalog;
use App\Services\AiTools\AiProviderToolSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAiProviderAgentTools implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;
    public int $maxExceptions = 4;
    public int $timeout = 120;
    public int $backoff = 30;
    public int $uniqueFor = 300;

    public function __construct(
        public readonly string $provider,
        public readonly string $providerAgentId,
        public readonly bool $force = false,
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return implode(':', [
            'ai-provider-agent-tools',
            $this->provider,
            $this->providerAgentId,
            AiProviderToolCatalog::REVISION,
            $this->force ? 'force' : 'normal',
        ]);
    }

    public function handle(AiProviderToolSyncService $syncs): void
    {
        if (! $syncs->ready()) {
            $this->release(300);
            return;
        }

        $syncs->synchronize($this->provider, $this->providerAgentId, $this->force);
    }
}
