<?php

namespace App\Services\Messaging;

use App\Models\Messages;
use App\Services\MessageMediaObjectStorageService;
use Illuminate\Support\Str;

class OutboundPhotoService
{
    public function __construct(
        protected PhotoCompressionSettings $settings,
        protected PhotoCompressionClient $client,
        protected MessageMediaObjectStorageService $storage,
    ) {}

    public function prepare(Messages $message): void
    {
        if ($message->direction !== 'out' || !$message->media) return;

        $media = $message->media;
        $compress = $this->settings->enabled($message->domain_uuid);
        $photos = array_filter($media, function ($item) use ($compress) {
            $format = $this->photoFormat($item);
            return in_array($format, ['heic', 'webp', 'avif', 'tiff'], true)
                || ($compress && in_array($format, ['jpeg', 'png'], true));
        });
        if (!$photos) return;
        $perPhoto = null;
        if ($compress) {
            $budget = (int) config('message_photos.budget_bytes');
            foreach ($media as $index => $item) {
                if (!isset($photos[$index])) {
                    if (!isset($item['size']) || !is_numeric($item['size']) || $item['size'] < 0) {
                        throw new \RuntimeException(__('An attachment size is unavailable. Please attach the files again.'));
                    }
                    $budget -= (int) $item['size'];
                }
            }
            $perPhoto = intdiv(max(0, $budget), count($photos));
            if ($perPhoto < 16000) throw new \RuntimeException(__('The attachments leave too little space for photos. Send fewer attachments.'));
        }

        $spool = config('message_photos.spool');
        $lock = @fopen($spool.'/client.lock', 'c');
        if (!$lock) throw new \RuntimeException(__('Photo compression is unavailable. Please contact your administrator.'));
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) throw new PhotoCompressionBusy;
            $started = microtime(true);
            foreach ($photos as $index => $item) {
                // Persist each derivative before proceeding so retries reuse finished work.
                $prepared = ($item['photo_compression']['version'] ?? null) === 1
                    && (int) ($item['size'] ?? PHP_INT_MAX) <= $perPhoto;
                if ($prepared && !empty($item['access_path'])) continue;
                if (microtime(true) - $started > 45) {
                    throw new \RuntimeException(__('Photo preparation took too long. Please retry the message.'));
                }
                $media[$index] = $prepared ? $item : $this->preparePhoto($message->domain_uuid, $item, $perPhoto);
                // Object storage returns no public URL. Restore the message media
                // route for carrier delivery, including previously prepared retries.
                $storedName = $media[$index]['stored_name'] ?? basename($media[$index]['object_key'] ?? 'attachment');
                $media[$index]['access_path'] = "/messages/media/{$message->message_uuid}/{$index}/{$storedName}";
                $message->media = $media;
                $message->save();
            }
        } finally {
            fclose($lock);
        }
    }

    protected function photoFormat(mixed $item): ?string
    {
        if (!is_array($item)) return null;
        $mime = strtolower(trim(explode(';', $item['mime_type'] ?? '')[0]));
        $extension = strtolower(pathinfo($item['original_name'] ?? '', PATHINFO_EXTENSION));
        $mimes = ['image/jpeg' => 'jpeg', 'image/jpg' => 'jpeg', 'image/png' => 'png',
            'image/gif' => 'gif', 'image/bmp' => 'bmp', 'image/x-ms-bmp' => 'bmp',
            'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heic',
            'image/heic-sequence' => 'heic', 'image/heif-sequence' => 'heic',
            'image/avif' => 'avif', 'image/tiff' => 'tiff', 'image/x-tiff' => 'tiff'];
        $extensions = ['jpg' => 'jpeg', 'jpeg' => 'jpeg', 'jpe' => 'jpeg', 'jfif' => 'jpeg',
            'png' => 'png', 'gif' => 'gif', 'bmp' => 'bmp', 'dib' => 'bmp', 'webp' => 'webp',
            'heic' => 'heic', 'heif' => 'heic', 'avif' => 'avif', 'tif' => 'tiff', 'tiff' => 'tiff'];
        if (isset($mimes[$mime])) return $mimes[$mime];
        if (str_starts_with($mime, 'image/')) {
            throw new \RuntimeException(__('This image format is not supported. Attach a JPEG, PNG, GIF, BMP, HEIC, HEIF, WebP, AVIF, or TIFF photo.'));
        }
        if (isset($extensions[$extension])) return $extensions[$extension];
        if (in_array($extension, ['svg', 'svgz', 'ico', 'icns', 'jxl', 'raw', 'cr2', 'cr3', 'nef', 'arw', 'dng', 'psd'], true)) {
            throw new \RuntimeException(__('This image format is not supported. Attach a JPEG, PNG, GIF, BMP, HEIC, HEIF, WebP, AVIF, or TIFF photo.'));
        }
        return null;
    }

    protected function preparePhoto(string $domain, array $item, ?int $budget): array
    {
        $id = (string) Str::uuid();
        $directory = config('message_photos.spool').'/'.$id;
        if (!mkdir($directory, 0700)) throw new \RuntimeException(__('Unable to prepare photo compression.'));
        try {
            if (empty($item['bucket']) || empty($item['object_key'])) {
                throw new \RuntimeException(__('The photo is not available in message storage.'));
            }
            $object = $this->storage->getObjectForDomain($domain, $item['bucket'], $item['object_key']);
            $body = $object['body'];
            $source = is_resource($body) ? $body : \GuzzleHttp\Psr7\StreamWrapper::getResource($body);
            $target = fopen($directory.'/input', 'xb');
            try {
                $bytes = stream_copy_to_stream($source, $target, (int) config('message_photos.max_input_bytes') + 1);
            } finally {
                fclose($target);
                fclose($source);
            }
            if (!$bytes || $bytes > config('message_photos.max_input_bytes')) {
                throw new \RuntimeException(__('The photo exceeds the 50 MB input limit.'));
            }
            $result = $budget === null ? $this->client->convert($id) : $this->client->compress($id, $budget);
            if ($result['unchanged'] ?? false) {
                if ($budget !== null && $bytes > $budget) throw new \RuntimeException(__('The photo exceeds the message attachment limit.'));
                $item['size'] = $bytes;
                $item['photo_compression'] = ['version' => 1, 'encodes' => 0];
                return $item;
            }
            $path = $directory.'/output.jpg';
            $size = is_file($path) ? filesize($path) : 0;
            $dimensions = $size && $size <= ($budget ?? config('message_photos.max_input_bytes')) ? @getimagesize($path) : false;
            if (!$dimensions || $dimensions[2] !== IMAGETYPE_JPEG) {
                throw new \RuntimeException(__('Photo compression did not produce a valid JPEG within the size limit.'));
            }
            $name = pathinfo($item['original_name'] ?? 'photo', PATHINFO_FILENAME).'.jpg';
            $stored = $this->storage->storeBinary($domain, file_get_contents($path), $name, $item['provider'] ?? 'unknown', 'image/jpeg');
            $stored['photo_compression'] = ['version' => 1, 'mode' => $budget === null ? 'convert' : 'compress', 'encodes' => $result['encodes'], 'original_bytes' => $bytes,
                'original' => $item['photo_compression']['original'] ?? array_intersect_key($item, array_flip(['bucket', 'object_key', 'original_name', 'mime_type']))];
            return $stored;
        } finally {
            // Only remove files belonging to this generated request directory.
            foreach (['input', 'output.jpg'] as $file) {
                if (is_file($directory.'/'.$file)) unlink($directory.'/'.$file);
            }
            @rmdir($directory);
        }
    }
}
