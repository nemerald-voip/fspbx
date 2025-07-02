<?php

namespace App\Observers;

use App\Models\Devices;
use Illuminate\Support\Facades\DB;
use App\Services\CloudProviderSelector;
use App\Jobs\RegisterDeviceWithCloudProvider;
use App\Services\DeviceCloudProvisioningService;

class DeviceObserver
{
    /**
     * Handle the Devices "created" event.
     */
    public function created(Devices $devices): void
    {
        //
    }

    /**
     * Handle the Devices "updated" event.
     */
    public function updated(Devices $device)
    {
        try {            
            $original = $device->getOriginal();
            $macChanged = $device->device_address !== $original['device_address'];
            $vendorChanged = $device->device_vendor !== $original['device_vendor'];
    
            // Only proceed if either changed
            if (!($macChanged || $vendorChanged || !$device->cloudProvisioning)) {
                return;
            }

            logger('deregister');
            app(DeviceCloudProvisioningService::class)->deregister($device);
            
            logger('register');
            app(DeviceCloudProvisioningService::class)->register($device);
    
        } catch (\Throwable $e) {
            logger('DeviceObserver@updated error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        }
    }
    

    /**
     * Handle the Devices "deleted" event.
     */
    public function deleted(Devices $devices): void
    {
        //
    }

    /**
     * Handle the Devices "restored" event.
     */
    public function restored(Devices $devices): void
    {
        //
    }

    /**
     * Handle the Devices "force deleted" event.
     */
    public function forceDeleted(Devices $devices): void
    {
        //
    }
}
