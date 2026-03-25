<?php

namespace App\Services\Messaging\Outbound;

use App\Jobs\SendOutboundSMSMessage;
use App\Models\Messages;
use App\Services\MessageMediaObjectStorageService;
use App\Services\Messaging\MessageRepository;
use App\Services\Messaging\Outbound\Data\CreateOutboundMessageData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class CreateOutboundMessageService
{
    public function __construct(
        protected MessageRepository $messages,
        protected MessageMediaObjectStorageService $mediaStorage,
    ) {}

    public function create(CreateOutboundMessageData $data): Messages
    {
        messaging_webhook_debug('CreateOutboundMessageService create() started', [
            'domain_uuid' => $data->domainUuid,
            'extension_uuid' => $data->extensionUuid,
            'source' => $data->source,
            'destination' => $data->destination,
            'origin' => $data->origin,
            'carrier' => $data->carrier,
            'media_count' => count($data->media),
            'media_files_count' => count($data->mediaFiles),
            'media_remote_urls_count' => count($data->mediaRemoteUrls),
        ]);

        $storedMedia = [];

        if (!empty($data->media)) {
            $storedMedia = array_values($data->media);

            messaging_webhook_debug('Using pre-stored outbound media', [
                'count' => count($storedMedia),
            ]);
        }

        if (!empty($data->mediaFiles)) {
            $storedFromFiles = $this->ingestMediaFiles($data->domainUuid, $data->mediaFiles);
            $storedMedia = array_merge($storedMedia, $storedFromFiles);

            messaging_webhook_debug('Outbound media files ingested', [
                'count' => count($storedFromFiles),
            ]);
        }

        if (!empty($data->mediaRemoteUrls)) {
            $storedFromRemote = $this->ingestRemoteMediaUrls($data->domainUuid, $data->mediaRemoteUrls);
            $storedMedia = array_merge($storedMedia, $storedFromRemote);

            messaging_webhook_debug('Outbound remote media ingested', [
                'count' => count($storedFromRemote),
            ]);
        }

        $message = $this->messages->storeOutbound(
            domainUuid: $data->domainUuid,
            extensionUuid: $data->extensionUuid,
            source: $data->source,
            destination: $data->destination,
            text: $data->message,
            type: !empty($storedMedia) ? 'mms' : 'sms',
            carrier: $data->carrier,
            origin: $data->origin,
            providerReferenceId: $data->providerReferenceId,
            media: $storedMedia,
            meta: $data->meta,
        );

        messaging_webhook_debug('Outbound message created', [
            'message_uuid' => $message->message_uuid,
            'type' => $message->type,
            'media_count' => count($message->media ?? []),
        ]);

        SendOutboundSMSMessage::dispatch($message->message_uuid)->onQueue('messages');

        messaging_webhook_debug('Outbound send queued', [
            'message_uuid' => $message->message_uuid,
            'carrier' => $data->carrier,
        ]);

        return $message->fresh();
    }

    protected function ingestMediaFiles(string $domainUuid, array $mediaFiles): array
    {
        $stored = [];

        foreach ($mediaFiles as $file) {
            messaging_webhook_debug('Processing outbound media file', [
                'domain_uuid' => $domainUuid,
                'is_uploaded_file' => $file instanceof UploadedFile,
            ]);

            if ($file instanceof UploadedFile) {
                $stored[] = $this->mediaStorage->storeBinary(
                    domainUuid: $domainUuid,
                    binary: file_get_contents($file->getRealPath()),
                    originalName: $file->getClientOriginalName() ?: 'attachment',
                    provider: 'portal',
                    mimeType: $file->getMimeType(),
                );

                continue;
            }

            if (is_array($file) && isset($file['binary'])) {
                $stored[] = $this->mediaStorage->storeBinary(
                    domainUuid: $domainUuid,
                    binary: $file['binary'],
                    originalName: $file['original_name'] ?? 'attachment',
                    provider: $file['provider'] ?? 'portal',
                    mimeType: $file['mime_type'] ?? null,
                );

                continue;
            }
        }

        return $stored;
    }

    protected function ingestRemoteMediaUrls(string $domainUuid, array $urls): array
    {
        $stored = [];

        foreach ($urls as $url) {
            messaging_webhook_debug('Downloading outbound remote media', [
                'domain_uuid' => $domainUuid,
                'url' => $url,
            ]);

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                messaging_webhook_debug('Skipping invalid outbound remote media URL', [
                    'url' => $url,
                ]);
                continue;
            }

            $response = Http::timeout(30)->get($url);
            $response->throw();

            $binary = $response->body();

            if ($binary === '' || $binary === null) {
                throw new \RuntimeException('Downloaded remote media is empty: ' . $url);
            }

            $path = parse_url($url, PHP_URL_PATH);
            $originalName = $path ? basename($path) : 'attachment';

            $stored[] = $this->mediaStorage->storeBinary(
                domainUuid: $domainUuid,
                binary: $binary,
                originalName: $originalName,
                provider: 'ringotel',
                mimeType: $response->header('Content-Type'),
            );

            messaging_webhook_debug('Stored outbound remote media', [
                'url' => $url,
                'original_name' => $originalName,
            ]);
        }

        return $stored;
    }
}