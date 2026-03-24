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
        $storedMedia = [];

        foreach ($mediaUrls as $url) {
            try {
                $binary = $parser->downloadMedia($url);

                $stored = $this->storageService->storeBinary(
                    binary: $binary,
                    domainUuid: $domainUuid,
                    provider: $provider,
                    originalName: basename(parse_url($url, PHP_URL_PATH) ?: Str::uuid()->toString()),
                );

                $storedMedia[] = $stored;
            } catch (\Throwable $e) {
                logger('Error storing inbound MMS media: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            }
        }

        return $storedMedia;
    }
}