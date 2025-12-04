<?php

namespace App\Jobs;

use App\Models\Extensions;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Redis;
use App\Services\FreeswitchEslService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class FireFollowMePresenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
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
    public $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 30;

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

    public string $extensionId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $extensionId)
    {
        $this->extensionId = $extensionId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        // Allow only 2 tasks every 1 second
        Redis::throttle('freeswith')->allow(2)->every(1)->then(function () {

        $esl = new FreeswitchEslService();

        $ext = Extensions::with('domain')
            ->find($this->extensionId);

        if (! $ext || ! $ext->domain) {
            logger('FireFollowMePresenceJob: extension or domain not found for id '.$this->extensionId);
            return;
        }

        $domainName = $ext->domain->domain_name;
        $extension  = $ext->extension;

        // Normalize follow_me_enabled to bool
        $enabled = filter_var($ext->follow_me_enabled, FILTER_VALIDATE_BOOLEAN);

        $cmd = sprintf(
            "luarun lua/followme_notify.lua %s %s %s",
            escapeshellarg($extension),
            escapeshellarg($domainName),
            $enabled ? 'true' : 'false'
        );

        logger('FireFollowMePresenceJob sending ESL: '.$cmd);

        $esl->executeCommand($cmd); 

        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(15);
        });
    }
}
