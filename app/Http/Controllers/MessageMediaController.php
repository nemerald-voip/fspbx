<?php

namespace App\Http\Controllers;

use App\Models\Messages;
use App\Services\MessageMediaObjectStorageService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageMediaController extends Controller
{
    public function show(
        string $message_uuid,
        int $index,
        MessageMediaObjectStorageService $mediaStorage
    ) {
        $message = Messages::where('message_uuid', $message_uuid)
            ->select(['message_uuid', 'domain_uuid', 'media'])
            ->firstOrFail();

        $media = $message->media;

        if (!is_array($media) || !array_key_exists($index, $media)) {
            abort(404);
        }

        $item = $media[$index];

        if (empty($item['bucket']) || empty($item['object_key'])) {
            abort(404);
        }

        $object = $mediaStorage->getObjectForDomain(
            domainUuid: $message->domain_uuid,
            bucket: $item['bucket'],
            objectKey: $item['object_key']
        );

        $fileName = $item['original_name'] ?? $item['stored_name'] ?? 'file';

        return new StreamedResponse(
            function () use ($object) {
                $stream = $object['body'];

                if (is_resource($stream)) {
                    fpassthru($stream);
                    return;
                }

                echo (string) $stream;
            },
            200,
            [
                'Content-Type' => $item['mime_type'] ?? $object['content_type'] ?? 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . addslashes($fileName) . '"',
                'Cache-Control' => 'private, max-age=300',
            ]
        );
    }
}