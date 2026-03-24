<?php

namespace App\Services\Messaging;

use App\Services\MessageMediaObjectStorageService;
use App\Services\Messaging\Providers\MessagingWebhookParser;
use Illuminate\Support\Str;

class MessageMediaIngestor
{
    public function __construct(
        protected MessageMediaObjectStorageService $storageService
    ) {}

    public function store(
        MessagingWebhookParser $parser,
        string $provider,
        string $domainUuid,
        array $mediaUrls
    ): array {
        messaging_webhook_debug('MessageMediaIngestor store() started', [
            'provider' => $provider,
            'domain_uuid' => $domainUuid,
            'media_url_count' => count($mediaUrls),
        ]);

        $storedMedia = [];

        foreach ($mediaUrls as $url) {
            try {
                messaging_webhook_debug('Downloading media', [
                    'url' => $url,
                ]);

                $downloaded = $parser->downloadMedia($url);

                $originalName = $downloaded->originalName
                    ?: basename(parse_url($url, PHP_URL_PATH) ?: '')
                    ?: Str::uuid()->toString();

                messaging_webhook_debug('Downloaded media', [
                    'original_name' => $originalName,
                    'mime_type' => $downloaded->mimeType,
                    'size' => $downloaded->size,
                ]);

                $stored = $this->storageService->storeBinary(
                    domainUuid: $domainUuid,
                    binary: $downloaded->binary,
                    originalName: $originalName,
                    provider: $provider,
                    mimeType: $downloaded->mimeType,
                );

                $storedMedia[] = $stored;

                messaging_webhook_debug('Stored media object', [
                    'object_key' => $stored['object_key'] ?? null,
                    'bucket' => $stored['bucket'] ?? null,
                ]);
            } catch (\Throwable $e) {
                logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            }
        }

        messaging_webhook_debug('MessageMediaIngestor store() completed', [
            'stored_media_count' => count($storedMedia),
        ]);

        return $storedMedia;
    }
}