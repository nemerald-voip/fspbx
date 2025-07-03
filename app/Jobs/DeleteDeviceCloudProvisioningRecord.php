<?php

namespace App\Jobs;

use App\Models\Devices;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Redis;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeleteDeviceCloudProvisioningRecord implements ShouldQueue
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
                $provisioning = QueryBuilder::for(\App\Models\DeviceCloudProvisioning::class)
                    ->where('device_uuid', $this->params['device_uuid'])
                    ->where('domain_uuid', $this->params['domain_uuid'])
                    ->first();

                if (!$provisioning) {
                    return false;
                }

                $provisioning->delete();

                return true;
            } catch (\Throwable $e) {
                logger("RegisterDeviceWithCloudProvider@handle Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                return false;
            }
        }, function () {
            return $this->release(30);
        });
    }
}
