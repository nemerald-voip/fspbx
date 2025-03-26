<?php

namespace App\Jobs;

use App\Models\FaxQueues;
use Illuminate\Bus\Queueable;
use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use App\Jobs\SendFaxQueueThresholdExceededNotification;

class CheckFaxServiceStatus implements ShouldQueue
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
    public $backoff = 300;

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
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Allow only 2 tasks every 30 second
        Redis::throttle('default')->allow(1)->every(30)->then(function () {

            // Retrieve the fax service settings
            $threshold = DefaultSettings::where('default_setting_category', 'scheduled_jobs')
                ->where('default_setting_subcategory', 'fax_service_threshold')
                ->value('default_setting_value') ?? 10;

            $waitTimeThreshold = DefaultSettings::where('default_setting_category', 'scheduled_jobs')
                ->where('default_setting_subcategory', 'fax_wait_time_threshold')
                ->value('default_setting_value') ?? 30;

            // Retrieve notification email
            $notifyEmail = DefaultSettings::where('default_setting_category', 'scheduled_jobs')
                ->where('default_setting_subcategory', 'fax_service_notify_email')
                ->value('default_setting_value') ?? null;

            // Calculate the time threshold
            $timeThreshold = now()->subMinutes($waitTimeThreshold)->toIso8601String();

            // Get pending faxes that exceed the wait time threshold
            $pendingFaxes = FaxQueues::where('fax_status', 'waiting')
                ->where('fax_date', '<', $timeThreshold)
                ->count();

            // logger('Threshold - ' . $threshold);
            // logger('waitTimeThreshold - ' . $waitTimeThreshold);
            // logger('timeThreshold - ' . $timeThreshold);
            // logger('pendingFaxes - ' . $pendingFaxes);
            
            if ($pendingFaxes >= $threshold) {
                logger("Fax service alert: {$pendingFaxes} faxes have been pending for longer than {$waitTimeThreshold} minutes. Check fax queue service status");

                if ($notifyEmail) {
                    $params['notifyEmail'] = $notifyEmail;
                    $params['pendingFaxes'] = $pendingFaxes;
                    $params['waitTimeThreshold'] = $waitTimeThreshold;
                    $params['email_subject'] = config('app.name', 'Laravel') . ' fax service alert';

                    $this->notifyAdmin($params);
                }
            }

            // Get last $threshold faxes and check failure rate
            $recentFaxes = FaxQueues::orderBy('fax_date', 'desc')
                ->take($threshold)
                ->pluck('fax_status');

            if ($recentFaxes->count() > 0) {
                $failedCount = $recentFaxes->filter(fn($status) => $status === 'failed')->count();
                $failureRate = ($failedCount / $recentFaxes->count()) * 100;

                if ($failureRate >= 80) {
                    logger("Fax failure alert: {$failedCount} out of {$recentFaxes->count()} faxes have failed.");

                    if ($notifyEmail) {
                        $this->notifyAdmin([
                            'notifyEmail' => $notifyEmail,
                            'failedFaxes' => $failedCount,
                            'totalChecked' => $recentFaxes->count(),
                            'failureRate' => $failureRate,
                            'email_subject' => config('app.name', 'Laravel') . ' fax failure alert'
                        ]);
                    }
                }
            }

        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(30);
        });
    }

    /**
     * Notify the admin
     */
    protected function notifyAdmin($params)
    {
        SendFaxQueueThresholdExceededNotification::dispatch($params)->onQueue('emails');

    }
}
