<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SendHorizonStatusNotification;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class CheckHorizonStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'horizon:check-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Laravel Horizon status and send Slack notification if it changes';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Fetch the previous status from the cache
        $previousStatus = Cache::get('horizon_status', '');

        // Get the current status of Horizon.
        if (!$masters = app(MasterSupervisorRepository::class)->all()) {
            $currentStatus = 'inactive';
        }

        if (!isset($currentStatus)) {
            $currentStatus =  collect($masters)->every(function ($master) {
                return $master->status === 'paused';
            }) ? 'paused' : 'running';
        }

        if ($previousStatus !== $currentStatus) {
            // Status has changed, send Slack notification
            $request['slack_message'] = "*" . strtoupper(gethostname()) . " Horizon*: status has changed to " . $currentStatus;
            // SendSystemStatusNotificationToSlack::dispatch($message)->onQueue('slack');

            if (config('slack.system_status')) {
                Notification::route('slack', config('slack.system_status'))
                ->notify(new SendHorizonStatusNotification($request));
            }
            // Update the cached status
            Cache::put('horizon_status', $currentStatus);
        }
    }
}
