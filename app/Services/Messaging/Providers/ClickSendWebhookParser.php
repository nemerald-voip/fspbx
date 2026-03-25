<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Data\DeliveryStatusEventData;
use App\Services\Messaging\Data\DownloadedMediaData;
use App\Services\Messaging\Data\InboundMessageEventData;
use App\Services\Messaging\Providers\Concerns\ExtractsMediaFilename;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use libphonenumber\PhoneNumberFormat;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

class ClickSendWebhookParser implements MessagingWebhookParser
{
    use ExtractsMediaFilename;

    /**
     * @return iterable<object>
     */
    public function parse(WebhookCall $webhookCall): iterable
    {
        $payload = $webhookCall->payload ?? [];

        messaging_webhook_debug('ClickSend parser received payload', [
            'payload' => $this->summarizePayload($payload),
        ]);

        if ($this->isDeliveryReceiptPayload($payload)) {
            $data = DeliveryStatusEventData::from([
                'provider' => 'clicksend',
                'referenceId' => (string) $this->extractReceiptReferenceId($payload),
                'status' => $this->mapReceiptStatus($payload),
                'description' => $this->extractReceiptDescription($payload),
                'providerEvent' => 'delivery_receipt',
            ]);

            messaging_webhook_debug('ClickSend parser yielding status event', [
                'reference_id' => $data->referenceId,
                'status' => $data->status,
                'description' => $data->description,
            ]);

            yield $data;

            return;
        }

        if ($this->isInboundMessagePayload($payload)) {
            $data = InboundMessageEventData::from([
                'provider' => 'clicksend',
                'providerReferenceId' => $this->extractInboundReferenceId($payload),
                'from' => (string) $this->extractFrom($payload),
                'to' => $this->extractTo($payload),
                'text' => (string) $this->extractText($payload),
                'mediaUrls' => $this->extractMediaUrls($payload),
                'providerEvent' => 'inbound_message',
            ]);

            messaging_webhook_debug('ClickSend parser yielding inbound event', [
                'reference_id' => $data->providerReferenceId,
                'from' => $data->from,
                'to' => $data->to,
                'media_count' => count($data->mediaUrls),
            ]);

            yield $data;

            return;
        }

        messaging_webhook_debug('ClickSend parser found no matching payload shape');
    }

    public function downloadMedia(string $url): DownloadedMediaData
    {
        messaging_webhook_debug('ClickSend downloadMedia started', [
            'url' => $url,
        ]);

        $response = Http::timeout(60)->get($url);
        $response->throw();

        $body = $response->body();

        $originalName = $this->extractFilenameFromContentDisposition(
            $response->header('Content-Disposition')
        );

        if (! $originalName) {
            $originalName = basename(parse_url($url, PHP_URL_PATH) ?: '') ?: 'attachment';
        }

        messaging_webhook_debug('ClickSend downloadMedia completed', [
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
        return filled($this->extractFrom($payload))
            && ! empty($this->extractTo($payload))
            && (
                array_key_exists('body', $payload)
                || array_key_exists('message', $payload)
                || array_key_exists('text', $payload)
                || ! empty($this->extractMediaUrls($payload))
            );
    }

    protected function isDeliveryReceiptPayload(array $payload): bool
    {
        return filled($this->extractReceiptReferenceId($payload))
            && (
                array_key_exists('status_code', $payload)
                || array_key_exists('status_text', $payload)
                || array_key_exists('error_text', $payload)
                || array_key_exists('status', $payload)
            );
    }

    protected function extractInboundReferenceId(array $payload): ?string
    {
        return $payload['message_id']
            ?? $payload['id']
            ?? null;
    }

    protected function extractReceiptReferenceId(array $payload): ?string
    {
        return $payload['message_id']
            ?? $payload['data']['message_id']
            ?? null;
    }

    protected function extractFrom(array $payload): ?string
    {
        $value = $payload['from']
            ?? $payload['sender']
            ?? $payload['msisdn']
            ?? null;

        return $this->normalizePhoneNumber($value);
    }

    protected function extractTo(array $payload): array
    {
        $value = $payload['to']
            ?? $payload['destination']
            ?? $payload['number']
            ?? null;

        return collect(Arr::wrap($value))
            ->map(fn($item) => $this->normalizePhoneNumber(is_scalar($item) ? (string) $item : null))
            ->filter(fn($item) => filled($item))
            ->values()
            ->all();
    }

    protected function extractText(array $payload): ?string
    {
        $originalText = $payload['original_body']
            ?? $payload['originalmessage']
            ?? null;

        if (is_string($originalText) && trim($originalText) !== '') {
            return trim($originalText);
        }

        $body = $payload['body']
            ?? $payload['message']
            ?? $payload['text']
            ?? '';

        if (is_string($body) && filter_var(trim($body), FILTER_VALIDATE_URL)) {
            return '';
        }

        return is_string($body) ? trim($body) : '';
    }

    protected function extractMediaUrls(array $payload): array
    {
        $candidates = [
            $payload['media'] ?? null,
            $payload['mediaUrls'] ?? null,
            $payload['media_urls'] ?? null,
            $payload['media_url'] ?? null,
            $payload['media_file'] ?? null,
            $payload['_media_file_url'] ?? null,
            $payload['attachment'] ?? null,
            $payload['attachments'] ?? null,
            $payload['files'] ?? null,
            $payload['body'] ?? null,
            $payload['message'] ?? null,
        ];

        return collect($candidates)
            ->flatten(1)
            ->map(function ($item) {
                if (is_string($item)) {
                    $item = trim($item);

                    return filter_var($item, FILTER_VALIDATE_URL) ? $item : null;
                }

                if (is_array($item)) {
                    $url = $item['url']
                        ?? $item['href']
                        ?? $item['mediaUrl']
                        ?? $item['media_url']
                        ?? $item['file']
                        ?? $item['file_url']
                        ?? null;

                    return is_string($url) && filter_var(trim($url), FILTER_VALIDATE_URL)
                        ? trim($url)
                        : null;
                }

                return null;
            })
            ->filter()
            ->reject(fn($url) => $this->shouldSkipMediaUrl($url))
            ->unique()
            ->values()
            ->all();
    }

    protected function shouldSkipMediaUrl(string $mediaUrl): bool
    {
        $path = parse_url($mediaUrl, PHP_URL_PATH) ?? '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension === 'smil';
    }

    protected function mapReceiptStatus(array $payload): string
    {
        $status = strtolower((string) (
            $payload['status']
            ?? $payload['status_text']
            ?? $payload['data']['status']
            ?? $payload['data']['status_text']
            ?? ''
        ));

        $statusCode = (string) (
            $payload['status_code']
            ?? $payload['data']['status_code']
            ?? ''
        );

        if (
            str_contains($status, 'failed')
            || str_contains($status, 'error')
            || str_contains($status, 'undeliver')
            || str_contains($status, 'reject')
            || str_contains($status, 'cancel')
        ) {
            return 'failed';
        }

        if (
            $statusCode === '201'
            || str_contains($status, 'received on handset')
            || str_contains($status, 'delivered')
            || str_contains($status, 'success')
            || str_contains($status, 'sent')
            || str_contains($status, 'completed')
        ) {
            return 'delivered';
        }

        return 'delivered';
    }

    protected function extractReceiptDescription(array $payload): ?string
    {
        return $payload['status_text']
            ?? $payload['error_text']
            ?? $payload['message']
            ?? $payload['data']['status_text']
            ?? $payload['data']['error_text']
            ?? null;
    }

    protected function normalizePhoneNumber(?string $number): ?string
    {
        if (! filled($number)) {
            return null;
        }

        $number = trim((string) $number);
        $countryCode = get_domain_setting('country', $domain_uuid = null) ?? 'US';

        try {
            return formatPhoneNumber($number, $countryCode, PhoneNumberFormat::E164);
        } catch (Throwable) {
            $digits = preg_replace('/\D+/', '', $number);

            return $digits !== '' ? '+' . $digits : null;
        }
    }

    protected function summarizePayload(array $payload): array
    {
        $summary = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $summary[$key] = [
                    'type' => 'array',
                    'keys' => array_keys($value),
                    'count' => count($value),
                ];

                continue;
            }

            if (is_string($value)) {
                $summary[$key] = [
                    'type' => 'string',
                    'length' => strlen($value),
                    'preview' => strlen($value) > 150
                        ? substr($value, 0, 150) . '...'
                        : $value,
                ];

                continue;
            }

            $summary[$key] = [
                'type' => gettype($value),
                'value' => $value,
            ];
        }

        return $summary;
    }
}
