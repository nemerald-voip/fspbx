<?php

namespace App\Jobs;

use App\Models\WakeupCall;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SnoozeWakeupCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $uuid;
    protected int $minutes;


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
    public function __construct(string $uuid, int $minutes)
    {
        $this->uuid = $uuid;
        $this->minutes = $minutes;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Redis::throttle('wakeup_calls')->allow(10)->every(30)->then(function () {

            $wakeupCall = WakeupCall::where('uuid', $this->uuid)->first();

            if (!$wakeupCall) {
                Log::error("Wake-up call not found: {$this->uuid}");
                return;
            }

            // Apply the snooze time to the original wake-up time instead of `now()`
            $newAttemptTime = Carbon::parse($wakeupCall->next_attempt_at)->addMinutes($this->minutes);

            // Update wake-up call next attempt time
            $wakeupCall->update([
                'next_attempt_at' => $newAttemptTime,
                'status' => 'snoozed'
            ]);

            Log::info("🔕 Wake-up call snoozed: {$this->uuid} until {$newAttemptTime->toDateTimeString()}");
        }, function () {
            return $this->release(30); // If locked, retry in 30 seconds
        });
    }
}
