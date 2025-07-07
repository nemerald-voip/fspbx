<?php

namespace App\Jobs;

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

    protected $params;

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        Redis::throttle('cloud-provider-jobs')->allow(1)->every(2)->then(function () {
            try {

                $provisioning = DeviceCloudProvisioning::find($this->params['provisioning_uuid']);

                $cloudProviderSelector = app()->make(CloudProviderSelector::class);
                $cloudProvider = $cloudProviderSelector->getCloudProvider($this->params['device_vendor']);

                if (!$cloudProvider) {
                    return false;
                }

                // Create device
                $result = $cloudProvider->createDevice($this->params);

                if ($result['success'] == true) {
                    $provisioning->status = 'success';
                    $provisioning->last_action = 'register';
                    $provisioning->error = null;
                    $provisioning->save();
                } else {
                    $provisioning->status = 'error';
                    $provisioning->last_action = 'register';
                    $provisioning->error = $result['error'];
                    $provisioning->save();
                }

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
