<?php

namespace App\Jobs;

use App\Models\Devices;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use App\Models\DeviceCloudProvisioning;
use App\Services\CloudProviderSelector;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeregisterDeviceWithCloudProvider implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $maxExceptions = 5;
    public $timeout = 180;
    public $failOnTimeout = true;
    public $backoff = 300;
    public $deleteWhenMissingModels = true;

    protected $device;
    protected $oldMac;
    protected $oldVendor;
    protected $provisioningUuid;

    public function __construct(Devices $device, $oldMac, $oldVendor)
    {
        $this->device = $device;
        $this->oldMac = $oldMac;
        $this->oldVendor = $oldVendor;
    }

    public function handle()
    {
        Redis::throttle('cloud-provider-jobs')->allow(1)->every(2)->then(function () {

            if (!$this->device) {
                return true;
            }

            try {
                $cloudProviderSelector = app()->make(CloudProviderSelector::class);
                $cloudProvider = $cloudProviderSelector->getCloudProvider($this->oldVendor);

                if (!$cloudProvider) {
                    return false;
                }

                $cloudProvider->ensureApiTokenExists();

                // Delete device
                $this->device->device_address = $this->oldMac;
                $result = $cloudProvider->deleteDevice($this->device);

                logger('deregister result:');
                logger($result);

                if ($result['success'] == true) {
                    $this->device->cloudProvisioning->delete();
                } else {
                    $this->device->cloudProvisioning->status = 'error';
                    $this->device->cloudProvisioning->last_action = 'deregister';
                    $this->device->cloudProvisioning->error = $result['error'];
                    $this->device->cloudProvisioning->save();
                }

                return true;
            } catch (\Throwable $e) {
                $this->device->cloudProvisioning->status = 'error';
                $this->device->cloudProvisioning->error = $e->getMessage();
                $this->device->cloudProvisioning->save();

                logger("RegisterDeviceWithCloudProvider@handle Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                return false;
            }
        }, function () {
            return $this->release(30);
        });
    }
}
