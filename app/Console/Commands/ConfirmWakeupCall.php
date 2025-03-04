<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ConfirmWakeupCallJob;

class ConfirmWakeupCall extends Command
{
    protected $signature = 'wakeup:confirm {uuid}';
    protected $description = 'Confirm a wake-up call and update its status.';

    public function handle()
    {
        $uuid = $this->argument('uuid');

        // Dispatch the job to the queue
        ConfirmWakeupCallJob::dispatch($uuid);

        logger("Wake-up call confirmation job is queued for UUID: $uuid.");
        return 0;
    }
}
