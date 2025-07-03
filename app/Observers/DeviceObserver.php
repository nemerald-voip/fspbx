<?php

namespace App\Observers;

use App\Models\Devices;
use App\Services\CloudProviderSelector;
use App\Services\DeviceCloudProvisioningService;

class DeviceObserver
{
    /**
     * Handle the Devices "created" event.
     */
    public function created(Devices $device): void
    {
        try {
            $params = [
                'device_uuid' => $device->device_uuid,
                'domain_uuid' => $device->domain_uuid,
                'device_vendor' => $device->device_vendor,
                'device_address' => $device->device_address,
            ];

            $cloudProviderSelector = app()->make(CloudProviderSelector::class);
            $cloudProvider = $cloudProviderSelector->getCloudProvider($device->device_vendor);

            if ($cloudProvider) {
                $registerJob = (new DeviceCloudProvisioningService)->register($params);
                dispatch($registerJob);
            }
        } catch (\Throwable $e) {
            logger('DeviceObserver@created error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        }
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

            // Only proceed if either changed and device has cloud provisioning
            if (!($macChanged || $vendorChanged || !$device->cloudProvisioning)) {
                return;
            }

            $cloudProviderSelector = app()->make(CloudProviderSelector::class);
            $service = app(DeviceCloudProvisioningService::class);

            // Deregister (using original values)
            $deregisterJob = null;
            $origProvider = $cloudProviderSelector->getCloudProvider($original['device_vendor']);
            if ($origProvider) {
                $deregisterJob = $service->deregister([
                    'device_uuid' => $device->device_uuid,
                    'domain_uuid' => $device->domain_uuid,
                    'device_vendor' => $original['device_vendor'],
                    'device_address' => $original['device_address'],
                ]);
            }

            // Register (using updated values)
            $registerJob = null;
            $newProvider = $cloudProviderSelector->getCloudProvider($device->device_vendor);
            if ($newProvider) {
                $registerJob = $service->register([
                    'device_uuid' => $device->device_uuid,
                    'domain_uuid' => $device->domain_uuid,
                    'device_vendor' => $device->device_vendor,
                    'device_address' => $device->device_address,
                ]);
            }

            // Dispatch jobs in correct order
            if ($deregisterJob && $registerJob) {
                dispatch($deregisterJob->chain([$registerJob]));
            } elseif ($deregisterJob) {
                dispatch($deregisterJob);
            } elseif ($registerJob) {
                dispatch($registerJob);
            }
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
