<?php

namespace App\Jobs;


use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Mail\CdrExportCompleted;
use App\Services\CdrDataService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\SendExportCompletedNotification;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;


class ExportCdrs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $params;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

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
    public $timeout = 600;

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
    public $backoff = 15;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($params, protected CdrDataService $cdrDataService)
    {

        $this->params = $params;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [(new RateLimitedWithRedis('default'))];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Allow only 1 job every 60 second
        Redis::throttle('default')->allow(1)->every(30)->then(function () {

            $cdrs = $this->cdrDataService->getData($this->params);

            // Generate a unique filename
            $uniqueFilename = Str::uuid() . '.csv';

            // Define the path to the CSV file within the export directory
            $pathToCsv = Storage::disk('export')->path($uniqueFilename);

            $writer = SimpleExcelWriter::create($pathToCsv);

            $count = 0;

            foreach ($cdrs as $cdr) {
                $writer->addRow([
                    'ID' => $cdr['xml_cdr_uuid'],
                    'Direction' => $cdr['direction'],
                    'Caller ID Name' => $cdr['caller_id_name'],
                    'Caller ID Number' => $cdr['caller_id_number_formatted'],
                    'Dialed Number' => $cdr['caller_destination_formatted'],
                    'Recipient' => $cdr['destination_number_formatted'],
                    'Date' => $cdr['start_date'],
                    'Time' => $cdr['start_time'],
                    'Duration' => $cdr['duration_formatted'],
                    'Status' => $cdr['status'],
                ]);

                $count++;

                if ($count % 1000 === 0) {
                    flush(); // Flush the buffer every 1000 rows
                }
            }

            // Generate a public URL for the file
            $this->params['fileUrl'] = Storage::disk('export')->url($uniqueFilename);

            SendExportCompletedNotification::dispatch($this->params)->onQueue('emails');

            // Mail::to($this->params['user_email'])->send(new CdrExportCompleted($fileUrl));

        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(60);
        });
    }
}
