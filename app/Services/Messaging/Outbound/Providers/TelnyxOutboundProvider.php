<?php

namespace App\Services\Messaging\Outbound\Providers;

use App\Models\Messages;
use App\Services\Messaging\Outbound\Contracts\OutboundProviderInterface;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;
use Illuminate\Support\Facades\Http;
use Throwable;

class TelnyxOutboundProvider implements OutboundProviderInterface
{
    public function send(Messages $message): OutboundSendResultData
    {
        messaging_webhook_debug('TelnyxOutboundProvider send() started', [
            'message_uuid' => $message->message_uuid,
            'source' => $message->source,
            'destination' => $message->destination,
            'type' => $message->type,
        ]);

        $mediaUrls = $this->buildMediaUrls($message);

        messaging_webhook_debug('TelnyxOutboundProvider media resolved', [
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

        $baseUrl = rtrim((string) config('telnyx.message_base_url'), '/');
        $apiKey = (string) config('telnyx.api_key');

        if ($baseUrl === '' || $apiKey === '') {
            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => 'Telnyx credentials are not configured',
            ]);
        }

        $payload = [
            'from' => (string) $message->source,
            'to' => (string) $message->destination,
        ];

        if ($text !== '') {
            $payload['text'] = $text;
        }

        if ($hasMedia) {
            $payload['media_urls'] = array_slice($mediaUrls, 0, 10);
        }

        if ($messagingProfileId = config('telnyx.messaging_profile_id')) {
            $payload['messaging_profile_id'] = $messagingProfileId;
        }

        if ($subject = config('telnyx.mms_subject')) {
            $payload['subject'] = (string) $subject;
        }

        messaging_webhook_debug('TelnyxOutboundProvider request payload', [
            'message_uuid' => $message->message_uuid,
            'payload' => $payload,
        ]);

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('telnyx.timeout', 30))
                ->post($baseUrl . '/messages', $payload);

            $result = $response->json();

            messaging_webhook_debug('TelnyxOutboundProvider response received', [
                'message_uuid' => $message->message_uuid,
                'http_status' => $response->status(),
                'response' => $result,
            ]);

            if ($response->successful() && is_array($result) && isset($result['data'])) {
                $providerStatus = strtolower((string) ($result['data']['to'][0]['status'] ?? 'queued'));

                return OutboundSendResultData::from([
                    'success' => true,
                    'status' => $this->mapSuccessStatus($providerStatus),
                    'providerReferenceId' => $result['data']['id'] ?? null,
                    'providerResponse' => $result,
                ]);
            }

            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => $this->extractError($result, $response->body()),
                'providerReferenceId' => is_array($result) ? ($result['data']['id'] ?? null) : null,
                'providerResponse' => is_array($result) ? $result : [],
            ]);
        } catch (Throwable $e) {
            messaging_webhook_debug('TelnyxOutboundProvider exception', [
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

    protected function mapSuccessStatus(string $providerStatus): string
    {
        return match ($providerStatus) {
            'queued', 'sending', 'accepted', 'scheduled', 'sent' => 'queued',
            'delivered' => 'success',
            default => 'queued',
        };
    }

    protected function extractError(mixed $result, string $fallback): string
    {
        if (is_array($result)) {
            if (! empty($result['errors']) && is_array($result['errors'])) {
                $firstError = $result['errors'][0] ?? [];

                return $firstError['detail']
                    ?? $firstError['title']
                    ?? ('Code: ' . ($firstError['code'] ?? 'N/A'));
            }

            return $result['message'] ?? json_encode($result);
        }

        return $fallback;
    }

    protected function buildMediaUrls(Messages $message): array
    {
        return collect($this->decodeMedia($message->media))
            ->map(function ($item) {
                if (is_string($item) && ! empty($item)) {
                    return $item;
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
            ->filter()
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
}