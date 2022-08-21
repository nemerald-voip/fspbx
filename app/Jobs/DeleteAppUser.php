<?php

namespace App\Jobs;

use App\Models\MobileAppUsers;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;

class DeleteAppUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $mobile_app;

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
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($mobile_app)
    {
        $this->mobile_app = $mobile_app;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [(new RateLimitedWithRedis('email'))];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Allow only 2 tasks every 1 second
        Redis::throttle('ringotel')->allow(2)->every(1)->then(function () {

            // Log::info("scheduled deleting app");
            //If there is no app then just return
            if(!isset($this->mobile_app)) return;

            // Send request to delеte user
            $response = appsDeleteUser($this->mobile_app['org_id'], $this->mobile_app['user_id']);

            //If there is an error return failed status and requeue the job
            if (isset($response['error'])) {
                return $this->release(5);
            } elseif (!isset($response['result'])) {
                return $this->release(5);
            }

            // Delete app info from database
            $appUser = MobileAppUsers::where('mobile_app_user_uuid', $this->mobile_app->mobile_app_user_uuid);
            if ($appUser) $appUser->delete();


        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(5);
        });

    }
}
