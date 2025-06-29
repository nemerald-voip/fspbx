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

class RegisterDeviceWithCloudProvider implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $maxExceptions = 5;
    public $timeout = 180;
    public $failOnTimeout = true;
    public $backoff = 300;
    public $deleteWhenMissingModels = true;

    protected $deviceUuid;
    protected $oldMac;
    protected $oldVendor;
    protected $provisioningUuid;

    public function __construct(Devices $device, $oldMac, $oldVendor, $provisioningUuid)
    {
        $this->deviceUuid = $device->device_uuid;
        $this->oldMac = $oldMac;
        $this->oldVendor = $oldVendor;
        $this->provisioningUuid = $provisioningUuid;
    }

    public function handle()
    {
        Redis::throttle('cloud-provider-jobs')->allow(1)->every(2)->then(function () {
            $device = Devices::where('device_uuid', $this->deviceUuid)->first();
            $provisioning = DeviceCloudProvisioning::where('uuid', $this->provisioningUuid)->first();
            $polycom_ztp_profile_id = get_domain_setting('polycom_ztp_profile_id', $device->domain_uuid);

            if (!$device || !$provisioning || !$polycom_ztp_profile_id ) {
                return true;
            }
    
            try {
                $cloudProviderSelector = app()->make(CloudProviderSelector::class);
                $cloudProvider = $cloudProviderSelector->getCloudProvider($device->device_vendor);
                $cloudProvider->ensureApiTokenExists();

                try {
                    // Try deleting old device
                    $cloudProvider->deleteDevice($this->oldMac);
                } catch (\Exception $e) {
                        logger("Device {$this->oldMac} not found in cloud provider, continuing with creating a new device.");
                }
                // Create device
                $cloudProvider->createDevice($device->device_address, $polycom_ztp_profile_id);
    
                $provisioning->status = 'provisioned';
                $provisioning->error = null;
                $provisioning->save();
    
                return true;
            } catch (\Throwable $e) {
                $provisioning->status = 'error';
                $provisioning->error = $e->getMessage();
                $provisioning->save();
    
                logger("RegisterDeviceWithCloudProvider@handle Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                return false;
            }
        }, function () {
            return $this->release(30);
        });
    }
}
