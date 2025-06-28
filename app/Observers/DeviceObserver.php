<?php

namespace App\Observers;

use App\Models\Devices;
use Illuminate\Support\Facades\DB;
use App\Services\CloudProviderSelector;
use App\Jobs\RegisterDeviceWithCloudProvider;

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
            DB::beginTransaction();
            
            $original = $device->getOriginal();
            $macChanged = $device->device_address !== $original['device_address'];
            $vendorChanged = $device->device_vendor !== $original['device_vendor'];
    
            // Only proceed if either changed
            if (!($macChanged || $vendorChanged)) {
                return;
            }
    
            $cloudProviderSelector = app()->make(CloudProviderSelector::class);
            $cloudProvider = $cloudProviderSelector->getCloudProvider($device->device_vendor);
    
            // Only proceed if a cloud provider is available
            if (!$cloudProvider) {
                return;
            }
            // Create/update provisioning record with status 'pending'
            $provisioning = $device->cloudProvisioning()->firstOrNew([]);
            $provisioning->provider = $device->device_vendor;
            $provisioning->status = 'pending';
            $provisioning->error = null;
            $provisioning->save();
            logger('device updated');
            DB::commit();
            
            // Dispatch job for async provisioning (recommended)
            RegisterDeviceWithCloudProvider::dispatch($device, $original['device_address'], $original['device_vendor'], $provisioning->uuid);
            
            logger('job dispatched');
    
        } catch (\Throwable $e) {
            DB::rollBack();
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
