<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Data\DownloadedMediaData;
use App\Services\Messaging\Data\InboundMessageEventData;
use Illuminate\Support\Facades\Http;
use libphonenumber\PhoneNumberFormat;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

class VoipMsWebhookParser implements MessagingWebhookParser
{
    /**
     * @return iterable<object>
     */
    public function parse(WebhookCall $webhookCall): iterable
    {
        $root = $webhookCall->payload ?? [];
        $eventType = (string) data_get($root, 'data.event_type', '');
        $payload = data_get($root, 'data.payload', []);

        messaging_webhook_debug('VoipMsWebhookParser parse() started', [
            'event_type' => $eventType,
            'webhook_event_id' => data_get($root, 'data.id'),
            'message_id' => data_get($payload, 'id'),
        ]);

        if (! is_array($payload)) {
            messaging_webhook_debug('VoipMsWebhookParser invalid payload shape', [
                'event_type' => $eventType,
            ]);

            return;
        }

        if ($eventType !== 'message.received') {
            messaging_webhook_debug('VoipMsWebhookParser ignored event', [
                'event_type' => $eventType,
            ]);

            return;
        }

        $mediaUrls = $this->extractMediaUrls($payload);

        $event = InboundMessageEventData::from([
            'provider' => 'voipms',
            'providerReferenceId' => filled($payload['id'] ?? null) ? (string) $payload['id'] : null,
            'from' => (string) $this->normalizePhoneNumber(data_get($payload, 'from.phone_number')),
            'to' => collect(data_get($payload, 'to', []))
                ->map(fn ($item) => $this->normalizePhoneNumber($item['phone_number'] ?? null))
                ->filter()
                ->values()
                ->all(),
            'text' => trim((string) ($payload['text'] ?? '')),
            'mediaUrls' => $mediaUrls,
            'providerEvent' => strtolower((string) ($payload['type'] ?? '')) === 'mms'
                ? 'incoming_mms'
                : 'incoming_sms',
        ]);

        messaging_webhook_debug('VoipMsWebhookParser inbound event parsed', [
            'provider_reference_id' => $event->providerReferenceId,
            'from' => $event->from,
            'to' => $event->to,
            'text_length' => strlen((string) $event->text),
            'media_count' => count($event->mediaUrls),
            'type' => $payload['type'] ?? null,
        ]);

        yield $event;
    }

    public function downloadMedia(string $url): DownloadedMediaData
    {
        messaging_webhook_debug('VoipMsWebhookParser downloadMedia() started', [
            'url' => $url,
        ]);

        $response = Http::timeout(60)
            ->accept('*/*')
            ->get($url);

        $response->throw();

        $binary = $response->body();
        $mimeType = $response->header('Content-Type') ?: 'application/octet-stream';
        $originalName = $this->extractFilenameFromUrl($url, $mimeType);

        messaging_webhook_debug('VoipMsWebhookParser downloadMedia() completed', [
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

    protected function extractMediaUrls(array $payload): array
    {
        return collect($payload['media'] ?? [])
            ->map(function ($item) {
                if (is_string($item) && filter_var($item, FILTER_VALIDATE_URL)) {
                    return $item;
                }

                if (is_array($item) && ! empty($item['url']) && filter_var($item['url'], FILTER_VALIDATE_URL)) {
                    return $item['url'];
                }

                return null;
            })
            ->filter()
            ->reject(fn ($url) => $this->shouldSkipMediaUrl($url))
            ->unique()
            ->values()
            ->all();
    }

    protected function shouldSkipMediaUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension === 'smil';
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
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'mp4',
            'video/mp4' => 'mp4',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            default => 'bin',
        };
    }
}