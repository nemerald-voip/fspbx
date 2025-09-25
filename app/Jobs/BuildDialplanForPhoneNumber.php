<?php

namespace App\Jobs;

use App\Models\Destinations;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use App\Services\DialplanBuilderService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class BuildDialplanForPhoneNumber implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $destinationUuid;
    private $domainName;

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
    public $backoff = 30;

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
    public function __construct($destinationUuid, $domainName)
    {
        $this->destinationUuid = $destinationUuid;
        $this->domainName = $domainName;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(DialplanBuilderService $builder)
    {
        // Allow only 2 tasks every 1 second
        Redis::throttle('dialplan')->allow(2)->every(1)->then(function () use ($builder) {

            $dest = Destinations::where('destination_uuid', $this->destinationUuid)->first();
            if ($dest) {
                $builder->buildDialplanForPhoneNumber($dest, $this->domainName);
            }


        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(15);
        });
    }
}
