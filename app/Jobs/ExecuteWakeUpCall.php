<?php

namespace App\Jobs;

use App\Models\ScheduledCall;
use Illuminate\Bus\Queueable;
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
    public function __construct(ScheduledCall $call)
    {
        $this->call = $call;
    }
    /**
     * Execute the job.
     */
    public function handle(FreeswitchEslService $eslService)
    {
        if ($this->call->status !== 'scheduled') {
            logger("🚫 Call {$this->call->origination_number}@{$this->call->context} already processed. Skipping.");
            return;
        }

        // 🚀 **Redis Throttling: Limit calls to 10 every 30 seconds**
        Redis::throttle('wakeup_calls')->allow(10)->every(30)->then(function () use ($eslService) {
            try {
                logger("📞 Initiating Wake-Up Call to {$this->call->origination_number}@{$this->call->context}");

                // **Step 1: Originate Call to FreeSWITCH**
                $response = $eslService->executeCommand("originate {origination_caller_id_number={$this->call->origination_number},origination_caller_id_name='Wakeup Call',hangup_after_bridge=true,originate_timeout=30}user/{$this->call->origination_number}@{$this->call->context} &lua wakeup.lua");

                logger("🛠 ESL Response: " . json_encode($response));

                if (!$response || $response['status'] === 'error') {
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
            $delay = ($this->call->retry_count + 1) * 1; // 1st retry +1 min, 2nd +2 min, 3rd +3 min
            $this->call->update([
                'status' => 'scheduled',
                'retry_count' => $this->call->retry_count + 1,
                'scheduled_time' => now()->addMinutes($delay),
            ]);

            logger("🔄 Rescheduling Wake-Up Call for {$this->call->origination_number}@{$this->call->context} in {$delay} minutes.");
        } else {
            // ⛔ Stop retrying after 3 failed attempts
            $this->call->update(['status' => 'failed']);
            logger("⛔ Wake-Up Call failed permanently for {$this->call->origination_number}@{$this->call->context}.");
        }
    }
}
