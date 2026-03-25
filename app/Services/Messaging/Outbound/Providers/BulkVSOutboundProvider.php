<?php

namespace App\Services\Messaging\Outbound\Providers;

use App\Models\Messages;
use App\Services\Messaging\Outbound\Contracts\OutboundProviderInterface;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;
use Illuminate\Support\Facades\Http;
use Throwable;

class BulkVSOutboundProvider implements OutboundProviderInterface
{
    public function send(Messages $message): OutboundSendResultData
    {
        messaging_webhook_debug('BulkVSOutboundProvider send() started', [
            'message_uuid' => $message->message_uuid,
            'source' => $message->source,
            'destination' => $message->destination,
            'type' => $message->type,
        ]);

        $mediaUrls = $this->buildMediaUrls($message);

        messaging_webhook_debug('BulkVSOutboundProvider media resolved', [
            'message_uuid' => $message->message_uuid,
            'media_count' => count($mediaUrls),
            'media_urls' => $mediaUrls,
        ]);

        $text = trim((string) ($message->message ?? ''));
        $hasMedia = ! empty($mediaUrls);

        if ($text === '' && ! $hasMedia) {
            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => 'Message has no text or media',
            ]);
        }

        $baseUrl = rtrim((string) config('bulkvs.base_url'), '/');
        $username = (string) config('bulkvs.username');
        $password = (string) config('bulkvs.password');
        $basicAuthHeader = (string) config('bulkvs.basic_auth_header');

        if ($baseUrl === '') {
            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => 'BulkVS base URL is not configured',
            ]);
        }

        if ($basicAuthHeader === '' && ($username === '' || $password === '')) {
            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => 'BulkVS credentials are not configured',
            ]);
        }

        $payload = [
            'From' => (string) $message->source,
            'To' => [(string) $message->destination],
            'Message' => $text,
        ];

        if ($hasMedia) {
            $payload['MediaURLs'] = $mediaUrls;
        }

        messaging_webhook_debug('BulkVSOutboundProvider request payload', [
            'message_uuid' => $message->message_uuid,
            'payload' => $payload,
        ]);

        try {
            $request = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('bulkvs.timeout', 30));

            if ($basicAuthHeader !== '') {
                $request = $request->withHeaders([
                    'Authorization' => str_starts_with($basicAuthHeader, 'Basic ')
                        ? $basicAuthHeader
                        : 'Basic ' . $basicAuthHeader,
                ]);
            } else {
                $request = $request->withBasicAuth($username, $password);
            }

            $response = $request->post($baseUrl . '/messageSend', $payload);

            $result = $response->json();

            messaging_webhook_debug('BulkVSOutboundProvider response received', [
                'message_uuid' => $message->message_uuid,
                'http_status' => $response->status(),
                'response' => $result,
            ]);

            if ($this->isSuccessfulResponse($response->successful(), $result)) {
                return OutboundSendResultData::from([
                    'success' => true,
                    'status' => 'success',
                    'providerReferenceId' => $result['RefId'] ?? null,
                    'providerResponse' => is_array($result) ? $result : [],
                ]);
            }

            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => $this->extractError($result, $response->body()),
                'providerReferenceId' => is_array($result) ? ($result['RefId'] ?? null) : null,
                'providerResponse' => is_array($result) ? $result : [],
            ]);
        } catch (Throwable $e) {
            messaging_webhook_debug('BulkVSOutboundProvider exception', [
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

    protected function isSuccessfulResponse(bool $httpSuccessful, mixed $result): bool
    {
        if (! $httpSuccessful || ! is_array($result)) {
            return false;
        }

        $firstResult = $result['Results'][0] ?? null;

        if (! is_array($firstResult)) {
            return false;
        }

        return strtoupper((string) ($firstResult['Status'] ?? '')) === 'SUCCESS';
    }

    protected function extractError(mixed $result, string $fallback): string
    {
        if (is_array($result)) {
            return $result['Results'][0]['Status']
                ?? $result['Message']
                ?? $result['error']
                ?? json_encode($result);
        }

        return $fallback;
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
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->filter(fn ($url) => filter_var($url, FILTER_VALIDATE_URL))
            ->filter(fn ($url) => $this->isSupportedMmsMediaUrl($url))
            ->unique()
            ->values()
            ->all();
    }

    protected function isSupportedMmsMediaUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png'], true);
    }

    protected function decodeMedia($media): array
    {
        if (is_string($media)) {
            $decoded = json_decode($media, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($media) ? $media : [];
    }
}