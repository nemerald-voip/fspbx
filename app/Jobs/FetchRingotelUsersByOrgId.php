<?php

namespace App\Jobs;


use Illuminate\Bus\Queueable;
use App\Services\RingotelApiService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;


class FetchRingotelUsersByOrgId implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $params;
    protected $data;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

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
    public $timeout = 60;

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
    public function __construct($params, $data)
    {

        $this->params = $params;
        $this->data = $data;
    }

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
     * Execute the job.
     *
     * @return void
     */
    public function handle(RingotelApiService $ringotelApiService)
    {
        // Allow only 2 job every 10 second
        Redis::throttle('default')->allow(2)->every(10)->then(function () use ($ringotelApiService) {

            try {
                $users = $ringotelApiService->getUsersByOrgId($orgId);
 
                logger($users);

                // Pass the fetched data to the next job in the chain
                $this->params['app_data'] = $organizations;

                ExportReport::dispatch($this->params, $this->data);
                
            } catch (\Exception $e) {
                logger($e->getMessage());
                return response()->json([
                    'error' => [
                        'message' => $e->getMessage(),
                    ],
                ], 400);
            }
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(30);
        });
    }
}
