<?php

namespace App\Http\Controllers;

use App\Models\Messages;
use App\Services\MessageMediaObjectStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageMediaController extends Controller
{
    public function show(
        Request $request,
        string $message_uuid,
        int $index,
        MessageMediaObjectStorageService $mediaStorage
    ) {
        $message = Messages::where('message_uuid', $message_uuid)
            ->select(['message_uuid', 'domain_uuid', 'media'])
            ->firstOrFail();

        $media = $message->mediaItems();

        if (!is_array($media) || !array_key_exists($index, $media)) {
            abort(404);
        }

        $item = $media[$index];

        if (empty($item['bucket']) || empty($item['object_key'])) {
            abort(404);
        }

        // A stored object never changes, so a browser that already has it gets a 304
        // without resolving S3 settings or downloading the object again.
        $etag = sha1($item['bucket'] . '/' . $item['object_key']);
        $notModified = $this->cacheable(new Response(), $etag);
        if ($notModified->isNotModified($request)) {
            return $notModified;
        }

        $object = $mediaStorage->getObjectForDomain(
            domainUuid: $message->domain_uuid,
            bucket: $item['bucket'],
            objectKey: $item['object_key']
        );

        $fileName = $item['original_name'] ?? $item['stored_name'] ?? 'file';

        return $this->cacheable(new StreamedResponse(
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
            ]
        ), $etag);
    }

    protected function cacheable(Response $response, string $etag): Response
    {
        return $response->setEtag($etag)->setPrivate()->setMaxAge(86400)->setImmutable();
    }
}
