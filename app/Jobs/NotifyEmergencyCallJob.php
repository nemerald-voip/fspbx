<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\EmergencyCallMember;
use Illuminate\Support\Facades\Redis;
use App\Services\FreeswitchEslService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class NotifyEmergencyCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public EmergencyCallMember $member;
    public string $caller;


    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

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
    public function __construct(EmergencyCallMember $member, string $caller)
    {
        $this->member = $member;
        $this->caller = $caller;
    }

    /**
     * Execute the job.
     */
    public function handle(FreeswitchEslService $eslService)
    {
        // 🚀 **Redis Throttling: Limit calls to 10 every 30 seconds**
        Redis::throttle('emergency_calls')->allow(10)->every(30)->then(function () use ($eslService) {
            try {

                $this->member->loadMissing('extension.domain');

                logger("📞 Notifying {$this->member->extension_uuid} about emergency call from {$this->caller}");

                // **Step 1: Originate Call to FreeSWITCH**
                $response = $eslService->executeCommand(
                    "originate {origination_caller_id_number={$this->caller},origination_caller_id_name='Emergency Call {$this->caller}',hangup_after_bridge=true,originate_timeout=30,call_direction='local'}user/{$this->member->extension->extension}@{$this->member->extension->domain->domain_name} &lua(lua/wakeup_call.lua)"
                );

                // logger("originate {origination_caller_id_number={$this->caller},origination_caller_id_name='Emergency Call {$this->caller}',hangup_after_bridge=true,originate_timeout=30,call_direction='local'}user/{$this->member->extension->extension}@{$this->member->extension->domain->domain_name} &lua(lua/wakeup_call.lua)");
                logger("🛠 ESL Response: " . json_encode($response));

                if (!$response) {
                    // ❌ Call was rejected or not answered
                    logger("❌ Error executing emergency call notification.");
                    return $this->release(10);
                }
            } catch (\Exception $e) {
                logger("❌ Error executing emergency call notification for {$this->member->extension_uuid}: " . $e->getMessage());
                return $this->release(10);
            }
        }, function () {
            return $this->release(30); // If locked, retry in 30 seconds
        });
    }
}
