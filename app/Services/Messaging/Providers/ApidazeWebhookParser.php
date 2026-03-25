<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Data\DownloadedMediaData;
use App\Services\Messaging\Data\InboundMessageEventData;
use App\Services\Messaging\Providers\Concerns\ExtractsMediaFilename;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use libphonenumber\PhoneNumberFormat;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

class ApidazeWebhookParser implements MessagingWebhookParser
{
    use ExtractsMediaFilename;

    /**
     * @return iterable<object>
     */
    public function parse(WebhookCall $webhookCall): iterable
    {
        $payload = $webhookCall->payload ?? [];
        $type = data_get($payload, 'type');

        messaging_webhook_debug('Apidaze parser received payload', [
            'type' => $type,
            'payload_summary' => $this->summarizePayload($payload),
        ]);

        if (! $this->isInboundMessagePayload($payload)) {
            messaging_webhook_debug('Apidaze parser found no matching payload shape', [
                'type' => $type,
            ]);

            return;
        }

        $data = InboundMessageEventData::from([
            'provider' => 'apidaze',
            'providerReferenceId' => $this->extractReferenceId($payload),
            'from' => (string) $this->extractFrom($payload),
            'to' => $this->extractTo($payload),
            'text' => (string) $this->extractText($payload),
            'mediaUrls' => $this->extractMediaUrls($payload),
            'providerEvent' => $type ?: 'inbound_message',
        ]);

        messaging_webhook_debug('Apidaze parser yielding inbound event', [
            'reference_id' => $data->providerReferenceId,
            'from' => $data->from,
            'to' => $data->to,
            'media_count' => count($data->mediaUrls),
            'provider_event' => $data->providerEvent,
        ]);

        yield $data;
    }

    public function downloadMedia(string $url): DownloadedMediaData
    {
        messaging_webhook_debug('Apidaze downloadMedia started', [
            'url' => $this->summarizeMediaReference($url),
        ]);

        if ($this->isInlineMediaReference($url)) {
            $inline = $this->decodeInlineMediaReference($url);

            messaging_webhook_debug('Apidaze downloadMedia resolved inline payload', [
                'original_name' => $inline['original_name'],
                'mime_type' => $inline['mime_type'],
                'size' => strlen($inline['binary']),
            ]);

            return DownloadedMediaData::from([
                'binary' => $inline['binary'],
                'originalName' => $inline['original_name'],
                'mimeType' => $inline['mime_type'],
                'size' => strlen($inline['binary']),
                'sourceUrl' => $url,
            ]);
        }

        $response = Http::timeout(60)->get($url);
        $response->throw();

        $body = $response->body();

        $originalName = $this->extractFilenameFromContentDisposition(
            $response->header('Content-Disposition')
        );

        if (! $originalName) {
            $originalName = basename(parse_url($url, PHP_URL_PATH) ?: '') ?: 'attachment';
        }

        messaging_webhook_debug('Apidaze downloadMedia completed', [
            'url' => $url,
            'original_name' => $originalName,
            'mime_type' => $response->header('Content-Type'),
            'size' => strlen($body),
        ]);

        return DownloadedMediaData::from([
            'binary' => $body,
            'originalName' => $originalName,
            'mimeType' => $response->header('Content-Type'),
            'size' => strlen($body),
            'sourceUrl' => $url,
        ]);
    }

    protected function isInboundMessagePayload(array $payload): bool
    {
        $type = data_get($payload, 'type');

        if (in_array($type, ['incomingWebhookSMS', 'incomingWebhookMMS'], true)) {
            return true;
        }

        return filled($this->extractFrom($payload))
            && ! empty($this->extractTo($payload));
    }

    protected function extractReferenceId(array $payload): ?string
    {
        $referenceId = data_get($payload, 'reference_id')
            ?? data_get($payload, 'referenceId')
            ?? data_get($payload, 'message_uuid')
            ?? data_get($payload, 'uuid')
            ?? data_get($payload, 'id');

        return filled($referenceId) ? (string) $referenceId : null;
    }

    protected function extractFrom(array $payload): ?string
    {
        $from = data_get($payload, 'from')
            ?? data_get($payload, 'caller_id_number')
            ?? data_get($payload, 'source')
            ?? data_get($payload, 'msisdn');

        return $this->normalizePhoneNumber($from);
    }

    protected function extractTo(array $payload): array
    {
        $to = data_get($payload, 'to')
            ?? data_get($payload, 'destination_number')
            ?? data_get($payload, 'destination')
            ?? data_get($payload, 'recipient');

        return collect(Arr::wrap($to))
            ->map(fn ($item) => $this->normalizePhoneNumber(is_scalar($item) ? (string) $item : null))
            ->filter(fn ($item) => filled($item))
            ->values()
            ->all();
    }

    protected function extractText(array $payload): ?string
    {
        return data_get($payload, 'text')
            ?? data_get($payload, 'body')
            ?? data_get($payload, 'message')
            ?? '';
    }

    protected function extractMediaUrls(array $payload): array
    {
        $mediaReferences = [];

        $files = data_get($payload, 'files');

        if (is_string($files) && filled($files)) {
            foreach ($this->parseApidazeFilesString($files) as $originalName => $base64Content) {
                if ($this->shouldSkipInlineMediaItem($originalName, null)) {
                    continue;
                }

                $mediaReferences[] = $this->encodeInlineMediaReference(
                    binary: base64_decode($base64Content, true) ?: '',
                    originalName: $originalName,
                    mimeType: $this->guessMimeTypeFromName($originalName),
                );
            }
        } else {
            foreach (Arr::wrap($files) as $item) {
                $normalized = $this->normalizeMediaReference($item);

                if (filled($normalized)) {
                    $mediaReferences[] = $normalized;
                }
            }
        }

        $otherMedia = data_get($payload, 'media')
            ?? data_get($payload, 'mediaUrls')
            ?? data_get($payload, 'media_urls')
            ?? [];

        foreach (Arr::wrap($otherMedia) as $item) {
            $normalized = $this->normalizeMediaReference($item);

            if (filled($normalized)) {
                $mediaReferences[] = $normalized;
            }
        }

        return collect($mediaReferences)
            ->filter(fn ($item) => filled($item))
            ->reject(fn ($item) => $this->shouldSkipMediaReference($item))
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeMediaReference(mixed $item): ?string
    {
        if (is_string($item)) {
            $trimmed = trim($item);

            if ($trimmed === '') {
                return null;
            }

            if ($this->shouldSkipMediaReference($trimmed)) {
                return null;
            }

            if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
                return $trimmed;
            }

            if (str_starts_with($trimmed, 'data:')) {
                $parsed = $this->parseDataUrl($trimmed);

                if (! $parsed) {
                    return null;
                }

                if ($this->shouldSkipInlineMediaItem($parsed['original_name'], $parsed['mime_type'])) {
                    return null;
                }

                return $this->encodeInlineMediaReference(
                    binary: $parsed['binary'],
                    originalName: $parsed['original_name'],
                    mimeType: $parsed['mime_type'],
                );
            }

            return null;
        }

        if (! is_array($item)) {
            return null;
        }

        $directUrl = $item['url']
            ?? $item['href']
            ?? $item['mediaUrl']
            ?? $item['media_url']
            ?? null;

        if (filled($directUrl)) {
            return $this->shouldSkipMediaReference((string) $directUrl)
                ? null
                : (string) $directUrl;
        }

        $originalName = (string) (
            $item['filename']
            ?? $item['name']
            ?? $item['file_name']
            ?? $item['original_name']
            ?? 'attachment'
        );

        $mimeType = $item['mime_type']
            ?? $item['content_type']
            ?? $item['type']
            ?? $this->guessMimeTypeFromName($originalName);

        if ($this->shouldSkipInlineMediaItem($originalName, $mimeType)) {
            return null;
        }

        foreach (['base64', 'data', 'content', 'body', 'file', 'content_base64', 'encoded_data'] as $key) {
            $value = $item[$key] ?? null;

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            if (str_starts_with($value, 'data:')) {
                $parsed = $this->parseDataUrl($value);

                if (! $parsed) {
                    return null;
                }

                return $this->encodeInlineMediaReference(
                    binary: $parsed['binary'],
                    originalName: $originalName !== 'attachment' ? $originalName : $parsed['original_name'],
                    mimeType: $parsed['mime_type'] ?: $mimeType,
                );
            }

            $binary = base64_decode($value, true);

            if ($binary === false) {
                continue;
            }

            return $this->encodeInlineMediaReference(
                binary: $binary,
                originalName: $originalName,
                mimeType: $mimeType,
            );
        }

        return null;
    }

    protected function parseApidazeFilesString(string $files): array
    {
        $results = [];

        preg_match_all(
            "/'([^']+)'\\s*:\\s*'([\\s\\S]*?)'(?=,\\s*'[^']+'\\s*:|\\s*\\}$)/",
            trim($files),
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $filename = $match[1] ?? null;
            $base64 = $match[2] ?? null;

            if ($filename && $base64) {
                $results[$filename] = $base64;
            }
        }

        return $results;
    }

    protected function normalizePhoneNumber(?string $number): ?string
    {
        if (! filled($number)) {
            return null;
        }

        $countryCode = get_domain_setting('country', $domain_uuid = null) ?? 'US';

        try {
            return formatPhoneNumber($number, $countryCode, PhoneNumberFormat::E164);
        } catch (Throwable) {
            $digits = preg_replace('/\D+/', '', (string) $number);

            return $digits !== '' ? $digits : null;
        }
    }

    protected function shouldSkipMediaReference(string $mediaReference): bool
    {
        if ($this->isInlineMediaReference($mediaReference)) {
            try {
                $inline = $this->decodeInlineMediaReference($mediaReference);

                return $this->shouldSkipInlineMediaItem(
                    $inline['original_name'] ?? null,
                    $inline['mime_type'] ?? null,
                );
            } catch (Throwable) {
                return false;
            }
        }

        $path = parse_url($mediaReference, PHP_URL_PATH) ?? '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension === 'smil';
    }

    protected function shouldSkipInlineMediaItem(?string $originalName, ?string $mimeType): bool
    {
        $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
        $mimeType = strtolower((string) $mimeType);

        return $extension === 'smil'
            || $mimeType === 'application/smil'
            || $mimeType === 'application/smil+xml';
    }

    protected function isInlineMediaReference(string $url): bool
    {
        return str_starts_with($url, 'apidaze-inline://');
    }

    protected function encodeInlineMediaReference(string $binary, string $originalName, ?string $mimeType = null): string
    {
        $payload = [
            'binary_base64' => base64_encode($binary),
            'original_name' => $originalName !== '' ? $originalName : 'attachment',
            'mime_type' => $mimeType ?: $this->guessMimeTypeFromName($originalName),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $encoded = rtrim(strtr(base64_encode($json ?: '{}'), '+/', '-_'), '=');

        return 'apidaze-inline://' . $encoded;
    }

    protected function decodeInlineMediaReference(string $url): array
    {
        $encoded = substr($url, strlen('apidaze-inline://'));
        $encoded .= str_repeat('=', (4 - (strlen($encoded) % 4)) % 4);

        $json = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($json === false) {
            throw new \RuntimeException('Invalid Apidaze inline media payload');
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new \RuntimeException('Invalid Apidaze inline media JSON');
        }

        $binary = base64_decode((string) ($payload['binary_base64'] ?? ''), true);

        if ($binary === false) {
            throw new \RuntimeException('Invalid Apidaze inline media binary');
        }

        return [
            'binary' => $binary,
            'original_name' => (string) ($payload['original_name'] ?? 'attachment'),
            'mime_type' => $payload['mime_type'] ?? null,
        ];
    }

    protected function parseDataUrl(string $value): ?array
    {
        if (! preg_match('/^data:(.*?);base64,(.*)$/s', $value, $matches)) {
            return null;
        }

        $mimeType = $matches[1] ?? 'application/octet-stream';
        $binary = base64_decode($matches[2] ?? '', true);

        if ($binary === false) {
            return null;
        }

        return [
            'binary' => $binary,
            'mime_type' => $mimeType,
            'original_name' => 'attachment.' . $this->extensionFromMimeType($mimeType),
        ];
    }

    protected function guessMimeTypeFromName(string $originalName): string
    {
        return match (strtolower(pathinfo($originalName, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'webp' => 'image/webp',
            'txt' => 'text/plain',
            'smil' => 'application/smil',
            default => 'application/octet-stream',
        };
    }

    protected function extensionFromMimeType(string $mimeType): string
    {
        return match (strtolower($mimeType)) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'application/smil', 'application/smil+xml' => 'smil',
            default => 'bin',
        };
    }

    protected function summarizePayload(array $payload): array
    {
        $summary = $payload;

        if (isset($summary['files'])) {
            if (is_string($summary['files'])) {
                $summary['files'] = [
                    'type' => 'string',
                    'length' => strlen($summary['files']),
                ];
            } elseif (is_array($summary['files'])) {
                $summary['files'] = [
                    'type' => 'array',
                    'count' => count($summary['files']),
                ];
            }
        }

        return $summary;
    }

    protected function summarizeMediaReference(string $url): string
    {
        if ($this->isInlineMediaReference($url)) {
            return 'apidaze-inline://[encoded-payload]';
        }

        return $url;
    }
}