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

            // Capture original path details before any processing
            $originalRecordPath = $rec->record_path;
            $originalRecordName = $rec->record_name;
            $recordingFile = rtrim($originalRecordPath, '/') . '/' . $originalRecordName;

            // 1. Check if file exists. 
            // If it is missing, it might be because a sibling CDR record sharing the same file
            // was processed just moments ago, uploaded the file, and deleted the local copy.
            if (!file_exists($recordingFile)) {
                // Refresh the model from the DB to see if it was updated by a batch update
                $rec->refresh();

                if (str_contains($rec->record_path, 'S3')) {
                    // This was already handled by a previous iteration (shared file).
                    // We can consider this a success or just skip silently.
                    return;
                }

                // The file is truly missing locally and not on S3.
                CDR::where('xml_cdr_uuid', $rec->xml_cdr_uuid)
                    ->update([
                        'record_path' => null,
                        'record_name' => null
                    ]);

                $failed[] = [
                    'msg' => 'Recording file not found. DB entries cleared.',
                    'name' => $originalRecordName,
                ];
                return;
            }

            $timeZone = $timeZonesByDomain['domains'][$rec->domain_uuid] ?? $timeZonesByDomain['default'];

            $clientKey = $this->s3StorageConfigService->getSettingsHash($settings);

            if (!isset($s3Clients[$clientKey])) {
                $s3Clients[$clientKey] = $this->s3StorageConfigService->buildClientFromSettings($settings);
            }

            $s3 = $s3Clients[$clientKey];

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

                $localSize = filesize($mp3File);

                $s3->putObject([
                    'Bucket'     => $settings['bucket'],
                    'SourceFile' => $mp3File,
                    'Key'        => $objectKey,
                ]);

                // Verify it exists and size matches
                $head = $s3->headObject([
                    'Bucket' => $settings['bucket'],
                    'Key'    => $objectKey,
                ]);

                $remoteSize = (int) ($head['ContentLength'] ?? 0);

                if ($remoteSize !== (int) $localSize) {
                    throw new \RuntimeException(
                        "Upload verification failed (size mismatch). Local={$localSize}, Remote={$remoteSize}"
                    );
                }

                // 2. MASS UPDATE
                // Update ALL CDRs that match this filename and path. 
                // This covers the current $rec AND any other CDRs sharing this file.
                $recordingStart = Carbon::parse($rec->start_stamp);

                CDR::where('record_name', $originalRecordName)
                    ->where('record_path', $originalRecordPath)
                    ->whereBetween('start_stamp', [
                        $recordingStart->copy()->subDay(),
                        $recordingStart->copy()->addDay(),
                    ])
                    ->update([
                        'record_path' => 'S3',
                        'record_name' => $objectKey
                    ]);

                // Cleanup local files
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
            '-nostdin',        // never prompt / read from stdin
            '-y',              // overwrite output if it exists
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
        $minimumAgeMinutes = 360;

        return CDR::query()
            ->whereNotNull('record_name')
            ->where('record_name', '<>', '')
            ->whereNotNull('record_path')
            ->where('record_path', '<>', '')
            ->where('record_path', 'not like', '%S3%')
            ->where('record_path', 'not like', '%NFS%')
            ->where('hangup_cause', '<>', 'LOSE_RACE')
            ->where('start_stamp', '<=', now()->subMinutes($minimumAgeMinutes))
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
