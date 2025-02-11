<?php

namespace App\Console;

use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\WebhookClient\Models\WebhookCall;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Cache the job settings for 2 minutes (120 seconds)
        $jobSettings = Cache::remember('scheduled_jobs_settings', 120, function () {
            return DefaultSettings::where('default_setting_category', 'scheduled_jobs')
                ->where('default_setting_enabled', true)
                ->pluck('default_setting_value', 'default_setting_subcategory')
                ->toArray();
        });

        // Schedule jobs based on the retrieved settings

        // Upload call recordings to AWS
        if (isset($jobSettings['aws_upload_calls_' . $this->getMacAddress()]) && $jobSettings['aws_upload_calls_' . $this->getMacAddress()] === "true") {
            $schedule->command('UploadArchiveFiles')
                ->dailyAt('01:00')
                ->timezone('America/Los_Angeles');
        }

        // Clear the export directory
        if (isset($jobSettings['clear_export_directory']) && $jobSettings['clear_export_directory'] === "true") {
            $schedule->command('storage:clear-export-directory')->daily();
        }

        // Horizon snapshot
        if (isset($jobSettings['horizon_snapshot']) && $jobSettings['horizon_snapshot'] === "true") {
            $schedule->command('horizon:snapshot')->everyFiveMinutes();
        }

        // Horizon check status
        if (isset($jobSettings['horizon_check_status']) && $jobSettings['horizon_check_status'] === "true") {
            $schedule->command('horizon:check-status')->everyTenMinutes();
        }

        // Clear Redis cache
        if (isset($jobSettings['cache_prune_stale_tags']) && $jobSettings['cache_prune_stale_tags'] === "true") {
            $schedule->command('cache:prune-stale-tags')->hourly();
        }

        // Delete Webhooks
        if (isset($jobSettings['prune_old_webhook_requests']) && $jobSettings['prune_old_webhook_requests'] === "true") {
            $schedule->command('model:prune', [
                '--model' => [WebhookCall::class],
            ])->daily();
        }

        if (isset($jobSettings['backup']) && $jobSettings['backup'] === "true") {
            // Schedule the backup command to run at 2 AM daily
            $schedule->command('app:backup')
                ->dailyAt('02:00')
                ->timezone('America/Los_Angeles');
        }

        // Check fax service status
        if (isset($jobSettings['check_fax_service_status']) && $jobSettings['check_fax_service_status'] === "true") {
            $schedule->job(new \App\Jobs\CheckFaxServiceStatus())->everyThirtyMinutes();
        }

        // Find stale Ringotel users
        if (isset($jobSettings['audit_stale_ringotel_users']) && $jobSettings['audit_stale_ringotel_users'] === "true") {
            $schedule->job(new \App\Jobs\AuditStaleRingotelUsers())->monthlyOn(1, '00:00');
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected $commands = [
        Commands\UploadArchiveFiles::class,
        Commands\MigrationShowLastBatch::class,
        Commands\MigrationDeleteLastBatch::class,
        Commands\ClearExportDirectory::class,
        Commands\VersionSetCommand::class,
    ];

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }

    public function getMacAddress()
    {
        // Run the shell command using Process
        $process = Process::run("ip link show | grep 'link/ether' | awk '{print $2}' | head -n 1");

        // Get the output from the process
        $macAddress = trim($process->output());

        return $macAddress ?: null;
    }
}
