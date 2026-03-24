<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Data\DeliveryStatusEventData;
use App\Services\Messaging\Data\DownloadedMediaData;
use App\Services\Messaging\Data\InboundMessageEventData;
use App\Services\Messaging\Providers\Concerns\ExtractsMediaFilename;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Spatie\WebhookClient\Models\WebhookCall;

class BandwidthWebhookParser implements MessagingWebhookParser
{
    use ExtractsMediaFilename;

    /**
     * @return iterable<object>
     */
    public function parse(WebhookCall $webhookCall): iterable
    {
        $payload = $webhookCall->payload ?? [];

        messaging_webhook_debug('Bandwidth parser received payload', [
            'payload' => $payload,
        ]);

        foreach (Arr::wrap($payload) as $event) {
            $type = data_get($event, 'type');

            messaging_webhook_debug('Bandwidth parser inspecting event', [
                'type' => $type,
            ]);

            if ($type === 'message-received') {
                $data = InboundMessageEventData::from([
                    'provider' => 'bandwidth',
                    'providerReferenceId' => data_get($event, 'message.id'),
                    'from' => (string) data_get($event, 'message.from', ''),
                    'to' => $this->normalizeToList(data_get($event, 'message.to', [])),
                    'text' => (string) data_get($event, 'message.text', ''),
                    'mediaUrls' => $this->normalizeMediaUrls(data_get($event, 'message.media', [])),
                    'providerEvent' => $type,
                ]);

                messaging_webhook_debug('Bandwidth parser yielding inbound event', [
                    'reference_id' => $data->providerReferenceId,
                    'from' => $data->from,
                    'to' => $data->to,
                    'media_count' => count($data->mediaUrls),
                    'media_urls' => $data->mediaUrls,
                ]);

                yield $data;

                continue;
            }

            if (in_array($type, ['message-delivered', 'message-failed'], true)) {
                $data = DeliveryStatusEventData::from([
                    'provider' => 'bandwidth',
                    'referenceId' => (string) data_get($event, 'message.id', ''),
                    'status' => $type === 'message-delivered' ? 'delivered' : 'failed',
                    'description' => $this->buildStatusDescription($event),
                    'providerEvent' => $type,
                ]);

                messaging_webhook_debug('Bandwidth parser yielding status event', [
                    'reference_id' => $data->referenceId,
                    'status' => $data->status,
                ]);

                yield $data;
            }
        }
    }

    public function downloadMedia(string $url): DownloadedMediaData
    {
        messaging_webhook_debug('Bandwidth downloadMedia started', [
            'url' => $url,
        ]);

        $response = Http::withBasicAuth(
            config('bandwidth.api_token'),
            config('bandwidth.api_secret')
        )->timeout(30)->get($url);

        $response->throw();

        $body = $response->body();

        if ($body === '' || $body === null) {
            throw new \RuntimeException('Downloaded empty Bandwidth MMS attachment: ' . $url);
        }

        $originalName = $this->extractFilenameFromContentDisposition(
            $response->header('Content-Disposition')
        );

        if (!$originalName) {
            $originalName = basename(parse_url($url, PHP_URL_PATH) ?: '') ?: 'attachment';
        }

        messaging_webhook_debug('Bandwidth downloadMedia completed', [
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

    protected function normalizeToList(mixed $value): array
    {
        return collect(Arr::wrap($value))
            ->filter(fn ($item) => filled($item))
            ->map(fn ($item) => (string) $item)
            ->values()
            ->all();
    }

    protected function normalizeMediaUrls(mixed $value): array
    {
        return collect(Arr::wrap($value))
            ->map(function ($item) {
                if (is_string($item)) {
                    return $item;
                }

                if (is_array($item)) {
                    return $item['url'] ?? $item['mediaUrl'] ?? null;
                }

                return null;
            })
            ->filter(fn ($item) => filled($item))
            ->reject(fn ($url) => $this->shouldSkipBandwidthMediaUrl($url))
            ->values()
            ->all();
    }

    protected function shouldSkipBandwidthMediaUrl(string $mediaUrl): bool
    {
        $path = parse_url($mediaUrl, PHP_URL_PATH) ?? '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension === 'smil';
    }

    protected function buildStatusDescription(array $event): ?string
    {
        return data_get($event, 'description')
            ?? data_get($event, 'errorMessage')
            ?? data_get($event, 'message.description')
            ?? null;
    }
}