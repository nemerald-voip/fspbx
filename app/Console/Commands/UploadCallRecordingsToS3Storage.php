<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\CDR;
use App\Models\Domain;
use App\Models\DefaultSettings;
use App\Models\DomainSettings;
use Illuminate\Console\Command;
use App\Jobs\SendS3UploadReport;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class UploadCallRecordingsToS3Storage extends Command
{
    protected $signature = 'fs:upload-call-recordings-to-s3-storage';

    protected $description = 'Upload archived call recordings to S3-compatible object storage';

    public function __construct(
        protected \App\Services\S3StorageConfigService $s3StorageConfigService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $this->uploadRecordings();

        return 0;
    }

    public function uploadRecordings()
    {
        $limit = $this->getUploadLimit();

        $recordingIds = $this->getCallRecordingIds($limit);

        if (empty($recordingIds)) {
            $this->info('No recordings found for upload.');
            return;
        }

        $failed = [];
        $success = [];

        $domainUuids = CDR::whereIn('xml_cdr_uuid', $recordingIds)
            ->distinct()
            ->pluck('domain_uuid')
            ->filter()
            ->values()
            ->all();

        $storageSettings = $this->s3StorageConfigService->getSettingsMapForDomains($domainUuids);
        $defaultSettings = $storageSettings['default'];
        $domainOverrides = $storageSettings['domains'];
        $timeZonesByDomain = $this->getTimeZonesByDomain($domainUuids);

        $s3Clients = [];

        $this->processRecordingsInChunks($recordingIds, function ($rec) use (
            &$failed,
            &$success,
            &$s3Clients,
            $defaultSettings,
            $domainOverrides,
            $timeZonesByDomain
        ) {
            $settings = $domainOverrides[$rec->domain_uuid] ?? $defaultSettings;

            if (!$settings) {
                $failed[] = [
                    'msg' => 'No s3_storage settings found for domain.',
                    'name' => $rec->record_name,
                ];
                return;
            }

            $timeZone = $timeZonesByDomain['domains'][$rec->domain_uuid] ?? $timeZonesByDomain['default'];

            $clientKey = $this->s3StorageConfigService->getSettingsHash($settings);

            if (!isset($s3Clients[$clientKey])) {
                $s3Clients[$clientKey] = $this->s3StorageConfigService->buildClientFromSettings($settings);
            }

            $s3 = $s3Clients[$clientKey];

            $recordingFile = rtrim($rec->record_path, '/') . '/' . $rec->record_name;

            if (!file_exists($recordingFile)) {
                $failed[] = [
                    'msg' => 'Recording file not found.',
                    'name' => $rec->record_name,
                ];
                return;
            }

            $mp3File = $this->convertToMp3IfNeeded($recordingFile);

            if (!$mp3File || !file_exists($mp3File)) {
                $failed[] = [
                    'msg' => 'MP3 conversion failed or file missing.',
                    'name' => $rec->record_name,
                ];
                return;
            }

            try {
                Log::info('Uploading File: ' . $mp3File);

                $objectKey = $this->buildObjectKey($rec, $settings, $mp3File, $timeZone);

                $s3->putObject([
                    'Bucket'     => $settings['bucket'],
                    'SourceFile' => $mp3File,
                    'Key'        => $objectKey,
                ]);

                $originalRecordName = $rec->record_name;

                $rec->record_path = 'S3';
                $rec->record_name = $objectKey;
                $rec->save();

                if ($mp3File !== $recordingFile && file_exists($mp3File)) {
                    unlink($mp3File);
                }

                if (
                    strtolower(pathinfo($recordingFile, PATHINFO_EXTENSION)) === 'wav'
                    && file_exists($recordingFile)
                ) {
                    unlink($recordingFile);
                }

                $success[] = $originalRecordName . ' => ' . $objectKey;
            } catch (\Exception $ex) {
                logger($ex->getMessage());

                $failed[] = [
                    'msg' => $ex->getMessage(),
                    'name' => $rec->record_name,
                ];
            }
        });

        $uploadNotificationEmail = DefaultSettings::where('default_setting_category', 's3_storage')
            ->where('default_setting_subcategory', 'upload_notification_email')
            ->where('default_setting_enabled', true)
            ->value('default_setting_value');

        if ($uploadNotificationEmail) {
            $attributes = [
                'email'   => $uploadNotificationEmail,
                'failed'  => $failed,
                'success' => $success,
            ];

            SendS3UploadReport::dispatch($attributes)->onQueue('emails');
        }
    }


    protected function getTimeZonesByDomain(array $domainUuids)
    {
        if (empty($domainUuids)) {
            return [
                'default' => 'UTC',
                'domains' => [],
            ];
        }

        $defaultTimeZone = DefaultSettings::where('default_setting_category', 'domain')
            ->where('default_setting_subcategory', 'time_zone')
            ->where('default_setting_enabled', true)
            ->value('default_setting_value') ?? 'UTC';

        $rows = DomainSettings::whereIn('domain_uuid', $domainUuids)
            ->where('domain_setting_subcategory', 'time_zone')
            ->where('domain_setting_enabled', true)
            ->get(['domain_uuid', 'domain_setting_value']);

        $domainTimeZones = [];

        foreach ($rows as $row) {
            if (!empty($row->domain_setting_value)) {
                $domainTimeZones[$row->domain_uuid] = $row->domain_setting_value;
            }
        }

        return [
            'default' => $defaultTimeZone,
            'domains' => $domainTimeZones,
        ];
    }

    protected function convertToMp3IfNeeded($recordingFile)
    {
        $ext = strtolower(pathinfo($recordingFile, PATHINFO_EXTENSION));

        if ($ext !== 'wav') {
            return $recordingFile;
        }

        $mp3File = preg_replace('/\.wav$/i', '.mp3', $recordingFile);

        $process = new Process([
            'ffmpeg',
            '-i',
            $recordingFile,
            '-b:a',
            '16k',
            '-ac',
            '1',
            '-q:a',
            '5',
            $mp3File,
        ]);

        try {
            $process->mustRun();
            return $mp3File;
        } catch (ProcessFailedException $e) {
            logger($e->getMessage());
            return null;
        }
    }

    protected function buildObjectKey($rec, array $settings, $filePath, string $timeZone = 'UTC')
    {
        $start = Carbon::parse($rec->start_stamp)->setTimezone($timeZone);

        if (($settings['type'] ?? 'default') === 'default') {
            $base = $rec->domain_name . '/'
                . $start->format('Y') . '/'
                . $start->format('m') . '/'
                . $start->format('d') . '/';
        } else {
            $base = 'recordings/'
                . $start->format('Y') . '/'
                . $start->format('m') . '/'
                . $start->format('d') . '/';
        }

        $ext = pathinfo($filePath, PATHINFO_EXTENSION);

        $direction = $this->sanitizePathSegment($rec->direction);
        $callerIdNumber = $this->sanitizePathSegment($rec->caller_id_number);
        $callerDestination = $this->sanitizePathSegment($rec->caller_destination);

        return $base
            . $start->format('His')
            . '_'
            . $direction
            . '_'
            . $callerIdNumber
            . '_'
            . $callerDestination
            . '.'
            . $ext;
    }

    protected function sanitizePathSegment($value)
    {
        $value = (string) $value;
        $value = preg_replace('/[^\w\-\+\.]/', '_', $value);

        return trim($value, '_') ?: 'unknown';
    }

    public function getDomainName($domain_id)
    {
        return Domain::where('domain_uuid', $domain_id)->first();
    }


    protected function getUploadLimit(): int
    {
        $value = DefaultSettings::where('default_setting_category', 'scheduled_jobs')
            ->where('default_setting_subcategory', 's3_upload_limit')
            ->where('default_setting_enabled', true)
            ->value('default_setting_value');

        $limit = (int) $value;

        if ($limit <= 0) {
            $limit = 2000;
        }

        return min($limit, 20000);
    }

    protected function getCallRecordingIds(int $limit): array
    {
        return CDR::query()
            ->whereNotNull('record_name')
            ->where('record_name', '<>', '')
            ->where('record_path', 'not like', '%S3%')
            ->where('record_path', 'not like', '%NFS%')
            ->where('hangup_cause', '<>', 'LOSE_RACE')
            ->where('start_stamp', '<=', now())
            ->orderBy('start_stamp', 'asc')
            ->limit($limit)
            ->pluck('xml_cdr_uuid')
            ->all();
    }

    protected function processRecordingsInChunks(array $ids, callable $callback): void
    {
        foreach (array_chunk($ids, 200) as $idChunk) {
            $recordings = CDR::select([
                'xml_cdr_uuid',
                'domain_uuid',
                'domain_name',
                'direction',
                'caller_id_number',
                'caller_destination',
                'start_stamp',
                'record_path',
                'record_name',
            ])
                ->whereIn('xml_cdr_uuid', $idChunk)
                ->orderBy('start_stamp', 'asc')
                ->get();

            foreach ($recordings as $rec) {
                $callback($rec);
            }
        }
    }
}
