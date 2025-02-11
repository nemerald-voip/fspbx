<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Log;
use App\Services\RingotelApiService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;

class AuditStaleRingotelUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ringotelApi;

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
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->ringotelApi = new RingotelApiService();
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Allow only 1 tasks every 30 second
        Redis::throttle('default')->allow(1)->every(30)->then(function () {
            try {
                // Retrieve all required settings in a single query
                $settings = DefaultSettings::where('default_setting_category', 'scheduled_jobs')
                    ->whereIn('default_setting_subcategory', [
                        'stale_ringotel_users_threshold',
                        'ringotel_audit_notify_email'
                    ])
                    ->pluck('default_setting_value', 'default_setting_subcategory');

                // Assign values with defaults
                $staleThresholdDays = $settings['stale_ringotel_users_threshold'] ?? 180; // Default to 180 days
                $notifyEmail = $settings['ringotel_audit_notify_email'] ?? null;

                // Get stale users using the Ringotel API service
                $staleUsers = $this->ringotelApi->getStaleUsers($staleThresholdDays);

                // Send email notification if enabled
                if (!empty($staleUsers) && $notifyEmail) {
                    $params['user_email'] = $notifyEmail;
                    ExportReport::dispatch($params, $staleUsers);
                }
            } catch (\Exception $e) {
                return $this->release(30);
            }
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(30);
        });
    }

    /**
     * Notify the admin about stale users.
     */
    protected function notifyAdmin($notifyEmail, $staleUsers, $staleThreshold)
    {
        if (!filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
            Log::error("Invalid Ringotel notify email: {$notifyEmail}");
            return;
        }

        $userList = implode("\n", array_map(fn($user) => "{$user['name']} ({$user['email']}) - Last Active: {$user['last_active']}", $staleUsers));

        \Mail::raw(
            "Alert: " . count($staleUsers) . " Ringotel users have been inactive for over {$staleThreshold} minutes.\n\n" . $userList,
            function ($message) use ($notifyEmail) {
                $message->to($notifyEmail)
                    ->subject('Ringotel Stale Users Alert');
            }
        );

        Log::info("Admin notified via email: {$notifyEmail} - " . count($staleUsers) . " stale users detected.");
    }
}
