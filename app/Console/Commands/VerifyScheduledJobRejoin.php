<?php

namespace App\Console\Commands;

use App\Services\Ha\ActiveNodeResolver;
use Illuminate\Console\Command;
use Throwable;

class VerifyScheduledJobRejoin extends Command
{
    protected $signature = 'scheduled-jobs:verify-rejoin';
    protected $description = 'Verify signed node identity and replicated ownership before restarting returning workers';

    public function handle(ActiveNodeResolver $coordinator): int
    {
        try {
            $coordinator->verifyRejoin();
            $this->info('Approved identities, membership and ownership agree. Verify application-table replication health before restarting workers.');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
