<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Data\DownloadedMediaData;
use App\Services\Messaging\Data\InboundMessageEventData;
use Illuminate\Support\Facades\Http;
use libphonenumber\PhoneNumberFormat;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

class BulkVSWebhookParser implements MessagingWebhookParser
{
    /**
     * @return iterable<object>
     */
    public function parse(WebhookCall $webhookCall): iterable
    {
        $payload = $webhookCall->payload ?? [];

        messaging_webhook_debug('BulkVSWebhookParser parse() started', [
            'payload' => $this->summarizePayload($payload),
        ]);

        if (! $this->isInboundMessagePayload($payload)) {
            messaging_webhook_debug('BulkVSWebhookParser ignored payload', [
                'reason' => 'payload_did_not_match_inbound_shape',
            ]);

            return;
        }

        $mediaUrls = $this->extractMediaUrls($payload);
        $text = $this->extractText($payload);

        $event = InboundMessageEventData::from([
            'provider' => 'bulkvs',
            'providerReferenceId' => null,
            'from' => (string) $this->normalizePhoneNumber($payload['From'] ?? null),
            'to' => collect($payload['To'] ?? [])
                ->map(fn ($number) => $this->normalizePhoneNumber(is_scalar($number) ? (string) $number : null))
                ->filter()
                ->values()
                ->all(),
            'text' => $text,
            'mediaUrls' => $mediaUrls,
            'providerEvent' => ! empty($mediaUrls) ? 'incoming_mms' : 'incoming_sms',
        ]);

        messaging_webhook_debug('BulkVSWebhookParser inbound event parsed', [
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
        messaging_webhook_debug('BulkVSWebhookParser downloadMedia() started', [
            'url' => $url,
        ]);

        $response = Http::timeout(60)
            ->accept('*/*')
            ->get($url);

        $response->throw();

        $binary = $response->body();
        $mimeType = $response->header('Content-Type') ?: 'application/octet-stream';
        $originalName = $this->extractFilenameFromUrl($url, $mimeType);

        messaging_webhook_debug('BulkVSWebhookParser downloadMedia() completed', [
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
        return filled($payload['From'] ?? null)
            && is_array($payload['To'] ?? null)
            && ! empty($payload['To']);
    }

    protected function extractText(array $payload): string
    {
        $message = trim((string) ($payload['Message'] ?? ''));

        if ($message !== '') {
            return $message;
        }

        $textAttachmentUrl = $this->extractTextAttachmentUrl($payload);

        if (! $textAttachmentUrl) {
            return '';
        }

        try {
            messaging_webhook_debug('BulkVSWebhookParser fetching text attachment for MMS body', [
                'url' => $textAttachmentUrl,
            ]);

            $response = Http::timeout(30)
                ->accept('text/plain,*/*')
                ->get($textAttachmentUrl);

            if (! $response->successful()) {
                messaging_webhook_debug('BulkVSWebhookParser text attachment fetch failed', [
                    'url' => $textAttachmentUrl,
                    'http_status' => $response->status(),
                ]);

                return '';
            }

            $text = trim($response->body());

            messaging_webhook_debug('BulkVSWebhookParser text attachment resolved', [
                'url' => $textAttachmentUrl,
                'text_length' => strlen($text),
            ]);

            return $text;
        } catch (Throwable $e) {
            messaging_webhook_debug('BulkVSWebhookParser text attachment exception', [
                'url' => $textAttachmentUrl,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    protected function extractMediaUrls(array $payload): array
    {
        return collect($payload['MediaURLs'] ?? [])
            ->map(function ($item) {
                if (! is_string($item)) {
                    return null;
                }

                $url = trim($item);

                if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                    return null;
                }

                if ($this->shouldSkipMediaUrl($url)) {
                    return null;
                }

                return $url;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function extractTextAttachmentUrl(array $payload): ?string
    {
        foreach (($payload['MediaURLs'] ?? []) as $item) {
            if (! is_string($item)) {
                continue;
            }

            $url = trim($item);

            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $path = parse_url($url, PHP_URL_PATH) ?: '';
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if ($extension === 'txt') {
                return $url;
            }
        }

        return null;
    }

    protected function shouldSkipMediaUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['smil', 'txt'], true);
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
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'audio/mpeg' => 'mp3',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            default => 'bin',
        };
    }

    protected function summarizePayload(array $payload): array
    {
        return [
            'keys' => array_keys($payload),
            'from' => $payload['From'] ?? null,
            'to_count' => is_array($payload['To'] ?? null) ? count($payload['To']) : 0,
            'message_length' => strlen((string) ($payload['Message'] ?? '')),
            'media_count' => is_array($payload['MediaURLs'] ?? null) ? count($payload['MediaURLs']) : 0,
        ];
    }
}