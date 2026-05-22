<?php

namespace App\Jobs;

use App\Models\FaxLogs;
use App\Models\FaxFiles;
use App\Models\FaxQueues;
use App\Models\OutboundFax;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeleteOldFaxes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $daysKeepFax;

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
     * @param int $daysKeepFax Number of days to keep faxes (older faxes and logs will be deleted)
     */
    public function __construct(int $daysKeepFax = 90)
    {
        $this->daysKeepFax = $daysKeepFax;
    }

    /**
     * Execute the job.
     */
    /**
     * How long an orphaned file in any temp/ directory is allowed to live
     * before this job removes it. A successful fax conversion finishes in
     * seconds; anything still around days later is from a failed run. The
     * window is intentionally generous so we keep enough history for
     * troubleshooting before sweeping.
     */
    private const TEMP_ORPHAN_KEEP_DAYS = 7;

    public function handle()
    {
        Redis::throttle('fax')->allow(1)->every(60)->then(function () {

            $days = $this->daysKeepFax;
            $cutoffTimestamp = Carbon::now()->subDays($days)->timestamp;
            $cutoffDate = Carbon::now()->subDays($days);
            $tempCutoffTimestamp = Carbon::now()->subDays(self::TEMP_ORPHAN_KEEP_DAYS)->timestamp;

            // Use the 'fax' disk defined in config/filesystems.php
            $disk = Storage::disk('fax');
            $files = $disk->allFiles();

            foreach ($files as $file) {
                // Files under /temp/ (disk root) or {domain}/{ext}/temp/ are
                // intermediates from the conversion pipeline. On success they
                // are unlinked explicitly; what's left is from a failed run.
                // Sweep them on a shorter clock and across all extensions —
                // .docx/.xls/.jpg orphans don't match the archive filter.
                if (preg_match('~(^|/)temp/~', $file)) {
                    $lastModified = $disk->lastModified($file);
                    if ($lastModified < $tempCutoffTimestamp) {
                        try {
                            $disk->delete($file);
                        } catch (\Exception $e) {
                            logger("Error deleting fax temp file {$file}: " . $e->getMessage());
                        }
                    }
                    continue;
                }

                // Archived sent/received faxes — keep only .tif/.pdf and
                // honour the per-tenant retention window.
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($extension, ['tif', 'pdf'])) {
                    $lastModified = $disk->lastModified($file);
                    if ($lastModified < $cutoffTimestamp) {
                        try {
                            $disk->delete($file);
                        } catch (\Exception $e) {
                            logger("Error deleting fax file {$file}: " . $e->getMessage());
                        }
                    }
                }
            }

            $oldOutboundFaxes = OutboundFax::query()
                ->select('outbound_fax_uuid')
                ->whereIn('status', ['sent', 'failed'])
                ->where('created_at', '<', $cutoffDate);

            $oldFaxFiles = FaxFiles::query()
                ->select('fax_file_uuid')
                ->where('fax_date', '<', $cutoffDate);

            $oldFaxLogs = FaxLogs::query()
                ->select('fax_log_uuid')
                ->where('fax_date', '<', $cutoffDate);

            $oldOutboundFaxLogs = FaxLogs::query()
                ->select('fax_log_uuid')
                ->whereIn('outbound_fax_uuid', clone $oldOutboundFaxes);

            // Delete inbound/sent fax file records from the FaxFiles model (v_fax_files table)
            // and files attached to outbound faxes being removed.
            try {
                FaxFiles::query()
                    ->where('fax_date', '<', $cutoffDate)
                    ->orWhereIn('fax_file_uuid', clone $oldFaxLogs)
                    ->orWhereIn('fax_file_uuid', clone $oldOutboundFaxLogs)
                    ->delete();
                // logger("Deleted fax records older than {$days} days from FaxFiles.");
            } catch (\Exception $e) {
                logger("Error deleting fax records from FaxFiles: " . $e->getMessage());
            }

            // Delete fax logs using the FaxLogs model (v_fax_logs table), including
            // logs associated with outbound faxes being removed.
            try {
                FaxLogs::query()
                    ->where('fax_date', '<', $cutoffDate)
                    ->orWhereIn('fax_log_uuid', clone $oldFaxFiles)
                    ->orWhereIn('outbound_fax_uuid', clone $oldOutboundFaxes)
                    ->delete();
                // logger("Deleted fax logs older than {$days} days from FaxLogs.");
            } catch (\Exception $e) {
                logger("Error deleting fax logs from FaxLogs: " . $e->getMessage());
            }

            // Delete completed outbound fax records using the OutboundFax model (outbound_faxes table)
            try {
                OutboundFax::whereIn('status', ['sent', 'failed'])
                    ->where('created_at', '<', $cutoffDate)
                    ->delete();
            } catch (\Exception $e) {
                logger("Error deleting outbound fax records from OutboundFax: " . $e->getMessage());
            }

            // Delete leftover legacy fax queue records. The table is no longer
            // used for active outbound faxing, but old rows may still exist.
            try {
                FaxQueues::where('fax_date', '<', $cutoffDate)->delete();
            } catch (\Exception $e) {
                logger("Error deleting legacy fax queue records from FaxQueues: " . $e->getMessage());
            }

            // logger("🔕 Wake-up call snoozed: {$this->uuid} until {$newAttemptTime->toDateTimeString()}");
        }, function () {
            return $this->release(30); // If locked, retry in 30 seconds
        });
    }
}
