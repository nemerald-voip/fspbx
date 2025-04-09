<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\EmergencyCallEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Mail\EmergencyCallNotificationEmail;

class NotifyEmergencyEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public EmergencyCallEmail $email;
    public string $caller;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

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
    public $backoff = 300;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;


    /**
     * Create a new job instance.
     */
    public function __construct(EmergencyCallEmail $email, string $caller)
    {
        $this->email = $email;
        $this->caller = $caller;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {

        // Allow only 2 tasks every 1 second
        Redis::throttle('email')->allow(2)->every(1)->then(function () {

            // Send email notification to user that the export has been completed
            $params['caller'] = $this->caller;
            $params['email_subject'] = 'Emergency Call Notification (Extension ' . $this->caller . ')';
            Mail::to($this->email->email)->send(new EmergencyCallNotificationEmail($params));
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(10);
        });

    }
}
