<?php

namespace App\Services;

use App\Models\CDR;
use Illuminate\Support\Facades\URL;

class CallRecordingUrlService
{
    public function __construct(
        protected S3StorageConfigService $s3StorageConfigService
    ) {
    }

    /**
     * Return temporary URLs for a recording by CDR UUID.
     * - Local: returns signed routes to local stream/download endpoints.
     * - S3/S3-compatible: returns presigned object URLs.
     */
    public function urlsForCdr(string $xmlCdrUuid, int $ttlSeconds = 600): array
    {
        $rec = CDR::query()
            ->select('xml_cdr_uuid', 'record_path', 'record_name', 'domain_uuid')
            ->with('archive_recording:xml_cdr_uuid,object_key')
            ->where('xml_cdr_uuid', $xmlCdrUuid)
            ->first();

        if (!$rec) {
            return $this->emptyResponse();
        }

        if ($rec->record_path === 'S3') {
            $objectKey = $this->resolveS3ObjectKey($rec);

            if (!$objectKey) {
                return $this->emptyResponse();
            }

            $disk = $this->s3StorageConfigService->buildDiskForDomain($rec->domain_uuid);

            if (!$disk) {
                return $this->emptyResponse();
            }

            try {
                if (!$disk->exists($objectKey)) {
                    return $this->emptyResponse();
                }
            } catch (\Throwable $e) {
                return $this->emptyResponse();
            }

            $filename = basename($objectKey);

            $audioUrl = $disk->temporaryUrl(
                $objectKey,
                now()->addSeconds($ttlSeconds),
                [
                    'ResponseContentDisposition' => 'inline; filename="' . $filename . '"',
                ]
            );

            $downloadUrl = $disk->temporaryUrl(
                $objectKey,
                now()->addSeconds($ttlSeconds),
                [
                    'ResponseContentDisposition' => 'attachment; filename="' . $filename . '"',
                    'ResponseContentType' => 'application/octet-stream',
                ]
            );

            return [
                'audio_url' => $audioUrl,
                'download_url' => $downloadUrl,
                'filename' => $filename,
            ];
        }

        $absDir = rtrim((string) $rec->record_path, '/');
        $file = (string) $rec->record_name;
        $absPath = $absDir !== '' && $file !== '' ? ($absDir . '/' . $file) : null;

        if (!$absPath || !is_file($absPath)) {
            return $this->emptyResponse();
        }

        $filename = basename($file);

        return [
            'audio_url' => URL::temporarySignedRoute(
                'cdrs.recording.stream',
                now()->addSeconds($ttlSeconds),
                ['uuid' => $rec->xml_cdr_uuid]
            ),
            'download_url' => URL::temporarySignedRoute(
                'cdrs.recording.download',
                now()->addSeconds($ttlSeconds),
                ['uuid' => $rec->xml_cdr_uuid]
            ),
            'filename' => $filename,
        ];
    }

    private function resolveS3ObjectKey($rec): ?string
    {
        if (!empty($rec->record_name)) {
            return $rec->record_name;
        }

        if ($rec->archive_recording && !empty($rec->archive_recording->object_key)) {
            return $rec->archive_recording->object_key;
        }

        return null;
    }

    private function emptyResponse(): array
    {
        return [
            'audio_url' => null,
            'download_url' => null,
            'filename' => null,
        ];
    }
}
