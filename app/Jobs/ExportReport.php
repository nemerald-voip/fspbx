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


class ExportReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $params;
    protected $data;

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
    public function __construct($params, $data)
    {

        $this->params = $params;
        $this->data = $data;
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

            // Generate a unique filename
            $uniqueFilename = Str::uuid() . '.csv';

            // Define the path to the CSV file within the export directory
            $pathToCsv = Storage::disk('export')->path($uniqueFilename);

            $writer = SimpleExcelWriter::create($pathToCsv);

            $count = 0;

            foreach ($this->data as $item) {
                $row = [];

                // Dynamically create the row with keys as column names
                foreach ($item as $key => $value) {
                    // Convert camel case to snake case, then replace underscores with spaces and convert to title case
                    $formattedKey = Str::title(str_replace('_', ' ', Str::snake($key)));

                    $row[$formattedKey] = $value;
                }

                $writer->addRow($row);

                $count++;

                if ($count % 1000 === 0) {
                    flush(); // Flush the buffer every 1000 rows
                }
            }

            // Generate a public URL for the file
            $this->params['fileUrl'] = Storage::disk('export')->url($uniqueFilename);
            $this->params['email_subject'] = config('app.name', 'Laravel') . ' report';

            SendExportCompletedNotification::dispatch($this->params)->onQueue('emails');

            // Mail::to($this->params['user_email'])->send(new CdrExportCompleted($fileUrl));

        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(60);
        });
    }
}
