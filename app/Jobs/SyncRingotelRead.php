<?php

namespace App\Jobs;

use App\Services\Messaging\RingotelReadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncRingotelRead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 8;
    public $timeout = 45;
    public $backoff = 15;
    // Default also applies when older serialized jobs are restored.
    public bool $authorizedViewAs = false;

    public function __construct(public string $messageUuid, public string $extensionUuid, public ?string $userUuid = null, bool $authorizedViewAs = false)
    {
        $this->authorizedViewAs = $authorizedViewAs;
    }

    public function handle(RingotelReadService $service): void
    {
        if (!$service->sync($this->messageUuid, $this->extensionUuid, $this->userUuid, $this->authorizedViewAs)) {
            $this->release(15);
        }
    }
}
