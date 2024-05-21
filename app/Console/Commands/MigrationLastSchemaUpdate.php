<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrationLastSchemaUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migration:show-last-batch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Output the last migration batch information';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get the last batch number
        $lastBatch = DB::table('migrations')->max('batch');

        // Get the migrations for the last batch
        $migrations = DB::table('migrations')
            ->where('batch', $lastBatch)
            ->orderBy('migration')
            ->get();

        // Display the batch information
        if ($migrations->isEmpty()) {
            $this->info('No migrations found.');
        } else {
            $this->info("Last Migration Batch: $lastBatch");

            foreach ($migrations as $migration) {
                $this->info("Migration: {$migration->migration}, Batch: {$migration->batch}");
            }
        }

        return 0;
    }
}

