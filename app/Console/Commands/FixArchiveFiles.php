<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\CDR;
use App\Models\Domain;
use Illuminate\Console\Command;


class FixArchiveFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'FixArchiveFiles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->uploadRecordings();
        return 0;
    }
    public function uploadRecordings()
    {
        //$start_date = date("Y-m-d", strtotime("-1 days"));
        //$recordings=$this->getCallRecordings($start_date);
        $recordings = $this->getCallRecordings();

        // logger($recordings);

        foreach ($recordings as $call_recording) {
            try {

                if ($call_recording->archive_recording) {
                    $call_recording->record_path = "S3";
                    $call_recording->record_name = $call_recording->archive_recording->object_key;
                    $call_recording->save();
                }
            } catch (\Exception $ex) {
                logger($ex->getMessage());

            }
        }


    }


    public function getDomainName($domain_id)
    {
        return Domain::where('domain_uuid', $domain_id)->first();
    }

    public function getCallRecordings()
    {

        // Get all calls that have call recordings
        $calls = CDR::select([
            'xml_cdr_uuid',
            'domain_uuid',
            'domain_name',
            'direction',
            'caller_id_number',
            'caller_destination',
            'start_stamp',
            'record_path',
            'record_name'
        ])

            // ->where('record_name', '<>', '')
            ->where('record_path', 'not like', '%S3%') // New where clause
            ->whereDate('start_stamp', '<=', Carbon::yesterday()->toDateTimeString())
            ->whereDate('start_stamp', '>=', Carbon::now()->subDays(10)->toDateTimeString())
            ->where('hangup_cause', '<>', 'LOSE_RACE')
            ->take(2000)
            // ->toSql();
            ->get();
        // Log::info($calls);
        // exit();
        return $calls;
    }
}
