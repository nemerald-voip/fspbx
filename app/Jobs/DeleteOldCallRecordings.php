<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\CallRecordings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;

class DeleteOldCallRecordings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $daysKeepRecordings;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 300;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;


    /**
     * Create a new job instance.
     *
     * @param int $daysKeepRecordings Number of days to retain call recordings (older recordings will be deleted)
     */
    public function __construct(int $daysKeepRecordings = 90)
    {
        $this->daysKeepRecordings = $daysKeepRecordings;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Redis::throttle('fax')->allow(1)->every(60)->then(function () {

            $days = $this->daysKeepRecordings;
            $cutoffTimestamp = Carbon::now()->subDays($days)->timestamp;
            $cutoffDate = Carbon::now()->subDays($days);

            // Access the 'recordings' disk (root: /var/lib/freeswitch/recordings)
            $disk = Storage::disk('recordings');
            // Retrieve the absolute root path from the disk
            $basePath = $disk->path('');
            // Ensure the base path ends with a directory separator
            $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            $this->deleteOldRecordingFiles($basePath, $cutoffTimestamp);

            // Delete call recording records from the database via the CallRecordings model
            try {
                CallRecordings::where('call_recording_date', '<', $cutoffDate)->delete();
                // logger("Deleted call recording records older than {$days} days from CallRecordings.");
            } catch (\Exception $e) {
                logger("Error deleting call recording records from CallRecordings: " . $e->getMessage());
            }

        }, function () {
            return $this->release(30); // If locked, retry in 30 seconds
        });
    }

    protected function deleteOldRecordingFiles(string $basePath, int $cutoffTimestamp): void
    {
        $domainDirectories = glob($basePath . '*', GLOB_ONLYDIR) ?: [];

        foreach ($domainDirectories as $domainDirectory) {
            $archivePath = $domainDirectory . DIRECTORY_SEPARATOR . 'archive';

            if (! is_dir($archivePath)) {
                continue;
            }

            try {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($archivePath, RecursiveDirectoryIterator::SKIP_DOTS)
                );

                foreach ($files as $file) {
                    if (
                        ! $file->isFile()
                        || ! in_array(strtolower($file->getExtension()), ['wav', 'mp3'], true)
                        || $file->getMTime() >= $cutoffTimestamp
                    ) {
                        continue;
                    }

                    $this->deleteRecordingFile($file->getPathname());
                }
            } catch (UnexpectedValueException $e) {
                logger("Error scanning recording archive {$archivePath}: " . $e->getMessage());
            }
        }
    }

    protected function deleteRecordingFile(string $file): void
    {
        try {
            if (unlink($file)) {
                // logger("Deleted recording file: {$file}");
            } else {
                logger("Failed to delete recording file: {$file}");
            }
        } catch (\Exception $e) {
            logger("Error deleting recording file {$file}: " . $e->getMessage());
        }
    }
}
