<?php

namespace App\Services\Messaging\Outbound\Providers;

use App\Models\Messages;
use App\Services\Messaging\Outbound\Contracts\OutboundProviderInterface;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;
use Illuminate\Support\Facades\Http;
use Throwable;

class VoipMsOutboundProvider implements OutboundProviderInterface
{
    public function send(Messages $message): OutboundSendResultData
    {
        messaging_webhook_debug('VoipMsOutboundProvider send() started', [
            'message_uuid' => $message->message_uuid,
            'source' => $message->source,
            'destination' => $message->destination,
            'type' => $message->type,
        ]);

        $mediaUrls = $this->buildMediaUrls($message);

        messaging_webhook_debug('VoipMsOutboundProvider media resolved', [
            'message_uuid' => $message->message_uuid,
            'media_count' => count($mediaUrls),
            'media_urls' => $mediaUrls,
        ]);

        $text = trim((string) ($message->message ?? ''));
        $hasMedia = !empty($mediaUrls);

        if ($text === '' && !$hasMedia) {
            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => 'Message has no text or media',
            ]);
        }

        $baseUrl = (string) config('voipms.base_url');
        $username = (string) config('voipms.api_username');
        $password = (string) config('voipms.api_password');
        $timeout = (int) config('voipms.timeout', 30);

        if ($baseUrl === '' || $username === '' || $password === '') {
            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => 'VoIP.ms credentials are not configured',
            ]);
        }

        $payload = $hasMedia
            ? $this->buildMmsPayload($message, $text, $mediaUrls)
            : $this->buildSmsPayload($message, $text);

        $payload['content_type'] = 'json';

        messaging_webhook_debug('VoipMsOutboundProvider request payload', [
            'message_uuid' => $message->message_uuid,
            'url' => $baseUrl . '?content_type=json',
            'payload' => $payload,
        ]);

        try {
            $multipart = collect($payload)
                ->map(function ($value, $name) {
                    return [
                        'name' => $name,
                        'contents' => (string) $value,
                    ];
                })
                ->values()
                ->all();

            $response = Http::acceptJson()
                ->timeout($timeout)
                ->asMultipart()
                ->post($baseUrl . '?content_type=json', $multipart);

            $result = $response->json();

            if (!is_array($result)) {
                parse_str((string) $response->body(), $parsed);
                $result = is_array($parsed) && !empty($parsed) ? $parsed : [];
            }

            messaging_webhook_debug('VoipMsOutboundProvider response received', [
                'message_uuid' => $message->message_uuid,
                'http_status' => $response->status(),
                'response' => $result,
                'raw_body' => is_array($result) && !empty($result) ? null : $response->body(),
            ]);

            if ($this->isSuccessfulResponse($response->successful(), $result, $hasMedia)) {
                return OutboundSendResultData::from([
                    'success' => true,
                    'status' => 'success',
                    'providerReferenceId' => $this->extractReferenceId($result, $hasMedia),
                    'providerResponse' => is_array($result) ? $result : [],
                ]);
            }

            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => $this->extractError($result, $response->body()),
                'providerReferenceId' => $this->extractReferenceId($result, $hasMedia),
                'providerResponse' => is_array($result) ? $result : [],
            ]);
        } catch (Throwable $e) {
            messaging_webhook_debug('VoipMsOutboundProvider exception', [
                'message_uuid' => $message->message_uuid,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'providerResponse' => [
                    'exception' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ]);
        }
    }

    protected function buildSmsPayload(Messages $message, string $text): array
    {
        return [
            'api_username' => (string) config('voipms.api_username'),
            'api_password' => (string) config('voipms.api_password'),
            'method' => 'sendSMS',
            'did' => $this->formatDid($message->source),
            'dst' => $this->formatDid($message->destination),
            'message' => $text,
            'content_type' => 'json',
        ];
    }

    protected function buildMmsPayload(Messages $message, string $text, array $mediaUrls): array
    {
        $payload = [
            'api_username' => (string) config('voipms.api_username'),
            'api_password' => (string) config('voipms.api_password'),
            'method' => 'sendMMS',
            'did' => $this->formatDid($message->source),
            'dst' => $this->formatDid($message->destination),
            'message' => $text,
            'content_type' => 'json',
        ];

        foreach (array_slice($mediaUrls, 0, 3) as $index => $url) {
            $payload['media' . ($index + 1)] = $url;
        }

        return $payload;
    }

    protected function isSuccessfulResponse(bool $httpSuccessful, array $result, bool $isMms): bool
    {
        if (! $httpSuccessful) {
            return false;
        }

        if (($result['status'] ?? null) !== 'success') {
            return false;
        }

        return $isMms
            ? ! empty($result['mms'])
            : ! empty($result['sms']);
    }

    protected function extractReferenceId(array $result, bool $isMms): ?string
    {
        $value = $isMms
            ? ($result['mms'] ?? null)
            : ($result['sms'] ?? null);

        return $value !== null ? (string) $value : null;
    }

    protected function extractError(array $result, string $fallback): string
    {
        return $result['error']
            ?? $result['message']
            ?? ($result['status'] ?? null && $result['status'] !== 'success' ? (string) $result['status'] : null)
            ?? ($fallback !== '' ? $fallback : 'VoIP.ms request failed');
    }

    protected function buildMediaUrls(Messages $message): array
    {
        return collect($this->decodeMedia($message->media))
            ->map(function ($item) {
                if (is_string($item) && ! empty($item)) {
                    return trim($item);
                }

                if (is_array($item) && ! empty($item['access_path'])) {
                    return filter_var($item['access_path'], FILTER_VALIDATE_URL)
                        ? $item['access_path']
                        : url($item['access_path']);
                }

                if (is_array($item) && ! empty($item['url'])) {
                    return $item['url'];
                }

                return null;
            })
            ->filter(fn($url) => is_string($url) && $url !== '')
            ->filter(fn($url) => filter_var($url, FILTER_VALIDATE_URL))
            ->unique()
            ->values()
            ->all();
    }

    protected function decodeMedia($media): array
    {
        if (is_string($media)) {
            $decoded = json_decode($media, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($media) ? $media : [];
    }

    protected function formatDid(?string $phoneNumber): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phoneNumber);

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return substr($digits, 1);
        }

        return $digits;
    }
}
