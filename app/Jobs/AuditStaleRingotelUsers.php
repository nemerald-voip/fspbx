<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
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
        // Allow only 2 tasks every 30 second
        Redis::throttle('default')->allow(1)->every(30)->then(function () {

            // Retrieve the stale user threshold from settings (in minutes)
            // $staleThreshold = DefaultSettings::where('default_setting_category', 'scheduled_jobs')
            //     ->where('default_setting_subcategory', 'ringotel_stale_user_threshold')
            //     ->value('default_setting_value') ?? 60;

            // // Retrieve notification email for stale users
            // $notifyEmail = DefaultSettings::where('default_setting_category', 'scheduled_jobs')
            //     ->where('default_setting_subcategory', 'ringotel_notify_email')
            //     ->value('default_setting_value') ?? null;

            // Fetch all organizations
            $organizations = $this->ringotelApi->getOrganizations();

            if (!$organizations || $organizations->isEmpty()) {
                logger("Failed to fetch organizations from Ringotel API.");
                return $this->release(30);
            }

            // Generate timestamps (milliseconds since epoch)
            $endTimestamp = now()->timestamp * 1000; // Current time in milliseconds
            $beginTimestamp = now()->subMonths(6)->timestamp * 1000; // 6 months ago in milliseconds


            // Loop through organizations and get users
            foreach ($organizations as $organization) {
                $orgId = $organization->id;
                $users = $this->ringotelApi->getUsersByOrgId($orgId);

                foreach ($users as $user) {
                    // Ignoring status "-1" for unactivated users and status "-2" for Parks
                    if ($user->status != -1 && $user->status != -2) {
                        logger($user->name);
                        $history = $this->ringotelApi->getUserRegistrationsHistory($orgId, $user->id, $beginTimestamp, $endTimestamp);
                        logger($history);
                    }
                }
            }

            // // Fetch users from Ringotel API
            // $users = $this->ringotelApi->getUsers();

            // if (!$users) {
            //     Log::error("Failed to fetch users from Ringotel API.");
            //     return;
            // }

            // $staleUsers = [];
            // $staleTime = now()->subMinutes($staleThreshold)->toIso8601String();

            // foreach ($users as $user) {
            //     if (!isset($user['last_active']) || Carbon::parse($user['last_active'])->toIso8601String() < $staleTime) {
            //         $staleUsers[] = $user;
            //     }
            // }

            // if (count($staleUsers) > 0) {
            //     Log::warning(count($staleUsers) . " stale Ringotel users detected. Last active beyond {$staleThreshold} minutes.");

            //     if ($notifyEmail) {
            //         $this->notifyAdmin($notifyEmail, $staleUsers, $staleThreshold);
            //     }
            // }
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
