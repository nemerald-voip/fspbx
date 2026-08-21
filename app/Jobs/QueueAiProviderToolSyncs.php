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

class QueueAiProviderToolSyncs implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 12;
    public int $timeout = 60;
    public int $uniqueFor = 600;

    public function __construct(
        public readonly bool $force = false,
        public readonly string $reason = 'manual',
        public readonly ?string $providerAgentId = null,
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return implode(':', [
            'ai-provider-tools',
            AiProviderToolCatalog::REVISION,
            $this->force ? 'force' : 'normal',
            $this->providerAgentId ?: 'all',
        ]);
    }

    public function handle(AiProviderToolSyncService $syncs): void
    {
        if (! $syncs->ready()) {
            if ($this->attempts() < $this->tries) {
                $this->release(300);
            }

            return;
        }

        foreach ($syncs->targets($this->providerAgentId) as $target) {
            if (! $syncs->shouldSync($target['provider'], $target['provider_agent_id'], $this->force)) {
                continue;
            }

            $syncs->markPending($target['provider'], $target['provider_agent_id']);
            SyncAiProviderAgentTools::dispatch(
                $target['provider'],
                $target['provider_agent_id'],
                $this->force,
            );
        }
    }
}
