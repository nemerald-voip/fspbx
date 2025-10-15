<?php

namespace App\Services;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;
use App\Models\CDR;

class CallRecordingUrlService
{
    /**
     * Return a temporary URL (string|null) for a recording by CDR UUID.
     * - Local: returns a signed route to a streaming controller (supports HTTP range).
     * - S3: returns a pre-signed S3 URL.
     */
    public function temporaryUrlForCdr(string $xmlCdrUuid, int $ttlSeconds = 600): ?string
    {
        /** @var CDR|null $rec */
        $rec = CDR::query()
            ->select('xml_cdr_uuid', 'record_path', 'record_name', 'domain_uuid')
            ->with('archive_recording:xml_cdr_uuid,object_key')
            ->where('xml_cdr_uuid', $xmlCdrUuid)
            ->first();

        if (!$rec) return null;

        // S3 route
        if ($rec->record_path === 'S3') {
            $objectKey = $this->resolveS3ObjectKey($rec);
            if (!$objectKey) return null;

            $disk = $this->buildS3DiskForDomain($rec->domain_uuid);
            if (!$disk) return null;

            // Content-Disposition optional; can switch to inline if preferred
            $opts = ['ResponseContentDisposition' => 'inline; filename="'.basename($objectKey).'"'];
            return $disk->temporaryUrl($objectKey, now()->addSeconds($ttlSeconds), $opts);
        }

        // We pass only the UUID; controller re-validates and streams securely.
        return URL::temporarySignedRoute(
            'cdrs.recording.stream',
            now()->addSeconds($ttlSeconds),
            ['uuid' => $rec->xml_cdr_uuid]
        );
    }

    private function resolveS3ObjectKey($rec): ?string
    {
        if (!empty($rec->record_name)) return $rec->record_name;
        if ($rec->archive_recording && !empty($rec->archive_recording->object_key)) {
            return $rec->archive_recording->object_key;
        }
        return null;
    }

    private function buildS3DiskForDomain(string $domainUuid)
    {
        $required = ['access_key','bucket_name','region','secret_key'];

        $domain = \App\Models\DomainSettings::query()
            ->where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', 'aws')
            ->whereIn('domain_setting_subcategory', $required)
            ->where('domain_setting_enabled', true)
            ->pluck('domain_setting_value', 'domain_setting_subcategory')
            ->toArray();

        $cfg = null;

        if (count(array_intersect(array_keys($domain), $required)) === count($required)) {
            $cfg = [
                'driver' => 's3',
                'key'    => $domain['access_key'],
                'secret' => $domain['secret_key'],
                'region' => $domain['region'],
                'bucket' => $domain['bucket_name'],
            ];
        } else {
            $defaults = \App\Models\DefaultSettings::query()
                ->where('default_setting_category', 'aws')
                ->whereIn('default_setting_subcategory', $required)
                ->where('default_setting_enabled', true)
                ->pluck('default_setting_value', 'default_setting_subcategory')
                ->toArray();

            if (count(array_intersect(array_keys($defaults), $required)) === count($required)) {
                $cfg = [
                    'driver' => 's3',
                    'key'    => $defaults['access_key'],
                    'secret' => $defaults['secret_key'],
                    'region' => $defaults['region'],
                    'bucket' => $defaults['bucket_name'],
                ];
            }
        }

        return $cfg ? Storage::build($cfg) : null;
    }
}
