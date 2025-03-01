<?php

namespace App\Jobs;

use App\Models\ScheduledCall;
use Illuminate\Bus\Queueable;
use App\Jobs\ExecuteWakeUpCall;
use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;

class ProcessScheduledCalls implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;

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
    public $backoff = 300;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    // public function __construct()
    // {
    // }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Allow only 1 tasks every 30 second
        Redis::throttle('wakeup_calls')->allow(1)->every(30)->then(function () {
            ScheduledCall::where('status', 'scheduled')
                ->where('scheduled_time', '<=', now())
                ->where('retry_count', '<=', 3)
                ->chunk(10, function ($calls) { //Chunking prevents memory overload when handling large datasets.
                    foreach ($calls as $call) {
                        foreach ($calls as $call) {
                            ExecuteWakeUpCall::dispatch($call);
                            logger("✅ Dispatched Wake-Up Call: " . $call->origination_number . '@' . $call->context);
                        }
                    }
                });
        }, function () {
            return $this->release(30); // If locked, retry in 30 seconds
        });
    }
}
