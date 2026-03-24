<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Data\DeliveryStatusEventData;
use App\Services\Messaging\Data\DownloadedMediaData;
use App\Services\Messaging\Data\InboundMessageEventData;
use App\Services\Messaging\Providers\Concerns\ExtractsMediaFilename;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Spatie\WebhookClient\Models\WebhookCall;

class SinchWebhookParser implements MessagingWebhookParser
{
    use ExtractsMediaFilename;

    /**
     * @return iterable<object>
     */
    public function parse(WebhookCall $webhookCall): iterable
    {
        $payload = $webhookCall->payload ?? [];
        $type = data_get($payload, 'type');
        $deliveryReceipt = data_get($payload, 'deliveryReceipt');

        messaging_webhook_debug('Sinch parser received payload', [
            'type' => $type,
            'deliveryReceipt' => $deliveryReceipt,
            'payload' => $payload,
        ]);

        // Inbound MO message
        if ($this->isInboundMessagePayload($payload)) {
            $data = InboundMessageEventData::from([
                'provider' => 'sinch',
                'providerReferenceId' => $this->extractReferenceId($payload),
                'from' => (string) $this->extractFrom($payload),
                'to' => $this->extractTo($payload),
                'text' => (string) $this->extractText($payload),
                'mediaUrls' => $this->extractMediaUrls($payload),
                'providerEvent' => $type ?: 'inbound_message',
            ]);

            messaging_webhook_debug('Sinch parser yielding inbound event', [
                'reference_id' => $data->providerReferenceId,
                'from' => $data->from,
                'to' => $data->to,
                'media_count' => count($data->mediaUrls),
            ]);

            yield $data;

            return;
        }

        // Delivery receipt / status update
        if ($this->isDeliveryReceiptPayload($payload)) {
            $data = DeliveryStatusEventData::from([
                'provider' => 'sinch',
                'referenceId' => (string) $this->extractReferenceId($payload),
                'status' => $this->mapStatus($payload),
                'description' => $this->extractStatusDescription($payload),
                'providerEvent' => $type ?: 'delivery_receipt',
            ]);

            messaging_webhook_debug('Sinch parser yielding status event', [
                'reference_id' => $data->referenceId,
                'status' => $data->status,
            ]);

            yield $data;

            return;
        }

        messaging_webhook_debug('Sinch parser found no matching payload shape');
    }

    public function downloadMedia(string $url): DownloadedMediaData
    {
        messaging_webhook_debug('Sinch downloadMedia started', [
            'url' => $url,
        ]);

        $response = Http::get($url);
        $response->throw();

        $body = $response->body();

        $originalName = $this->extractFilenameFromContentDisposition(
            $response->header('Content-Disposition')
        );

        if (!$originalName) {
            $originalName = basename(parse_url($url, PHP_URL_PATH) ?: '') ?: null;
        }

        messaging_webhook_debug('Sinch downloadMedia completed', [
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
        // Old typed format
        if (in_array(data_get($payload, 'type'), ['mo_text', 'mo_media'], true)) {
            return true;
        }

        // Current observed Sinch inbound format
        return data_get($payload, 'deliveryReceipt') === false
            && filled($this->extractFrom($payload))
            && !empty($this->extractTo($payload));
    }

    protected function isDeliveryReceiptPayload(array $payload): bool
    {
        if (in_array(data_get($payload, 'type'), ['message_delivery', 'message_failed', 'delivery_report'], true)) {
            return true;
        }

        return data_get($payload, 'deliveryReceipt') === true;
    }

    protected function extractReferenceId(array $payload): ?string
    {
        return data_get($payload, 'referenceId')
            ?? data_get($payload, 'message.id')
            ?? data_get($payload, 'id')
            ?? data_get($payload, 'messageId')
            ?? data_get($payload, 'event.message_id');
    }

    protected function extractFrom(array $payload): ?string
    {
        return data_get($payload, 'from')
            ?? data_get($payload, 'message.from')
            ?? data_get($payload, 'event.from');
    }

    protected function extractTo(array $payload): array
    {
        $to = data_get($payload, 'to')
            ?? data_get($payload, 'message.to')
            ?? data_get($payload, 'event.to');

        return collect(Arr::wrap($to))
            ->filter(fn($item) => filled($item))
            ->map(fn($item) => (string) $item)
            ->values()
            ->all();
    }

    protected function extractText(array $payload): ?string
    {
        return data_get($payload, 'text')
            ?? data_get($payload, 'message.text')
            ?? data_get($payload, 'event.text')
            ?? '';
    }

    protected function extractMediaUrls(array $payload): array
    {
        $media = data_get($payload, 'media')
            ?? data_get($payload, 'mediaUrls')
            ?? data_get($payload, 'message.media')
            ?? data_get($payload, 'message.mediaUrls')
            ?? data_get($payload, 'event.media')
            ?? data_get($payload, 'event.mediaUrls')
            ?? data_get($payload, 'media_urls')
            ?? [];

        return collect(Arr::wrap($media))
            ->map(function ($item) {
                if (is_string($item)) {
                    return $item;
                }

                if (is_array($item)) {
                    return $item['url']
                        ?? $item['href']
                        ?? $item['mediaUrl']
                        ?? $item['media_url']
                        ?? null;
                }

                return null;
            })
            ->filter(fn($item) => filled($item))
            ->reject(fn($url) => $this->shouldSkipSinchMediaUrl($url))
            ->values()
            ->all();
    }

    protected function shouldSkipSinchMediaUrl(string $mediaUrl): bool
    {
        $path = parse_url($mediaUrl, PHP_URL_PATH) ?? '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension === 'smil';
    }

    protected function mapStatus(array $payload): string
    {
        $type = data_get($payload, 'type');
        $status = strtolower((string) (
            data_get($payload, 'status')
            ?? data_get($payload, 'message.status')
            ?? data_get($payload, 'event.status')
            ?? ''
        ));

        if ($type === 'message_failed') {
            return 'failed';
        }

        if (in_array($status, ['delivered', 'delivery_report', 'successful', 'success'], true)) {
            return 'delivered';
        }

        if (in_array($status, ['failed', 'rejected', 'undelivered'], true)) {
            return 'failed';
        }

        // If it's a delivery receipt but no explicit failure is present, default to delivered
        if (data_get($payload, 'deliveryReceipt') === true) {
            return 'delivered';
        }

        return 'delivered';
    }

    protected function extractStatusDescription(array $payload): ?string
    {
        return data_get($payload, 'reason')
            ?? data_get($payload, 'description')
            ?? data_get($payload, 'error.message')
            ?? data_get($payload, 'message.reason')
            ?? null;
    }
}
