<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SnoozeWakeupCallJob;

class SnoozeWakeupCall extends Command
{
    protected $signature = 'wakeup:snooze {uuid} {minutes}';
    protected $description = 'Snooze a wake-up call for a specified number of minutes';

    public function handle()
    {
        $uuid = $this->argument('uuid');
        $minutes = (int) $this->argument('minutes');

        // Dispatch the job to the queue
        SnoozeWakeupCallJob::dispatch($uuid, $minutes);

        $this->info("Wake-up call snooze job for $minutes minutes is queued.");
        return 0;
    }
}
