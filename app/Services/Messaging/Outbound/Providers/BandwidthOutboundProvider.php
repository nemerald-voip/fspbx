<?php

namespace App\Services\Messaging\Outbound\Providers;

use App\Models\Messages;
use App\Services\Messaging\Outbound\Contracts\OutboundProviderInterface;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;
use Illuminate\Support\Facades\Http;

class BandwidthOutboundProvider implements OutboundProviderInterface
{
    public function send(Messages $message): OutboundSendResultData
    {
        messaging_webhook_debug('BandwidthOutboundProvider send() started', [
            'message_uuid' => $message->message_uuid,
            'source' => $message->source,
            'destination' => $message->destination,
            'type' => $message->type,
        ]);

        $baseUrl = rtrim(config('bandwidth.message_base_url'), '/');
        $accountId = config('bandwidth.account_id');
        $applicationId = config('bandwidth.application_id');
        $apiToken = config('bandwidth.api_token');
        $apiSecret = config('bandwidth.api_secret');

        if (empty($baseUrl) || empty($accountId) || empty($applicationId) || empty($apiToken) || empty($apiSecret)) {
            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => 'Bandwidth credentials are not configured',
            ]);
        }

        $mediaUrls = $this->buildMediaUrls($message);
        messaging_webhook_debug('BandwidthOutboundProvider media resolved', [
            'message_uuid' => $message->message_uuid,
            'media_count' => count($mediaUrls),
            'media_urls' => $mediaUrls,
        ]);

        $hasMedia = !empty($mediaUrls);
        $text = trim((string) ($message->message ?? ''));

        if ($text === '' && !$hasMedia) {
            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => 'Message has no text or media',
            ]);
        }

        $url = $baseUrl . '/users/' . $accountId . '/messages';

        $payload = [
            'from' => (string) $message->source,
            'to' => [
                (string) $message->destination,
            ],
            'applicationId' => $applicationId,
            'tag' => (string) $message->message_uuid,
        ];

        if ($text !== '') {
            $payload['text'] = $text;
        }

        if ($hasMedia) {
            $payload['media'] = $mediaUrls;
        }

        messaging_webhook_debug('BandwidthOutboundProvider request payload', [
            'message_uuid' => $message->message_uuid,
            'payload' => $payload,
        ]);

        $credentials = $apiToken . ':' . $apiSecret;
        $authHeader = 'Basic ' . base64_encode($credentials);

        $response = Http::withHeaders([
            'Authorization' => $authHeader,
            'Content-Type' => 'application/json; charset=utf-8',
        ])->asJson()->timeout(30)->post($url, $payload);

        $result = $response->json();

        messaging_webhook_debug('BandwidthOutboundProvider response received', [
            'message_uuid' => $message->message_uuid,
            'http_status' => $response->status(),
            'response' => $result,
        ]);

        if ($response->status() === 202) {
            return OutboundSendResultData::from([
                'success' => true,
                'status' => 'success',
                'providerReferenceId' => is_array($result) ? ($result['id'] ?? null) : null,
                'providerResponse' => is_array($result) ? $result : [],
            ]);
        }

        return OutboundSendResultData::from([
            'success' => false,
            'status' => 'failed',
            'providerReferenceId' => is_array($result) ? ($result['id'] ?? null) : null,
            'error' => $this->extractErrorMessage($result, $response->body()),
            'providerResponse' => is_array($result) ? $result : [],
        ]);
    }

    protected function buildMediaUrls(Messages $message): array
    {
        return collect($message->media ?? [])
            ->map(function ($item) {
                if (is_string($item) && !empty($item)) {
                    return $item;
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
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function extractErrorMessage(mixed $result, string $fallback): string
    {
        if (is_array($result)) {
            return $result['message']
                ?? $result['error']
                ?? json_encode($result);
        }

        return $fallback;
    }
}
