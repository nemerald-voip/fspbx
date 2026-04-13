<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Data\DownloadedMediaData;
use App\Services\Messaging\Data\InboundMessageEventData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use libphonenumber\PhoneNumberFormat;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

class CommioWebhookParser implements MessagingWebhookParser
{
    /**
     * @return iterable<object>
     */
    public function parse(WebhookCall $webhookCall): iterable
    {
        $payload = $webhookCall->payload ?? [];
        $headers = $this->extractHeaders($webhookCall);
        $type = strtoupper((string) ($payload['type'] ?? ''));

        messaging_webhook_debug('CommioWebhookParser parse() started', [
            'type' => $payload['type'] ?? null,
            'provider_reference_id' => $this->extractProviderReferenceId($headers),
            'payload_keys' => array_keys($payload),
        ]);

        if (! $this->isInboundMessagePayload($payload)) {
            messaging_webhook_debug('CommioWebhookParser ignored payload', [
                'reason' => 'payload_did_not_match_inbound_shape',
            ]);

            return;
        }

        $mediaUrls = $this->extractMediaUrls($payload);
        $text = $this->extractText($payload);

        $event = InboundMessageEventData::from([
            'provider' => 'commio',
            'providerReferenceId' => $this->extractProviderReferenceId($headers),
            'from' => (string) $this->normalizePhoneNumber($payload['from'] ?? null),
            'to' => array_values(array_filter([
                $this->normalizePhoneNumber($payload['to'] ?? null),
            ])),
            'text' => $text,
            'mediaUrls' => $mediaUrls,
            'providerEvent' => match ($type) {
                'SMS' => 'incoming_sms',
                'MMS' => 'incoming_mms',
                default => 'incoming_message',
            },
        ]);

        messaging_webhook_debug('CommioWebhookParser inbound event parsed', [
            'provider_reference_id' => $event->providerReferenceId,
            'from' => $event->from,
            'to' => $event->to,
            'text_length' => strlen((string) $event->text),
            'media_count' => count($event->mediaUrls),
            'provider_event' => $event->providerEvent,
        ]);

        yield $event;
    }

    public function downloadMedia(string $url): DownloadedMediaData
    {
        messaging_webhook_debug('CommioWebhookParser downloadMedia() started', [
            'url' => $this->summarizeMediaReference($url),
        ]);

        if ($this->isInlineMediaReference($url)) {
            $inline = $this->decodeInlineMediaReference($url);

            messaging_webhook_debug('CommioWebhookParser downloadMedia() resolved inline payload', [
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

        $response = Http::timeout(60)
            ->accept('*/*')
            ->get($url);

        $response->throw();

        $binary = $response->body();
        $mimeType = $response->header('Content-Type') ?: 'application/octet-stream';
        $originalName = $this->extractFilenameFromUrl($url, $mimeType);

        messaging_webhook_debug('CommioWebhookParser downloadMedia() completed', [
            'url' => $url,
            'mime_type' => $mimeType,
            'size' => strlen($binary),
            'original_name' => $originalName,
        ]);

        return DownloadedMediaData::from([
            'binary' => $binary,
            'originalName' => $originalName,
            'mimeType' => $mimeType,
            'size' => strlen($binary),
            'sourceUrl' => $url,
        ]);
    }

    protected function isInboundMessagePayload(array $payload): bool
    {
        return filled($payload['from'] ?? null)
            && filled($payload['to'] ?? null)
            && in_array(strtoupper((string) ($payload['type'] ?? '')), ['SMS', 'MMS'], true);
    }

    protected function extractText(array $payload): string
    {
        $type = strtoupper((string) ($payload['type'] ?? ''));

        if ($type === 'SMS') {
            return trim((string) ($payload['message'] ?? ''));
        }

        // Single webhook MMS: message is the text portion.
        if ($type === 'MMS' && ! empty($this->normalizeArrayInput($payload['attachments'] ?? null))) {
            return trim((string) ($payload['message'] ?? ''));
        }

        // Non-single MMS: message is the attachment payload, not the text body.
        return '';
    }

    protected function extractMediaUrls(array $payload): array
    {
        $type = strtoupper((string) ($payload['type'] ?? ''));

        if ($type === 'SMS') {
            return [];
        }

        $attachments = $this->normalizeArrayInput($payload['attachments'] ?? null);
        $contentTypes = $this->normalizeArrayInput($payload['contentType'] ?? null);

        $mediaUrls = [];

        // MMS single webhook: attachments array carries the media.
        if (! empty($attachments)) {
            foreach ($attachments as $index => $attachment) {
                $mimeType = $contentTypes[$index] ?? $contentTypes[0] ?? null;
                $normalized = $this->normalizeMediaReference(
                    item: $attachment,
                    mimeType: is_string($mimeType) ? $mimeType : null,
                    originalName: 'attachment_' . ($index + 1)
                );

                if ($normalized !== null) {
                    $mediaUrls[] = $normalized;
                }
            }

            return array_values(array_unique(array_filter($mediaUrls)));
        }

        // MMS non-single webhook: message field itself is the attachment payload.
        $singleMimeType = is_array($payload['contentType'] ?? null)
            ? ($payload['contentType'][0] ?? null)
            : ($payload['contentType'] ?? null);

        $normalized = $this->normalizeMediaReference(
            item: $payload['message'] ?? null,
            mimeType: is_string($singleMimeType) ? $singleMimeType : null,
            originalName: 'attachment_1'
        );

        return $normalized ? [$normalized] : [];
    }

    protected function normalizeMediaReference(mixed $item, ?string $mimeType = null, string $originalName = 'attachment'): ?string
    {
        if (! is_string($item) || trim($item) === '') {
            return null;
        }

        $value = trim($item);

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $this->shouldSkipMediaUrl($value) ? null : $value;
        }

        $binary = base64_decode($value, true);

        if ($binary === false) {
            return null;
        }

        $mimeType = $mimeType ?: 'application/octet-stream';

        if ($this->shouldSkipMimeType($mimeType)) {
            return null;
        }

        $originalName = $originalName . '.' . $this->extensionFromMimeType($mimeType);

        return $this->encodeInlineMediaReference(
            binary: $binary,
            originalName: $originalName,
            mimeType: $mimeType,
        );
    }

    protected function normalizeArrayInput(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $trimmed = trim($value);

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return array_values($decoded);
        }

        if (str_contains($trimmed, ',') || str_contains($trimmed, "\n")) {
            return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $trimmed) ?: [])));
        }

        return [$trimmed];
    }

    protected function extractHeaders(WebhookCall $webhookCall): array
    {
        $headers = $webhookCall->headers ?? [];

        return is_array($headers) ? $headers : [];
    }

    protected function extractProviderReferenceId(array $headers): ?string
    {
        $guid = data_get($headers, 'X-sms-guid.0')
            ?? data_get($headers, 'x-sms-guid.0')
            ?? data_get($headers, 'x_sms_guid.0');

        return filled($guid) ? (string) $guid : null;
    }

    protected function normalizePhoneNumber(?string $number): ?string
    {
        if (! filled($number)) {
            return null;
        }

        try {
            return formatPhoneNumber(
                $number,
                get_domain_setting('country') ?? 'US',
                PhoneNumberFormat::E164
            );
        } catch (Throwable) {
            $digits = preg_replace('/\D+/', '', (string) $number);

            return $digits ? '+' . $digits : null;
        }
    }

    protected function shouldSkipMediaUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension === 'smil';
    }

    protected function shouldSkipMimeType(string $mimeType): bool
    {
        $mimeType = strtolower(trim(explode(';', $mimeType)[0]));

        return in_array($mimeType, ['application/smil', 'application/smil+xml'], true);
    }

    protected function isInlineMediaReference(string $url): bool
    {
        return str_starts_with($url, 'commio-inline://');
    }

    protected function encodeInlineMediaReference(string $binary, string $originalName, ?string $mimeType = null): string
    {
        $payload = [
            'binary_base64' => base64_encode($binary),
            'original_name' => $originalName,
            'mime_type' => $mimeType ?: 'application/octet-stream',
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $encoded = rtrim(strtr(base64_encode($json ?: '{}'), '+/', '-_'), '=');

        return 'commio-inline://' . $encoded;
    }

    protected function decodeInlineMediaReference(string $url): array
    {
        $encoded = substr($url, strlen('commio-inline://'));
        $encoded .= str_repeat('=', (4 - (strlen($encoded) % 4)) % 4);

        $json = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($json === false) {
            throw new \RuntimeException('Invalid Commio inline media payload');
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new \RuntimeException('Invalid Commio inline media JSON');
        }

        $binary = base64_decode((string) ($payload['binary_base64'] ?? ''), true);

        if ($binary === false) {
            throw new \RuntimeException('Invalid Commio inline media binary');
        }

        return [
            'binary' => $binary,
            'original_name' => (string) ($payload['original_name'] ?? 'attachment.bin'),
            'mime_type' => (string) ($payload['mime_type'] ?? 'application/octet-stream'),
        ];
    }

    protected function summarizeMediaReference(string $url): string
    {
        return $this->isInlineMediaReference($url)
            ? 'commio-inline://[encoded-payload]'
            : $url;
    }

    protected function extractFilenameFromUrl(string $url, string $mimeType): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $filename = basename($path);

        if ($filename && str_contains($filename, '.')) {
            return $filename;
        }

        return 'attachment.' . $this->extensionFromMimeType($mimeType);
    }

    protected function extensionFromMimeType(string $mimeType): string
    {
        return match (strtolower(trim(explode(';', $mimeType)[0]))) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'audio/aac' => 'aac',
            'audio/mp3', 'audio/mpeg', 'audio/mpg' => 'mp3',
            'audio/mp4', 'audio/mp4-latm', 'audio/3gpp' => 'm4a',
            'application/ogg', 'audio/ogg' => 'ogg',
            'video/h263' => '3gp',
            'video/m4v' => 'm4v',
            'video/mp4', 'video/mpeg4' => 'mp4',
            'video/mpeg' => 'mpeg',
            'video/webm' => 'webm',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'application/smil', 'application/smil+xml' => 'smil',
            default => 'bin',
        };
    }
}