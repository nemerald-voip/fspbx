<?php

namespace App\Services\Messaging\Outbound;

use App\Models\Messages;
use App\Services\CallWebhooks\PublicWebhookUrlValidator;
use App\Services\MessageMediaObjectStorageService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\DB;

class ApiMessageMedia
{
    public function __construct(private PublicWebhookUrlValidator $urls, private MessageMediaObjectStorageService $storage) {}

    public function prepare(Messages $message): void
    {
        $started = microtime(true);
        $remaining = (int) config('messaging.api.media_max_bytes', 50 * 1024 * 1024)
            - (int) data_get($message->delivery_meta, 'outbound.meta.api.downloaded_bytes', 0);
        foreach (data_get($message->delivery_meta, 'outbound.meta.api.media_urls', []) as $index => $url) {
            if (isset($message->media[$index])) continue;
            $seconds = (float) config('messaging.api.media_download_seconds', 60) - (microtime(true) - $started);
            if ($remaining <= 0 || $seconds <= 0) throw new MediaPreparationException('Media preparation limit exceeded.');
            $download = $this->download($url, $remaining, $seconds);
            $remaining -= strlen($download['binary']);
            $item = $this->storage->storeBinary(
                domainUuid: $message->domain_uuid, binary: $download['binary'], originalName: $download['name'],
                provider: 'api', mimeType: $download['mime'],
            );
            $item['access_path'] = '/messages/media/'.$message->getKey().'/'.$index.'/'.($item['stored_name'] ?? 'attachment');
            DB::transaction(function () use ($message, $item, $index, $download) {
                $current = Messages::whereKey($message->getKey())->lockForUpdate()->firstOrFail();
                $media = $current->media ?? [];
                $media[$index] = $item;
                $meta = $current->delivery_meta;
                data_set($meta, 'outbound.meta.api.downloaded_bytes',
                    (int) data_get($meta, 'outbound.meta.api.downloaded_bytes', 0) + strlen($download['binary']));
                $current->media = $media;
                $current->delivery_meta = $meta;
                $current->saveQuietly();
            });
            $message->refresh();
        }
    }

    public function download(string $url, int $maxBytes, float $seconds): array
    {
        $stream = tmpfile();
        if (! $stream) throw new MediaPreparationException('Unable to prepare attachment storage.');
        try {
            // Validate again at download time, pin that exact IP, and bypass proxies.
            $started = microtime(true);
            $address = $this->urls->validateAndResolve($url);
            $seconds -= microtime(true) - $started;
            if ($seconds <= 0) throw new MediaPreparationException('Attachment download budget exhausted.');
            $parts = parse_url($url);
            $host = trim($parts['host'], '[]');
            $port = $parts['port'] ?? 443;
            $address = str_contains($address, ':') ? '['.$address.']' : $address;
            $response = $this->client()->get($url, [
                'sink' => $stream, 'allow_redirects' => false, 'proxy' => '', 'verify' => true,
                'timeout' => $seconds, 'connect_timeout' => min(10, $seconds), 'decode_content' => false,
                'headers' => ['Accept-Encoding' => 'identity'],
                'curl' => filter_var($host, FILTER_VALIDATE_IP) ? [] : [CURLOPT_RESOLVE => ["{$host}:{$port}:{$address}"]],
                'on_headers' => function ($response) use ($maxBytes) {
                    if ($response->getStatusCode() !== 200
                        || (int) $response->getHeaderLine('Content-Length') > $maxBytes
                        || ! in_array(strtolower($response->getHeaderLine('Content-Encoding')), ['', 'identity'], true)) {
                        throw new MediaPreparationException('Attachment response is not supported.');
                    }
                },
                'progress' => function ($total, $downloaded) use ($maxBytes) {
                    if ($total > $maxBytes || $downloaded > $maxBytes) {
                        throw new MediaPreparationException('Attachment exceeds the download limit.');
                    }
                },
            ]);
            rewind($stream);
            $binary = stream_get_contents($stream, $maxBytes + 1);
            if ($binary === '' || strlen($binary) > $maxBytes) throw new MediaPreparationException('Invalid attachment size.');
            return ['binary' => $binary, 'name' => basename($parts['path'] ?? '') ?: 'attachment',
                'mime' => trim(explode(';', $response->getHeaderLine('Content-Type'))[0]) ?: 'application/octet-stream'];
        } catch (\Throwable $exception) {
            // URLs can contain signed query strings. Do not expose transport exceptions.
            throw new MediaPreparationException('An attachment could not be downloaded safely.');
        } finally {
            if (is_resource($stream)) fclose($stream);
        }
    }

    protected function client(): Client
    {
        return new Client(['handler' => HandlerStack::create(new CurlHandler())]);
    }
}
