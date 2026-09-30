<?php

namespace App\Jobs;

use App\Models\CallCenterAgents;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use App\Services\CallCenterAgentDeletionService;
use RuntimeException;

class DeleteCallCenterAgent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $agent;
    public ?string $agentUuid = null;
    public ?string $domainUuid = null;
    public ?string $agentContact = null;

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
    public $maxExceptions = 5;

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
    public $backoff = 15;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(CallCenterAgents $agent)
    {
        // Scalar snapshots keep retries tied to the original account and Contact.
        $this->agentUuid = $agent->call_center_agent_uuid;
        $this->domainUuid = $agent->domain_uuid;
        $this->agentContact = $agent->agent_contact;
        $this->afterCommit();
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [(new RateLimitedWithRedis('default'))];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(CallCenterAgentDeletionService $deletion)
    {
        if (! $this->agentUuid || ! $this->domainUuid || $this->agentContact === null) {
            // Older queued payloads carry no immutable tenant identity. Never guess it.
            throw new RuntimeException('Legacy agent deletion job has no account snapshot; review and redispatch it.');
        }
        Redis::throttle('system')->allow(2)->every(1)->then(function () use ($deletion) {
            $deletion->delete($this->agentUuid, $this->domainUuid, $this->agentContact);
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(5);
        });
    }
}
