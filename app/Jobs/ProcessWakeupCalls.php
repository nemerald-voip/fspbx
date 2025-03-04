<?php

namespace App\Jobs;

use App\Models\WakeupCall;
use Illuminate\Bus\Queueable;
use App\Jobs\ExecuteWakeUpCall;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessWakeupCalls implements ShouldQueue
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
        // Allow only 10 tasks every 30 second
        Redis::throttle('wakeup_calls')->allow(10)->every(30)->then(function () {
            WakeupCall::whereIn('status', ['scheduled', 'snoozed'])
                ->where('next_attempt_at', '<=', now()) 
                ->where('retry_count', '<=', 3) // ✅ Only retry up to 3 times
                ->with('extension')
                ->chunk(10, function ($calls) { // ✅ Prevent memory overload
                    foreach ($calls as $call) {
                        ExecuteWakeUpCall::dispatch($call);
                        logger("✅ Dispatched Wake-Up Call: " . $call->extension->extension);
                    }
                });
        }, function () {
            return $this->release(30); // If locked, retry in 30 seconds
        });
    }
}
