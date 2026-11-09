<?php

namespace App\Jobs;

use App\Services\Ha\ActiveNodeResolver;
use App\Services\Messaging\MessageRetentionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DeleteOldMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;
    public int $backoff = 60;
    public bool $failOnTimeout = true;

    public function __construct(public readonly int $days = 90, public readonly ?string $after = null)
    {
        $this->onConnection('scheduled-jobs');
        $this->onQueue('scheduled-jobs');
    }

    public function handle(MessageRetentionService $retention, ActiveNodeResolver $activeNode): void
    {
        if ($this->days < 1) {
            throw new \InvalidArgumentException('Message retention must be a positive number of days.');
        }

        $execution = $activeNode->claimExecution('message_retention', 'messages', $this->timeout);
        if (!$execution) return;

        try {
            $next = $retention->deleteExpired($this->days, $execution, $this->after);
            $activeNode->finishExecution($execution);
        } catch (Throwable $exception) {
            $activeNode->finishExecution($execution, 'failed', 'Message retention failed. See application logs.');
            throw $exception;
        }

        if ($next !== null) {
            self::dispatch($this->days, $next)->delay(now()->addMinute());
        }
    }
}
