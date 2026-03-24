<?php

namespace App\Services;

use Illuminate\Support\Str;

class MessageMediaObjectStorageService
{
    protected array $clients = [];

    public function __construct(
        protected S3StorageConfigService $s3StorageConfigService
    ) {}

    public function storeBinaryForDomain(
        string $domainUuid,
        string $binary,
        string $originalName,
        string $provider = 'unknown'
    ): array {
        $settings = $this->getSettingsForDomain($domainUuid);

        if (!$settings) {
            throw new \RuntimeException('No s3_storage settings found for domain ' . $domainUuid);
        }

        $s3 = $this->getClientForSettings($settings);

        $mimeType = $this->guessMimeType($binary, $originalName);
        $extension = $this->resolveExtension($originalName, $mimeType);
        $storedName = (string) Str::uuid() . ($extension ? '.' . $extension : '');
        $objectKey = $this->buildObjectKey($storedName);
        $size = strlen($binary);

        $params = [
            'Bucket' => $settings['bucket'],
            'Key' => $objectKey,
            'Body' => $binary,
            'ContentLength' => $size,
            'ContentType' => $mimeType,
        ];

        $s3->putObject($params);

        $head = $s3->headObject([
            'Bucket' => $settings['bucket'],
            'Key' => $objectKey,
        ]);

        $remoteSize = (int) ($head['ContentLength'] ?? 0);

        if ($remoteSize !== $size) {
            throw new \RuntimeException(
                "Upload verification failed (size mismatch). Local={$size}, Remote={$remoteSize}"
            );
        }

        return [
            'provider' => $provider,
            'storage' => 's3_compatible',
            'bucket' => $settings['bucket'],
            'object_key' => $objectKey,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'size' => $size,
            'access_path' => null, // filled in after the Message row exists
        ];
    }

    public function generateTemporaryDownloadUrl(
        string $domainUuid,
        string $bucket,
        string $objectKey,
        string $expires = '+5 minutes',
        ?string $mimeType = null,
        ?string $downloadName = null
    ): string {
        $settings = $this->getSettingsForDomain($domainUuid);

        if (!$settings) {
            throw new \RuntimeException('No s3_storage settings found for domain ' . $domainUuid);
        }

        $s3 = $this->getClientForSettings($settings);

        $params = [
            'Bucket' => $bucket,
            'Key' => $objectKey,
        ];

        if ($mimeType) {
            $params['ResponseContentType'] = $mimeType;
        }

        if ($downloadName) {
            $params['ResponseContentDisposition'] = 'inline; filename="' . addslashes($downloadName) . '"';
        }

        $command = $s3->getCommand('GetObject', $params);
        $request = $s3->createPresignedRequest($command, $expires);

        return (string) $request->getUri();
    }

    protected function getSettingsForDomain(string $domainUuid): ?array
    {
        $storageSettings = $this->s3StorageConfigService->getSettingsMapForDomains([$domainUuid]);

        return $storageSettings['domains'][$domainUuid] ?? $storageSettings['default'] ?? null;
    }

    protected function getClientForSettings(array $settings)
    {
        $clientKey = $this->s3StorageConfigService->getSettingsHash($settings);

        if (!isset($this->clients[$clientKey])) {
            $this->clients[$clientKey] = $this->s3StorageConfigService->buildClientFromSettings($settings);
        }

        return $this->clients[$clientKey];
    }

    protected function buildObjectKey(string $storedName): string
    {
        return 'message-media/'
            . date('Y') . '/'
            . date('m') . '/'
            . date('d') . '/'
            . $storedName;
    }

    protected function guessMimeType(string $binary, string $originalName): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary);

        if ($mime) {
            return $mime;
        }

        return match (strtolower(pathinfo($originalName, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'webp' => 'image/webp',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }

    protected function resolveExtension(string $originalName, string $mimeType): string
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!empty($ext)) {
            return $ext;
        }

        return match ($mimeType) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            default => 'bin',
        };
    }

    public function getObjectForDomain(string $domainUuid, string $bucket, string $objectKey): array
    {
        $settings = $this->getSettingsForDomain($domainUuid);

        if (!$settings) {
            throw new \RuntimeException('No s3_storage settings found for domain ' . $domainUuid);
        }

        $s3 = $this->getClientForSettings($settings);

        $result = $s3->getObject([
            'Bucket' => $bucket,
            'Key' => $objectKey,
        ]);

        return [
            'body' => $result['Body'],
            'content_type' => $result['ContentType'] ?? 'application/octet-stream',
            'content_length' => $result['ContentLength'] ?? null,
        ];
    }
}
