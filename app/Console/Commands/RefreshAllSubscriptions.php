<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshAllSubscriptions extends Command
{
    protected $signature = 'db:refresh-subscriptions
        {--connection= : DB connection name (defaults to database.default)}
        {--like= : Only refresh subscriptions whose names ILIKE this pattern (e.g. sub_%)} 
        {--except= : Comma-separated list of subscription names to skip}
        {--dry-run : Show what would run without executing}';

    protected $description = 'Discover and refresh all PostgreSQL logical replication subscriptions on the given connection.';

    public function handle(): int
    {
        $connection = $this->option('connection') ?: config('database.default');
        $like       = $this->option('like');
        $exceptList = array_filter(array_map('trim', explode(',', (string)$this->option('except'))));
        $dryRun     = (bool) $this->option('dry-run');

        try {
            $this->info("Querying subscriptions on connection: {$connection}");

            // Discover subscriptions on this server
            $sql = 'SELECT subname FROM pg_subscription';
            $bindings = [];

            if ($like) {
                $sql .= ' WHERE subname ILIKE ?';
                $bindings[] = $like;
            }

            $rows = DB::connection($connection)->select($sql, $bindings);
            $subs = collect($rows)->pluck('subname')->values();

            if ($subs->isEmpty()) {
                $this->warn('No subscriptions found.');
                return self::SUCCESS;
            }

            if (!empty($exceptList)) {
                $subs = $subs->reject(fn ($s) => in_array($s, $exceptList, true))->values();
            }

            if ($subs->isEmpty()) {
                $this->warn('No subscriptions left to refresh after filters.');
                return self::SUCCESS;
            }

            $this->line('Subscriptions to refresh:');
            foreach ($subs as $s) {
                $this->line("  - {$s}");
            }

            if ($dryRun) {
                $this->comment('Dry run: no statements executed.');
                return self::SUCCESS;
            }

            // Refresh each subscription
            foreach ($subs as $sub) {
                $stmt = sprintf('ALTER SUBSCRIPTION %s REFRESH PUBLICATION;', $this->quoteIdent($sub));
                $this->line("Executing: {$stmt}");
                DB::connection($connection)->statement($stmt);
                $this->info("Refreshed: {$sub}");
            }

            $this->info('All done.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Safely quote an identifier for PostgreSQL (simple local helper).
     * Doubles internal quotes and wraps with ".
     */
    private function quoteIdent(string $ident): string
    {
        return '"' . str_replace('"', '""', $ident) . '"';
    }
}
