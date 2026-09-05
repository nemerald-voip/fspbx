<?php

namespace App\Console\Commands;

use App\Services\Ha\ActiveNodeResolver;
use Illuminate\Console\Command;

class MaintainScheduledJobCoordination extends Command
{
    protected $signature = 'scheduled-jobs:maintain';
    protected $description = 'Finish pending scheduled-job transfers after work completes or authorization expires';

    public function handle(ActiveNodeResolver $coordinator): int
    {
        $coordinator->finalizePendingHandoff();

        return self::SUCCESS;
    }
}
