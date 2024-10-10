<?php

namespace App\Jobs;

use App\Services\PolycomZtpProvider;
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

    /**
     * The UUID of the device.
     *
     * @var string
     */
    private string $deviceMacAddress;

    /**
     * The action to be performed.
     *
     * @var string
     */
    private string $action;

    private string $provider;

    private string $organisationId;

    const ACTION_CREATE = 'createDevice';
    const ACTION_DELETE = 'deleteDevice';

    /**
     * Create a new job instance.
     *
     * @param  string  $deviceUuid
     * @param  string  $action
     * @return void
     */
    public function __construct(
        string $action,
        string $provider,
        string $deviceMacAddress,
        string $organisationId = null
    ) {
        $this->action = $action;
        $this->provider = $provider;
        $this->deviceMacAddress = $deviceMacAddress;
        $this->organisationId = $organisationId;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware(): array
    {
        return [(new RateLimitedWithRedis('ztp'))];
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws LimiterTimeoutException
     */
    public function handle(): void
    {
        Redis::throttle('ztp')->allow(2)->every(1)->then(function () {
            $cloudProvider = match ($this->provider) {
                'polycom' => new PolycomZtpProvider()
            };
            try {
                $cloudProvider->{$this->action}(
                    $this->deviceMacAddress,
                    $this->organisationId
                );
            } catch (\Exception $e) {
                logger($e);
                $this->delete();
            }
        }, function () {
            // Could not obtain lock; this job will be re-queued
            $this->release(5);
        });
    }
}
