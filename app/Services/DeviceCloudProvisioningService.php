<?php

namespace App\Services;

use App\Jobs\RegisterDeviceWithCloudProvider;
use App\Jobs\DeregisterDeviceWithCloudProvider;
use App\Jobs\DeleteDeviceCloudProvisioningRecord;
use App\Models\DeviceCloudProvisioning;

class DeviceCloudProvisioningService
{
    public function register($params)
    {
        // Create/update provisioning record with status 'pending'
        $provisioning = DeviceCloudProvisioning::firstOrNew([
            'device_uuid' => $params['device_uuid'],
            'domain_uuid' => $params['domain_uuid'],
        ]);
        $provisioning->provider = $params['device_vendor'];
        $provisioning->last_action = 'register';
        $provisioning->status = 'pending';
        $provisioning->error = null;
        $provisioning->save();

        $params['provisioning_uuid'] = $provisioning->uuid;

        return new RegisterDeviceWithCloudProvider($params);
    }

    public function deregister($params)
    {
        // Update provisioning record with status 'pending'
        $provisioning = DeviceCloudProvisioning::where(
            'device_uuid', $params['device_uuid'],
          )->first();
        if ($provisioning) {
            $provisioning->provider = $params['device_vendor'];
            $provisioning->last_action = 'deregister';
            $provisioning->status = 'pending';
            $provisioning->error = null;
            $provisioning->save();

            $params['provisioning_uuid'] = $provisioning->uuid;
    
            return new DeregisterDeviceWithCloudProvider($params);
        }

        return null;
    }

    // Deletes local cache from DB
    public function reset($params)
    {
        return new DeleteDeviceCloudProvisioningRecord($params);
    }
}
