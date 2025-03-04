<?php

namespace App\Jobs;

use App\Models\WakeupCall;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use App\Services\FreeswitchEslService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ExecuteWakeUpCall implements ShouldQueue
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

    protected $call;

    /**
     * Create a new job instance.
     */
    public function __construct(WakeupCall $call)
    {
        $this->call = $call;
    }
    /**
     * Execute the job.
     */
    public function handle(FreeswitchEslService $eslService)
    {
        if ($this->call->status !== 'scheduled' && $this->call->status !== 'snoozed') {
            logger("🚫 Call {$this->call->extension->extension} already processed. Skipping.");
            return;
        }

        // 🚀 **Redis Throttling: Limit calls to 10 every 30 seconds**
        Redis::throttle('wakeup_calls')->allow(10)->every(30)->then(function () use ($eslService) {
            try {
                logger("📞 Initiating Wake-Up Call to {$this->call->extension->extension}");

                // **Step 1: Originate Call to FreeSWITCH**
                $response = $eslService->executeCommand(
                    "originate {origination_caller_id_number={$this->call->extension->extension},origination_caller_id_name='Wakeup Call',hangup_after_bridge=true,originate_timeout=30,call_direction='local',wakeup_call_uuid={$this->call->uuid}}user/{$this->call->extension->extension}@{$this->call->extension->user_context} &lua(lua/wakeup_call.lua)"
                );

                // logger("originate {origination_caller_id_number={$this->call->extension->extension},origination_caller_id_name='Wakeup Call',hangup_after_bridge=true,originate_timeout=30}user/{$this->call->extension->extension}@{$this->call->extension->user_context} &lua wakeup.lua");
                // logger("🛠 ESL Response: " . json_encode($response));

                if (!$response) {
                    // ❌ Call was rejected or not answered → Retry with increasing delay
                    $this->retryCall();
                } else {
                    // ✅ Call was successfully placed → Lua will handle snooze or completion
                    logger("✅ Wake-Up Call placed, awaiting Lua IVR decision.");
                    $this->call->update(['status' => 'in_progress']);
                }
            } catch (\Exception $e) {
                logger("❌ Error executing Wake-Up Call for {$this->call->origination_number}: " . $e->getMessage());
                $this->retryCall();
            }
        }, function () {
            logger("⏳ Wake-Up Call rate limit reached. Retrying in 10 seconds.");
            return $this->release(10);
        });
    }

    /**
     * Retry the call with increasing delay or mark as failed after 3 attempts.
     */
    private function retryCall()
    {
        if ($this->call->retry_count < 3) {
            // Calculate cumulative retry delay using summation formula
            $delay = (($this->call->retry_count + 1) * ($this->call->retry_count + 2)) / 2;

            // Apply the delay to the original wake-up time
            $newAttemptTime = Carbon::parse($this->call->wake_up_time)->addMinutes($delay);

            $this->call->update([
                'status' => 'scheduled',
                'retry_count' => $this->call->retry_count + 1,
                'next_attempt_at' => $newAttemptTime,
            ]);

            logger("🔄 Rescheduling Wake-Up Call for {$this->call->extension->extension} at {$newAttemptTime->toDateTimeString()}.");
        } else {
            // ⛔ Stop retrying after 3 failed attempts
            if ($this->call->recurring) {
                // ✅ If recurring, schedule for the next day
                $this->call->update([
                    'status' => 'scheduled',
                    'retry_count' => 0, // Reset retry count
                    'next_attempt_at' => Carbon::parse($this->call->wake_up_time)->addDay(),
                ]);

                logger("🔁 Recurring wake-up call rescheduled for the next day: {$this->call->extension->extension}");
            } else {
                $this->call->update(['status' => 'failed']);
                logger("⛔ Wake-Up Call failed permanently for {$this->call->extension->extension}.");
            }
        }
    }
}
