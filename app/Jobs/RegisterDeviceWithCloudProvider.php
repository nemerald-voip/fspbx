<?php

namespace App\Jobs;

use App\Models\Devices;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use App\Models\DeviceCloudProvisioning;
use App\Services\CloudProviderSelector;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimited;

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

                $result = $cloudProvider->deleteDevice($this->oldMac);
                logger($result);
                $result = $cloudProvider->createDevice($device->device_address, $polycom_ztp_profile_id);
                logger($result);
    
                $provisioning->status = 'success';
                $provisioning->error = null;
                $provisioning->save();
    
                return true;
            } catch (\Throwable $e) {
                $provisioning->status = 'error';
                $provisioning->error = $e->getMessage();
                $provisioning->save();
    
                Log::error("RegisterDeviceWithCloudProvider@handle Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                return false;
            }
        }, function () {
            Log::info('[RegisterDeviceWithCloudProvider] Throttled, will retry.');
            return $this->release(30);
        });
    }
}
