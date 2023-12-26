<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\Faxes;
use Illuminate\Support\Facades\Redis;
use Spatie\WebhookClient\Models\WebhookCall;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessPostmarkWebhookJob extends SpatieProcessWebhookJob
{

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 15;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [(new RateLimitedWithRedis('fax'))];
    }

    public function __construct(WebhookCall $webhookCall)
    {
        $this->queue = 'faxes';
        $this->webhookCall = $webhookCall;
    }


    public function handle()
    {
        // $this->webhookCall // contains an instance of `WebhookCall`

        // Allow only 2 tasks every 1 second
        Redis::throttle('fax')->allow(2)->every(1)->then(function () {

            // perform the work here
            // Log::alert("----------Webhook Job starts-----------");
            // Log::alert($this->webhookCall->payload);

            $fax = new Faxes();
            $result = $fax->EmailToFax($this->webhookCall->payload);

            return "ok";


        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(5);
        });

    }
}

