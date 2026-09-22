<?php

namespace App\Jobs;

use App\Services\Messaging\RingotelSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMessageToRingotel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $timeout = 120;
    public $backoff = 30;

    public ?string $conversationId = null;

    public function __construct(public string $messageUuid, ?string $conversationId = null)
    {
        $this->conversationId = $conversationId;
    }

    public function handle(RingotelSyncService $service): void
    {
        $service->sync($this->messageUuid, $this->conversationId);
    }
}
