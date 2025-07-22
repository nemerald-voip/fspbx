<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\CDR;
use App\Models\Domain;
use App\Models\DefaultSettings;
use Illuminate\Console\Command;
use App\Jobs\SendS3UploadReport;
use App\Models\ArchiveRecording;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
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
        $recordings = $this->getCallRecordings();
        $failed = [];
        $success = [];
    
        $domainUuids = $recordings->pluck('domain_uuid')->unique()->values();
        $requiredKeys = ['access_key', 'bucket_name', 'region', 'secret_key'];
    
        $domainSettingsRows = \App\Models\DomainSettings::whereIn('domain_uuid', $domainUuids)
            ->where('domain_setting_category', 'aws')
            ->whereIn('domain_setting_subcategory', $requiredKeys)
            ->where('domain_setting_enabled', true)
            ->get();
    
        $domainSettingsMap = [];
        foreach ($domainSettingsRows->groupBy('domain_uuid') as $domainUuid => $settings) {
            $flat = [];
            foreach ($settings as $row) {
                $flat[$row->domain_setting_subcategory] = $row->domain_setting_value;
            }
            if (count(array_intersect(array_keys($flat), $requiredKeys)) === count($requiredKeys)) {
                $domainSettingsMap[$domainUuid] = [
                    'key'    => $flat['access_key'],
                    'bucket' => $flat['bucket_name'],
                    'region' => $flat['region'],
                    'secret' => $flat['secret_key'],
                    'type'   => 'custom',
                ];
            }
        }
    
        $defaultRows = \App\Models\DefaultSettings::where('default_setting_category', 'aws')
            ->whereIn('default_setting_subcategory', $requiredKeys)
            ->where('default_setting_enabled', true)
            ->get();
    
        $defaultFlat = [];
        foreach ($defaultRows as $row) {
            $defaultFlat[$row->default_setting_subcategory] = $row->default_setting_value;
        }
        $defaultSettings = (count(array_intersect(array_keys($defaultFlat), $requiredKeys)) === count($requiredKeys))
            ? [
                'key'    => $defaultFlat['access_key'],
                'bucket' => $defaultFlat['bucket_name'],
                'region' => $defaultFlat['region'],
                'secret' => $defaultFlat['secret_key'],
                'type'   => 'default',
            ]
            : null;
    
        $finalSettingsByDomain = [];
        foreach ($domainUuids as $domainUuid) {
            if (!empty($domainSettingsMap[$domainUuid])) {
                $finalSettingsByDomain[$domainUuid] = $domainSettingsMap[$domainUuid];
            } elseif ($defaultSettings) {
                $finalSettingsByDomain[$domainUuid] = $defaultSettings;
            }
        }
    
        date_default_timezone_set('America/Los_Angeles');
        $s3Clients = [];
    
        foreach ($recordings as $rec) {
            $settings = $finalSettingsByDomain[$rec->domain_uuid] ?? null;
            if (!$settings) continue;
    
            // Cache S3 client per settings hash
            $clientKey = md5(json_encode($settings));
            if (!isset($s3Clients[$clientKey])) {
                $s3Clients[$clientKey] = new \Aws\S3\S3Client([
                    'region'      => $settings['region'],
                    'version'     => 'latest',
                    'credentials' => [
                        'key'    => $settings['key'],
                        'secret' => $settings['secret'],
                    ],
                ]);
            }
            $s3 = $s3Clients[$clientKey];
    
            $recordingFile = $rec->record_path . '/' . $rec->record_name;
            $mp3File = null;
    
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
                    $process->mustRun();
                } catch (ProcessFailedException $e) {
                    logger($e->getMessage());
                    $mp3File = null;
                }
            }
    
            try {
                if ($mp3File && file_exists($mp3File)) {
                    Log::info("Uploading File : " . $mp3File);
    
                    if ($settings['type'] == "default") {
                        $location = $rec->domain_name . '/' . date('Y', strtotime($rec->start_stamp)) . '/' . date('m', strtotime($rec->start_stamp)) . '/' . date('d', strtotime($rec->start_stamp)) . '/';
                    } else {
                        $location = 'recordings/' . date('Y', strtotime($rec->start_stamp)) . '/' . date('m', strtotime($rec->start_stamp)) . '/' . date('d', strtotime($rec->start_stamp)) . '/';
                    }
                    $file_ext = pathinfo($mp3File, PATHINFO_EXTENSION);
    
                    $object_key = $location . date('His', strtotime($rec->start_stamp)) . '_' . $rec->direction . '_' . $rec->caller_id_number . '_' . $rec->caller_destination . '.' . $file_ext;
                    $path = $s3->putObject([
                        'Bucket'     => $settings['bucket'],
                        'SourceFile' => $mp3File,
                        'Key'        => $object_key
                    ]);
    
                    $rec->record_path = "S3";
                    $rec->record_name = $object_key;
                    $rec->save();
    
                    unlink($mp3File);
                    // delete the original WAV as well:
                    unlink($recordingFile);
    
                    $success[] = $rec->record_name;
                }
            } catch (\Exception $ex) {
                if (!empty($rec->record_name)) {
                    logger($ex->getMessage());
                    $failed[] = ['msg' => $ex->getMessage(), 'name' => $rec->record_name];
                }
            }
        }
    
        // Send email report with upload status
        $upload_notification_email = DefaultSettings::where('default_setting_category', 'aws')
            ->where('default_setting_subcategory', 'upload_notification_email')
            ->value('default_setting_value');
        $attributes = [
            'email'   => $upload_notification_email,
            'failed'  => $failed,
            'success' => $success,
        ];
        SendS3UploadReport::dispatch($attributes)->onQueue('emails');
    }
    


    public function getDomainName($domain_id)
    {
        return Domain::where('domain_uuid', $domain_id)->first();
    }

    public function getCallRecordings()
{
    return CDR::select([
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
        ->whereNotNull('record_name')
        ->where('record_name', '<>', '')
        ->where('record_path', 'not like', '%S3%')
        ->where('record_path', 'not like', '%NFS%')
        ->where('hangup_cause', '<>', 'LOSE_RACE')
        ->whereDate('start_stamp', '<=', Carbon::today()->toDateTimeString())
        ->orderBy('start_stamp', 'desc') // Or whatever order makes sense
        ->take(2000)
        ->get();
}


}
