<?php

namespace App\Services\Messaging\Outbound\Providers;

use App\Models\Messages;
use App\Services\Messaging\Outbound\Contracts\OutboundProviderInterface;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;
use Illuminate\Support\Facades\Http;
use Throwable;

class CommioOutboundProvider implements OutboundProviderInterface
{
    public function send(Messages $message): OutboundSendResultData
    {
        messaging_webhook_debug('CommioOutboundProvider send() started', [
            'message_uuid' => $message->message_uuid,
            'source' => $message->source,
            'destination' => $message->destination,
            'type' => $message->type,
        ]);

        $mediaUrls = $this->buildMediaUrls($message);

        messaging_webhook_debug('CommioOutboundProvider media resolved', [
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

        $baseUrl = rtrim((string) config('commio.base_url'), '/');
        $endpoint = (string) config('commio.send_endpoint');
        $username = (string) config('commio.username');
        $token = (string) config('commio.token');
        $timeout = (int) config('commio.timeout', 30);

        if ($baseUrl === '' || $endpoint === '' || $username === '' || $token === '') {
            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => 'Commio credentials are not configured',
            ]);
        }

        if ($hasMedia && count($mediaUrls) > 1) {
            messaging_webhook_debug('CommioOutboundProvider extra MMS media ignored', [
                'message_uuid' => $message->message_uuid,
                'media_count' => count($mediaUrls),
                'used_media_url' => $mediaUrls[0],
            ]);
        }

        $payload = $hasMedia
            ? $this->buildMmsPayload($message, $text, $mediaUrls[0] ?? null)
            : $this->buildSmsPayload($message, $text);

        messaging_webhook_debug('CommioOutboundProvider request payload', [
            'message_uuid' => $message->message_uuid,
            'url' => $baseUrl . $endpoint,
            'payload' => $payload,
        ]);

        try {
            $response = Http::withBasicAuth($username, $token)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->post($baseUrl . $endpoint, $payload);

            $result = $response->json();

            messaging_webhook_debug('CommioOutboundProvider response received', [
                'message_uuid' => $message->message_uuid,
                'http_status' => $response->status(),
                'response' => $result,
            ]);

            if ($response->successful()) {
                return OutboundSendResultData::from([
                    'success' => true,
                    'status' => 'queued',
                    'providerReferenceId' => $this->extractReferenceId($result),
                    'providerResponse' => is_array($result) ? $result : [],
                ]);
            }

            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => $this->extractError($result, $response->body()),
                'providerReferenceId' => $this->extractReferenceId($result),
                'providerResponse' => is_array($result) ? $result : [],
            ]);
        } catch (Throwable $e) {
            messaging_webhook_debug('CommioOutboundProvider exception', [
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
            'channel' => 'SMS',
            'from' => $this->formatDid($message->source),
            'to' => $this->formatDid($message->destination),
            'content' => [
                'text' => $text,
            ],
        ];
    }

    protected function buildMmsPayload(Messages $message, string $text, ?string $mediaUrl): array
    {
        $content = [
            'file' => $mediaUrl,
        ];

        if ($text !== '') {
            $content['text'] = $text;
        }

        return [
            'channel' => 'MMS',
            'from' => $this->formatDid($message->source),
            'to' => $this->formatDid($message->destination),
            'content' => $content,
        ];
    }

    protected function extractReferenceId(mixed $result): ?string
    {
        if (!is_array($result)) {
            return null;
        }

        return $result['guid']
            ?? $result['id']
            ?? $result['message_id']
            ?? $result['request_id']
            ?? null;
    }

    protected function extractError(mixed $result, string $fallback): string
    {
        if (is_array($result)) {
            return $result['message']
                ?? $result['error']
                ?? (isset($result['errors']) ? json_encode($result['errors']) : null)
                ?? json_encode($result);
        }

        return $fallback;
    }

    protected function buildMediaUrls(Messages $message): array
    {
        return collect($this->decodeMedia($message->media))
            ->map(function ($item) {
                if (is_string($item) && !empty($item)) {
                    return trim($item);
                }

                if (is_array($item) && !empty($item['access_path'])) {
                    return filter_var($item['access_path'], FILTER_VALIDATE_URL)
                        ? $item['access_path']
                        : url($item['access_path']);
                }

                if (is_array($item) && !empty($item['url'])) {
                    return $item['url'];
                }

                return null;
            })
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->filter(fn ($url) => filter_var($url, FILTER_VALIDATE_URL))
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