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
    public $maxExceptions = 3;
    public $timeout = 300;
    public $failOnTimeout = true;
    public $backoff = 60;
    public $deleteWhenMissingModels = true;

    public function __construct(int $daysKeepTranscriptions = 90)
    {
        $this->daysKeepTranscriptions = $daysKeepTranscriptions;
    }

    public function handle()
    {
        Redis::throttle('delete-transcriptions')
            ->allow(2)->every(60)
            ->then(function () {

                try {
                    $days   = $this->daysKeepTranscriptions;
                    $cutoff = Carbon::now()->subDays($days);

                    CallTranscription::where('created_at', '<', $cutoff)->delete();
                } catch (\Exception $e) {
                    logger('DeleteOldTranscriptions@handle error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
                }
            }, function () {
                return $this->release(60);
            });
    }
}
