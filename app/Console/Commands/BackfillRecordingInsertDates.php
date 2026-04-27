<?php

namespace App\Console\Commands;

use App\Models\Recordings;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BackfillRecordingInsertDates extends Command
{
    protected $signature = 'recordings:backfill-insert-dates
        {--domain= : Only backfill recordings for this domain UUID}
        {--domain_uuid= : Alias for --domain}
        {--limit= : Maximum number of recordings to inspect}
        {--dry-run : Show what would be updated without writing to the database}';

    protected $description = 'Backfill missing recording insert_date values from local file timestamps';

    public function handle(): int
    {
        $query = Recordings::query()
            ->with('domain:domain_uuid,domain_name')
            ->whereNull('insert_date');

        if ($domainUuid = $this->option('domain') ?: $this->option('domain_uuid')) {
            $query->where('domain_uuid', $domainUuid);
        }

        $limit = $this->normalizedLimit();
        $dryRun = (bool) $this->option('dry-run');
        $inspected = 0;
        $updated = 0;
        $missing = 0;
        $skipped = 0;

        $this->info($dryRun
            ? 'Dry run: no recording rows will be updated.'
            : 'Backfilling missing recording insert_date values.'
        );

        $query->chunkById(100, function ($recordings) use (
            &$inspected,
            &$updated,
            &$missing,
            &$skipped,
            $limit,
            $dryRun
        ) {
            foreach ($recordings as $recording) {
                if ($limit !== null && $inspected >= $limit) {
                    return false;
                }

                $inspected++;

                $storagePath = $this->storagePathFor($recording);

                if ($storagePath === null) {
                    $skipped++;
                    $this->warn("Skipped {$recording->recording_uuid}: invalid or incomplete path data.");
                    continue;
                }

                if (! Storage::disk('recordings')->exists($storagePath)) {
                    $missing++;
                    $this->warn("Missing file for {$recording->recording_uuid}: {$storagePath}");
                    continue;
                }

                try {
                    $insertDate = $this->insertDateFromFile($recording->domain_uuid, $storagePath);
                } catch (Throwable $e) {
                    $skipped++;
                    report($e);
                    $this->warn("Skipped {$recording->recording_uuid}: {$e->getMessage()}");
                    continue;
                }

                $this->line(sprintf(
                    '%s %s -> %s (%s)',
                    $dryRun ? 'Would update' : 'Updating',
                    $recording->recording_uuid,
                    $insertDate->format('Y-m-d H:i:s P'),
                    $storagePath
                ));

                if (! $dryRun) {
                    Recordings::whereKey($recording->recording_uuid)
                        ->whereNull('insert_date')
                        ->update(['insert_date' => $insertDate->toIso8601String()]);
                }

                $updated++;
            }
        }, 'recording_uuid', 'recording_uuid');

        $action = $dryRun ? 'Would update' : 'Updated';

        $this->info("Inspected: {$inspected}; {$action}: {$updated}; Missing files: {$missing}; Skipped: {$skipped}.");

        return self::SUCCESS;
    }

    protected function normalizedLimit(): ?int
    {
        $limit = $this->option('limit');

        if ($limit === null || $limit === '') {
            return null;
        }

        $limit = (int) $limit;

        return $limit > 0 ? $limit : null;
    }

    protected function storagePathFor(Recordings $recording): ?string
    {
        $domainName = $recording->domain?->domain_name;
        $filename = str_replace('\\', '/', (string) $recording->recording_filename);

        if (! $domainName || $filename === '' || $this->hasUnsafePathSegments($filename)) {
            return null;
        }

        return trim($domainName, '/').'/'.ltrim($filename, '/');
    }

    protected function hasUnsafePathSegments(string $path): bool
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path)) {
            return true;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                return true;
            }
        }

        return false;
    }

    protected function insertDateFromFile(string $domainUuid, string $storagePath): Carbon
    {
        $timezone = get_local_time_zone($domainUuid) ?: config('app.timezone', 'UTC');

        return Carbon::createFromTimestamp(
            Storage::disk('recordings')->lastModified($storagePath),
            'UTC'
        )->setTimezone($timezone);
    }
}
