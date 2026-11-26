<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Data\DeliveryStatusEventData;
use App\Services\Messaging\Data\DownloadedMediaData;
use App\Services\Messaging\Data\InboundMessageEventData;
use Spatie\WebhookClient\Models\WebhookCall;

class VoipInnovationsWebhookParser implements MessagingWebhookParser
{
    public function parse(WebhookCall $webhookCall): iterable
    {
        $payload = $webhookCall->payload ?? [];
        if (!is_array($payload)) {
            return;
        }

        $type = strtoupper((string) ($payload['messageType'] ?? ''));
        $uuid = trim((string) ($payload['uuid'] ?? ''));
        if ($uuid === '') {
            return;
        }

        if (in_array($type, ['SMS', 'MMS'], true)) {
            $from = $this->normalizeNumber($payload['from'] ?? null);
            $to = $this->normalizeNumber($payload['to'] ?? null);
            if ($from === null || $to === null) {
                return;
            }

            $mediaUrls = [];
            if ($type === 'MMS') {
                foreach (($payload['files'] ?? []) as $name => $base64) {
                    if (!is_string($base64) || strlen($base64) > 2800000) {
                        throw new \UnexpectedValueException('Invalid or oversized VI MMS attachment');
                    }
                    $bytes = base64_decode($base64, true);
                    if ($bytes === false || strlen($bytes) > 2100000) {
                        throw new \UnexpectedValueException('Invalid or oversized VI MMS binary');
                    }
                    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) $name));
                    $filename = substr($filename ?: 'attachment.bin', 0, 128);
                    $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
                    // Message media is served inline. Treat other formats as downloads.
                    $mime = in_array($detectedMime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)
                        ? $detectedMime
                        : 'application/octet-stream';
                    $mediaUrls[] = $this->inlineReference($bytes, $filename, $mime);
                }
            }

            yield InboundMessageEventData::from([
                'provider' => 'voipinnovations',
                'providerReferenceId' => $uuid,
                'from' => $from,
                'to' => [$to],
                'text' => (string) ($payload['text'] ?? ''),
                'mediaUrls' => $mediaUrls,
                'providerEvent' => $type === 'MMS' ? 'incoming_mms' : 'incoming_sms',
                'isMms' => $type === 'MMS',
            ]);
            return;
        }

        if (!in_array($type, ['DELIVERY_RECEIPT', 'STATUS_UPDATE', 'DELIVERED', 'FAILED', 'DLR'], true)) {
            return;
        }
        $rawStatus = strtolower(trim((string) ($payload['status'] ?? $type)));
        $status = match ($rawStatus) {
            'delivered', 'success' => 'delivered',
            'failed', 'undelivered', 'rejected' => 'failed',
            default => null,
        };
        if ($status === null) {
            return;
        }

        yield DeliveryStatusEventData::from([
            'provider' => 'voipinnovations',
            'referenceId' => $uuid,
            'status' => $status,
            'rawStatus' => $rawStatus,
            'providerEvent' => $type,
            'recipient' => $this->normalizeNumber($payload['to'] ?? null),
            // VI done_date format has not been verified to be ISO8601;
            // omit rather than pass an incorrectly formatted timestamp.
            'occurredAt' => null,
        ]);
    }

    public function downloadMedia(string $url): DownloadedMediaData
    {
        if (!str_starts_with($url, 'vi-inline://')) {
            throw new \InvalidArgumentException('Unsupported VI MMS media reference');
        }
        $encoded = substr($url, strlen('vi-inline://'));
        $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        $data = $json === false ? null : json_decode($json, true);
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Malformed VI inline media reference');
        }
        $bytes = base64_decode((string) ($data['binary_base64'] ?? ''), true);
        if ($bytes === false || strlen($bytes) > 2100000) {
            throw new \UnexpectedValueException('Malformed VI media data');
        }
        return DownloadedMediaData::from([
            'binary' => $bytes,
            'originalName' => (string) ($data['original_name'] ?? 'attachment.bin'),
            'mimeType' => (string) ($data['mime_type'] ?? 'application/octet-stream'),
            'size' => strlen($bytes),
            'sourceUrl' => 'vi-inline://[redacted]',
        ]);
    }

    private function inlineReference(string $bytes, string $filename, string $mime): string
    {
        $data = json_encode([
            'binary_base64' => base64_encode($bytes),
            'original_name' => $filename,
            'mime_type' => $mime,
        ], JSON_THROW_ON_ERROR);
        return 'vi-inline://' . rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function normalizeNumber(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^\+?[1-9][0-9]{9,14}$/D', trim($value))) {
            return null;
        }

        $number = trim($value);
        return strlen($number) === 10 ? '+1' . $number : '+' . ltrim($number, '+');
    }
}
