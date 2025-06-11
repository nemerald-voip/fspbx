<?php

namespace App\Jobs;

use App\Models\MobileAppUsers;
use Illuminate\Bus\Queueable;
use App\Services\RingotelApiService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;

class SuspendAppUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $attributes;

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
    public function __construct($attributes)
    {
        $this->attributes = $attributes;
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
    public function handle(RingotelApiService $ringotelApiService)
    {
        // Allow only 2 tasks every 1 second
        Redis::throttle('ringotel')->allow(2)->every(1)->then(function () use ($ringotelApiService) {
            $result = $ringotelApiService->deactivateUser($this->attributes);

            $users = $ringotelApiService->getUsers($this->attributes['org_id'], $this->attributes['conn_id']);

            $user = collect($users)->firstWhere('username', $this->attributes['ext']);

            if ($user) {
                $mobile_app = MobileAppUsers::where('user_id', $this->attributes['user_id'])->first();
                $mobile_app->user_id = $user->id;
                $mobile_app->status = -1;
                $mobile_app->save();
            }

        }, function () {
            throw new \Exception('Could not obtain Redis lock for Ringotel throttling.');
        });
    }
}
