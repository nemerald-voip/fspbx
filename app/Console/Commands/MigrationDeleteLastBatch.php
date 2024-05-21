<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrationDeleteLastBatch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:delete-last-batch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete the last migration batch records from the migrations table without rolling back the migrations';

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
            $this->info('No migrations found for the last batch.');
        } else {
            $this->info("Last Migration Batch: $lastBatch");

            foreach ($migrations as $migration) {
                $this->info("Migration: {$migration->migration}, Batch: {$migration->batch}");
            }

            // Confirm deletion
            if ($this->confirm('Do you really wish to delete these migrations from database records?')) {
                // Delete the last batch migrations
                DB::table('migrations')
                    ->where('batch', $lastBatch)
                    ->delete();

                $this->info("Successfully deleted the last migration batch: $lastBatch");
            } else {
                $this->info('Operation cancelled. No migrations were deleted.');
            }
        }

        return 0;
    }
}

