<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use App\Models\VoicemailMessages;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeleteOldVoicemails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $daysKeepVoicemail;

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
     * @param int $daysKeepVoicemail Number of days to retain voicemails (older voicemails will be deleted)
     */
    public function __construct(int $daysKeepVoicemail = 90)
    {
        $this->daysKeepVoicemail = $daysKeepVoicemail;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Redis::throttle('fax')->allow(1)->every(60)->then(function () {

            try {
                $days = $this->daysKeepVoicemail;
                $cutoffTimestamp = Carbon::now()->subDays($days)->timestamp;

                // Access the 'voicemail' disk (root: /var/lib/freeswitch/storage/voicemail/default)
                $disk = Storage::disk('voicemail');
                // Retrieve the absolute path for the disk
                $basePath = $disk->path('');

                // Ensure the base path ends with a directory separator if needed
                $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

                $pattern = $basePath . 'msg_*.{wav,mp3}';
                $files = glob($pattern, GLOB_BRACE);

                foreach ($files as $file) {
                    if (filemtime($file) < $cutoffTimestamp) {
                        try {
                            if (unlink($file)) {
                                // logger("Deleted voicemail file: {$file}");
                            } else {
                                logger("Failed to delete voicemail file: {$file}");
                            }
                        } catch (\Exception $e) {
                            logger("Error deleting voicemail file {$file}: " . $e->getMessage());
                        }
                    }
                }

                // Delete voicemail message records using the VoicemailMessages model
                VoicemailMessages::where('created_epoch', '<', $cutoffTimestamp)->delete();
                // logger("Deleted voicemail messages older than {$days} days from VoicemailMessages.");
            } catch (\Exception $e) {
                logger("Error deleting voicemail messages: " . $e->getMessage());
            }

        }, function () {
            return $this->release(30); // If locked, retry in 30 seconds
        });
    }
}
