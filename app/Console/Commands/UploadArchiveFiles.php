<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\CDR;
use App\Models\Domain;
use App\Models\DomainSettings;
use App\Models\DefaultSettings;
use Illuminate\Console\Command;
use App\Jobs\SendS3UploadReport;
use App\Models\ArchiveRecording;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;

class UploadArchiveFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UploadArchiveFiles';

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

        $failed = [];
        $success = [];
        foreach ($recordings as $key => $call_recording) {
            $setting = getS3Setting($call_recording->domain_uuid);

            $s3 = new \Aws\S3\S3Client([
                'region'  => $setting['region'],
                'version' => 'latest',
                'credentials' => [
                    'key'    => $setting['key'],
                    'secret' => $setting['secret']
                ]
            ]);

            // Attempt to convert original file to MP3 for compression
            $recordingFile = $call_recording->record_path . '/' . $call_recording->record_name;
            if (file_exists($recordingFile) && pathinfo($recordingFile, PATHINFO_EXTENSION) === 'wav') {
                $mp3File = str_replace('.wav', '.mp3', $recordingFile);
                $process = new Process([
                    'ffmpeg',
                    '-i', $recordingFile,
                    '-b:a', '16k',
                    '-ac', '1',
                    '-q:a', '5',
                    $mp3File,
                ]);

                try {
                    // Run the FFmpeg command
                    $process->mustRun();

                    logger($mp3File);

                } catch (ProcessFailedException $e) {
                    logger($e->getMessage());
                }
            }


            try {

                // Upload Original File
                // This needs to be removed after confirmation that mp3 files are workign ok
                if (file_exists($recordingFile)) {
                    Log::info("Uploading File : " . $recordingFile);

                    // Set default time zone for this script
                    date_default_timezone_set('America/Los_Angeles');

                    //S3 file location
                    if ($setting['type'] == "default") {
                        $location = $call_recording->domain_name . '/' . date('Y', strtotime($call_recording->start_stamp)) . '/' . date('m', strtotime($call_recording->start_stamp)) . '/' . date('d', strtotime($call_recording->start_stamp)) . '/';
                    } elseif ($setting['type'] == "custom") {
                        $location = 'recordings' . '/' . date('Y', strtotime($call_recording->start_stamp)) . '/' . date('m', strtotime($call_recording->start_stamp)) . '/' . date('d', strtotime($call_recording->start_stamp)) . '/';
                    }

                    //S3 Object name
                    $file_ext = pathinfo($recordingFile, PATHINFO_EXTENSION);

                    $object_key = $location . date('His', strtotime($call_recording->start_stamp)) . '_' . $call_recording->direction . '_' . $call_recording->caller_id_number . '_' . $call_recording->caller_destination . '.' . $file_ext;
                    $path = $s3->putObject(array(
                        'Bucket'     => $setting['bucket'],
                        'SourceFile' => $recordingFile,
                        'Key'        => $object_key
                    ));

                    //Add uploaded recording to the database    
                    $archive = new ArchiveRecording();
                    $archive->domain_uuid = $call_recording->domain_uuid;
                    $archive->s3_path = $path['ObjectURL'];
                    $archive->object_key = $object_key;
                    $call_recording->archive_recording()->save($archive);

                    unlink($recordingFile);

                    array_push($success, $call_recording->record_name);
                } else {
                    // If file doesn't exist empty the database record file name
                    Log::info("File doesn't exist: " . $call_recording->record_name);
                    $cdr = CDR::find($call_recording->xml_cdr_uuid);
                    $call_recording->record_name = '';
                    $cdr->update([
                        'record_name' => $call_recording->record_name
                    ]);
                }

                // Uploading mp3 file 
                if (file_exists($mp3File)) {
                    Log::info("Uploading File : " . $mp3File);

                    // Set default time zone for this script
                    date_default_timezone_set('America/Los_Angeles');

                    //S3 file location
                    if ($setting['type'] == "default") {
                        $location = $call_recording->domain_name . '/' . date('Y', strtotime($call_recording->start_stamp)) . '/' . date('m', strtotime($call_recording->start_stamp)) . '/' . date('d', strtotime($call_recording->start_stamp)) . '/';
                    } elseif ($setting['type'] == "custom") {
                        $location = 'recordings' . '/' . date('Y', strtotime($call_recording->start_stamp)) . '/' . date('m', strtotime($call_recording->start_stamp)) . '/' . date('d', strtotime($call_recording->start_stamp)) . '/';
                    }

                    //S3 Object name
                    $file_ext = pathinfo($mp3File, PATHINFO_EXTENSION);

                    $object_key = $location . date('His', strtotime($call_recording->start_stamp)) . '_' . $call_recording->direction . '_' . $call_recording->caller_id_number . '_' . $call_recording->caller_destination . '.' . $file_ext;
                    $path = $s3->putObject(array(
                        'Bucket'     => $setting['bucket'],
                        'SourceFile' => $mp3File,
                        'Key'        => $object_key
                    ));
                }
            } catch (\Exception $ex) {
                if (!empty($call_recording->record_name)) {

                    array_push($failed, ['msg' => $ex->getMessage(), 'name' => $call_recording->record_name]);
                }
            }
        }
        //    if(!empty($failed)){
        // Send email report with upload status
        $upload_notification_email = DefaultSettings::where('default_setting_category', 'aws')
            ->where('default_setting_subcategory', 'upload_notification_email')
            ->value('default_setting_value');
        // Log::info($upload_notification_email);
        $attributes['email'] = $upload_notification_email;
        $attributes['failed'] = $failed;
        $attributes['success'] = $success;
        SendS3UploadReport::dispatch($attributes)->onQueue('emails');

        // $this->sendFailEmail($failed,$success);
        // }

    }
    public function sendFailEmail($failed, $success)
    {
        $view = view('emails.failed_upload')->with(['failed' => $failed, 'success' => $success]);
        // sergei@nemerald.com
        // sergei@nemerald.com
        $emails = ['sergei@nemerald.com'];
        foreach ($emails as $email) {
            $mail = sendEmail(array('email_layout' => 'emails/content', 'content' => $view, 'subject' => 'Completed - failed uploads.', 'user' => (object) array('name' => '', 'email' => $email)));
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

            ->where('record_name', '<>', '')
            ->whereDate('start_stamp', '<=', Carbon::today()->toDateTimeString())
            ->where('hangup_cause', '<>', 'LOSE_RACE')
            ->take(2000)
            // ->toSql();
            ->get();
        // Log::info($calls);
        // exit();
        return $calls;
    }
}
