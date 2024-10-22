<?php

namespace App\Listeners;

use Illuminate\Bus\Queueable;
use App\Events\GreetingDeleted;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use App\Models\IvrMenus;

class NotifyModelsOnGreetingDeleted implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
    public $timeout = 600;

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
        return [(new RateLimitedWithRedis('default'))];
    }


    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(GreetingDeleted $event): void
    {
        // Allow only 2 tasks every 1 second
        Redis::throttle('system')->allow(2)->every(1)->then(function () use ($event) {
            // Notify other models or take actions here
            // Find all matching IVR menus by domain_uuid and ivr_menu_greet_long
            $matchingIvrMenus = IvrMenus::where('domain_uuid', $event->domain_uuid)
                ->where('ivr_menu_greet_long', $event->file_name)
                ->get();

            if ($matchingIvrMenus->isEmpty()) {
                return;
            }

            // Erase the ivr_menu_greet_long value for each matching menu
            foreach ($matchingIvrMenus as $ivrMenu) {
                $ivrMenu->ivr_menu_greet_long = null;  // Erase the value
                $ivrMenu->save();  // Save changes to the database
            }
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(15);
        });
    }
}
