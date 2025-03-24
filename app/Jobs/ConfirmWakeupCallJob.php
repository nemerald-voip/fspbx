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

class ConfirmWakeupCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $uuid;

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
    public $backoff = 30;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(string $uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Redis::throttle('wakeup_calls')->allow(10)->every(30)->then(function () {
            $wakeupCall = WakeupCall::where('uuid', $this->uuid)->first();
    
            if (!$wakeupCall) {
                logger("Wake-up call not found: {$this->uuid}");
                return;
            }
    
            if ($wakeupCall->recurring) {
                // If recurring, schedule for the next day
                $nextDayWakeUpTime = Carbon::parse($wakeupCall->wake_up_time)->addDay();
                
                $wakeupCall->update([
                    'wake_up_time' => $nextDayWakeUpTime,
                    'next_attempt_at' => $nextDayWakeUpTime,
                    'status' => 'scheduled',
                    'retry_count' => 0, // Reset retry count
                ]);
    
                // logger("🔁 Recurring wake-up call rescheduled for the next day: {$wakeupCall->uuid} at {$nextDayWakeUpTime->toDateTimeString()}");
            } else {
                // Mark as completed
                $wakeupCall->update([
                    'status' => 'completed',
                    'next_attempt_at' => null,
                ]);
    
                // logger("✅ Wake-up call confirmed: {$wakeupCall->uuid}");
            }
        }, function () {
            return $this->release(30); // If locked, retry in 30 seconds
        });
    }
    
}
