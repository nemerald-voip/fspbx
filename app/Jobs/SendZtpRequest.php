<?php

namespace App\Jobs;

use App\Models\Commio\CommioOutboundSMS;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Redis\LimiterTimeoutException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class SendZtpRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public int $maxExceptions = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 120;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public bool $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public int $backoff = 30;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public bool $deleteWhenMissingModels = true;

    private string $deviceId;
    private string $orgId;
    private string $provider;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($provider, $deviceId, $orgId)
    {
        $this->provider = $provider;
        $this->deviceId = $deviceId;
        $this->orgId = $orgId;
        logger('Init queue for ZTP request');
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware(): array
    {
        return [(new RateLimitedWithRedis('ztp_requests'))];
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws LimiterTimeoutException
     */
    public function handle(): void
    {
        logger('Handle method called');
        try {
            logger('Attempting to throttle ZTP requests');
            Redis::throttle('ztp_requests')->allow(2)->every(1)->then(function () {
                logger('Throttling successful, sending ZTP request');
                // Your logic for sending the ZTP request
            }, function () {
                // Could not obtain lock; this job will be re-queued
                logger('Throttling failed, releasing job back to queue');
                $this->release(5);
            });
        } catch (\Exception $e) {
            logger('Exception in handle method: ' . $e->getMessage());
            throw $e; // Optionally rethrow the exception to mark the job as failed
        }
    }
}
