<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeleteOldEmailLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $daysKeepEmailLogs;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;


    /**
     * Create a new job instance.
     *
     * @param int $daysKeepEmailLogs Number of days to retain email logs (older email logs will be deleted)
     */
    public function __construct(int $daysKeepEmailLogs = 90)
    {
        $this->daysKeepEmailLogs = $daysKeepEmailLogs;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Redis::throttle('default')->allow(2)->every(60)->then(function () {

            try {
                $days = $this->daysKeepEmailLogs;
                EmailLog::where('created_at', '<', Carbon::now()->subDays($days))->delete();
            } catch (\Exception $e) {
                logger('DeleteOldEmailLogs@handle error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            }
        }, function () {
            return $this->release(60); // If locked, retry in 30 seconds
        });
    }
}
