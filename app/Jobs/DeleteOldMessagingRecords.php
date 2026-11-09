<?php

namespace App\Jobs;

use App\Services\Ha\ActiveNodeResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DeleteOldMessagingRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const TABLES = [
        'message_api_requests' => 'message_api_request_uuid',
        'outbound_message_webhook_events' => 'outbound_message_webhook_event_uuid',
        'outbound_webhook_logs' => 'outbound_webhook_log_uuid',
    ];

    public int $timeout = 300;
    public int $tries = 3;
    public int $backoff = 60;
    public bool $failOnTimeout = true;

    public function __construct(public readonly string $table, public readonly int $days = 30)
    {
        $this->onConnection('scheduled-jobs');
        $this->onQueue('scheduled-jobs');
    }

    public function handle(ActiveNodeResolver $activeNode): void
    {
        if (!isset(self::TABLES[$this->table]) || $this->days < 1) {
            throw new \InvalidArgumentException('Select a supported messaging table and a positive retention period.');
        }
        if (!Schema::hasTable($this->table)) return;

        $execution = $activeNode->claimExecution('messaging_record_retention', $this->table, $this->timeout);
        if (!$execution) return;

        try {
            $cutoff = now()->subDays($this->days);
            $deadline = microtime(true) + 240;
            $primaryKey = self::TABLES[$this->table];
            do {
                $count = $activeNode->withExecution($execution, function () use ($cutoff, $primaryKey) {
                    $query = $this->candidates($cutoff);
                    $ids = (clone $query)->orderBy($primaryKey)->limit(500)->lockForUpdate()->pluck($primaryKey);
                    if ($ids->isNotEmpty()) {
                        // Reapply expiry/status conditions to the deletion too.
                        $query->whereIn($primaryKey, $ids)->delete();
                    }
                    return $ids->count();
                });
            } while ($count === 500 && microtime(true) < $deadline);
            $activeNode->finishExecution($execution);
        } catch (Throwable $exception) {
            $activeNode->finishExecution($execution, 'failed', 'Messaging record retention failed. See application logs.');
            throw $exception;
        }

        if ($count === 500) self::dispatch($this->table, $this->days)->delay(now()->addMinute());
    }

    private function candidates($cutoff): Builder
    {
        $query = DB::table($this->table)->where('created_at', '<', $cutoff);

        return $this->table === 'message_api_requests'
            ? $query->where('expires_at', '<=', now())
            : $query->whereIn('status', ['delivered', 'failed']);
    }
}
