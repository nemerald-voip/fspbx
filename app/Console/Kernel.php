<?php

namespace App\Console;

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
        // $schedule->command('inspire')->hourly();
        $schedule->command('UploadArchiveFiles')
                ->dailyAt('01:00')->timezone('America/Los_Angeles');

        // you’d like to see graphs of how your queues are doing, run this
        $schedule->command('horizon:snapshot')->everyFiveMinutes();

        // Check Horizon status
        $schedule->command('horizon:check-status')->everyTenMinutes();

        // Delete Webhooks
        $schedule->command('model:prune', [
            '--model' => [WebhookCall::class],
        ])->daily();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected $commands = [
       Commands\UploadArchiveFiles::class,
    ];
    
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
