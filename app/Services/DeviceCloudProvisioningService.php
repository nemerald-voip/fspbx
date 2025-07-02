<?php

namespace App\Services;

use App\Models\Devices;
use App\Jobs\RegisterDeviceWithCloudProvider;
use App\Jobs\DeregisterDeviceWithCloudProvider;

class DeviceCloudProvisioningService
{
    public function register(Devices $device)
    {
        // If old values are not passed, get them from the model
        $original = $device->getOriginal();
        $oldMac = $original['device_address'] ?? null;
        $oldVendor = $original['device_vendor'] ?? null;

        // Create/update provisioning record with status 'pending'
        $provisioning = $device->cloudProvisioning()->firstOrNew([
            'device_uuid' => $device->device_uuid,
            'domain_uuid' => $device->domain_uuid,
        ]);
        $provisioning->provider = $device->device_vendor;
        $provisioning->last_action = 'register';
        $provisioning->status = 'pending';
        $provisioning->error = null;
        $provisioning->save();

        // Dispatch job for async provisioning
        RegisterDeviceWithCloudProvider::dispatch($device, $oldMac, $oldVendor);

        return $provisioning;
    }

    public function deregister(Devices $device)
    {
        // If old values are not passed, get them from the model
        $original = $device->getOriginal();
        $oldMac = $original['device_address'] ?? null;
        $oldVendor = $original['device_vendor'] ?? null;

        // Update provisioning record with status 'pending'
        $provisioning = $device->cloudProvisioning;
        if ($provisioning) {
            $provisioning->provider = $device->device_vendor;
            $provisioning->last_action = 'deregister';
            $provisioning->status = 'pending';
            $provisioning->error = null;
            $provisioning->save();
    
            // Dispatch job for async provisioning
            DeregisterDeviceWithCloudProvider::dispatch($device, $oldMac, $oldVendor);
        }

        return $provisioning;
    }
}
