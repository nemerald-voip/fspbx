<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use App\Models\CallTranscription;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeleteOldTranscriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $daysKeepTranscriptions;

    public $tries = 10;
    public $maxExceptions = 10;
    public $timeout = 300;
    public $failOnTimeout = true;
    public $backoff = 300;
    public $deleteWhenMissingModels = true;

    public function __construct(int $daysKeepTranscriptions = 90)
    {
        $this->daysKeepTranscriptions = $daysKeepTranscriptions;
    }

    public function handle()
    {
        Redis::throttle('delete-transcriptions')
            ->allow(30)->every(60)
            ->then(function () {

                try {
                    $days   = $this->daysKeepTranscriptions;
                    $cutoff = Carbon::now()->subDays($days);

                    // Use COALESCE to determine age based on most meaningful timestamp
                    CallTranscription::whereRaw(
                        "COALESCE(completed_at, requested_at, created_at) < ?",
                        [$cutoff]
                    )
                    ->chunkById(1000, function ($rows) {
                        foreach ($rows as $row) {
                            $row->delete();
                        }
                    });

                } catch (\Exception $e) {
                    logger("Error pruning call transcriptions: " . $e->getMessage());
                }

            }, function () {
                return $this->release(60);
            });
    }
}
