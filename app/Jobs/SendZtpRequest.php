<?php

namespace App\Jobs;

use App\Models\CloudProvisioningStatus;
use App\Models\Devices;
use App\Services\Exceptions\ZtpProviderException;
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

    private ?string $organizationId;

    private bool $forceRemove;

    const ACTION_CREATE = 'createDevice';
    const ACTION_DELETE = 'deleteDevice';

    /**
     * Create a new job instance.
     *
     * @param  string  $action
     * @param  string  $provider
     * @param  string  $deviceMacAddress
     * @param  ?string $organizationId
     * @param  bool    $forceRemove
     * @return void
     */
    public function __construct(
        string $action,
        string $provider,
        string $deviceMacAddress,
        string $organizationId = null,
        bool $forceRemove = false
    ) {
        $this->action = $action;
        $this->provider = $provider;
        $this->deviceMacAddress = $deviceMacAddress;
        $this->organizationId = $organizationId;
        $this->forceRemove = $forceRemove;
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
            $cloudProvider = $this->createCloudProvider();

            try {
                $existingDevice = Devices::where(['device_address' => $this->deviceMacAddress])->first();

                if ($this->forceRemove) {
                    $this->processForceRemove($cloudProvider);
                } else {
                    $this->processDeviceAction($cloudProvider, $existingDevice);
                }
            } catch (ZtpProviderException $e) {
                logger($e);
                $this->handleError($existingDevice ?? null, $e->getMessage());
            } catch (\Exception $e) {
                logger($e);
                $this->delete(); // Delete job if an unknown issue occurs.
            }
        }, function () {
            $this->release(5); // Could not obtain lock; this job will be re-queued.
        });
    }

    private function createCloudProvider(): PolycomZtpProvider
    {
        return match ($this->provider) {
            'polycom' => new PolycomZtpProvider(),
            default => throw new \InvalidArgumentException('Invalid provider: ' . $this->provider),
        };
    }

    private function processForceRemove($cloudProvider): void
    {
        CloudProvisioningStatus::where('device_address', $this->deviceMacAddress)->delete();
        $cloudProvider->deleteDevice($this->deviceMacAddress);
    }

    private function processDeviceAction($cloudProvider, $existingDevice): void
    {
        if (!$existingDevice) {
            throw new \RuntimeException('Device not found.');
        }

        try {
            // Perform action based on `action`
            match ($this->action) {
                self::ACTION_CREATE => $cloudProvider->createDevice(
                    $this->deviceMacAddress,
                    $this->organizationId
                ),
                self::ACTION_DELETE => $cloudProvider->deleteDevice(
                    $this->deviceMacAddress
                ),
                default => throw new \InvalidArgumentException('Invalid action: '.$this->action),
            };

            $statusPayload = [
                'provider' => $this->provider,
                'device_address' => $this->deviceMacAddress,
                'status' => ($this->action === self::ACTION_CREATE) ? 'provisioned' : 'not_provisioned',
                'error' => '',
            ];

            $existingDevice->cloudProvisioningStatus()->updateOrInsert(
                ['device_uuid' => $existingDevice->device_uuid],
                $statusPayload
            );
        } catch (\Exception $e) {
            $existingDevice->cloudProvisioningStatus()->updateOrInsert(
                ['device_uuid' => $existingDevice->device_uuid],
                [
                    'provider' => $this->provider,
                    'device_address' => $this->deviceMacAddress,
                    'status' => 'error',
                    'error' => $e->getMessage() ?? 'Unknown error',
                ]
            );
        }
    }

    private function handleError($device, $errorMessage): void
    {
        $response = json_decode($errorMessage);

        if ($device) {
            $errorPayload = [
                'provider' => $this->provider,
                'device_address' => $this->deviceMacAddress,
                'status' => 'error',
                'error' => $response->message ?? 'Unknown error',
            ];

            $device->cloudProvisioningStatus()->updateOrInsert(
                ['device_uuid' => $device->device_uuid],
                $errorPayload
            );
        }
    }
}
