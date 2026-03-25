<?php

namespace App\Services\Messaging\Outbound\Providers;

use App\Models\Messages;
use App\Services\Messaging\Outbound\Contracts\OutboundProviderInterface;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;
use Illuminate\Support\Facades\Http;

class SinchOutboundProvider implements OutboundProviderInterface
{
    public function send(Messages $message): OutboundSendResultData
    {
        messaging_webhook_debug('SinchOutboundProvider send() started', [
            'message_uuid' => $message->message_uuid,
            'source' => $message->source,
            'destination' => $message->destination,
            'type' => $message->type,
        ]);

        $mediaUrls = $this->buildMediaUrls($message);
        messaging_webhook_debug('SinchOutboundProvider media resolved', [
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

        $payload = [
            'from' => preg_replace('/\D+/', '', (string) $message->source),
            'to' => [preg_replace('/\D+/', '', (string) $message->destination)],
            'referenceId' => $message->message_uuid,
        ];

        if ($text !== '') {
            $payload['text'] = $text;
        }

        if ($hasMedia) {
            $payload['mediaUrls'] = $mediaUrls;
        }

        messaging_webhook_debug('SinchOutboundProvider request payload', [
            'message_uuid' => $message->message_uuid,
            'payload' => $payload,
        ]);

        $response = Http::withToken(config('sinch.api_key'))
            ->asJson()
            ->timeout(30)
            ->post(rtrim(config('sinch.message_broker_url'), '/') . '/publishMessages', $payload);

        $result = $response->json();

        messaging_webhook_debug('SinchOutboundProvider response received', [
            'message_uuid' => $message->message_uuid,
            'http_status' => $response->status(),
            'response' => $result,
        ]);

        if ($response->successful() && ($result['success'] ?? false) === true) {
            return OutboundSendResultData::from([
                'success' => true,
                'status' => 'success',
                'providerReferenceId' => $result['result']['referenceId'] ?? null,
                'providerResponse' => is_array($result) ? $result : [],
            ]);
        }

        return OutboundSendResultData::from([
            'success' => false,
            'status' => 'failed',
            'error' => is_array($result) ? ($result['detail'] ?? $result['message'] ?? json_encode($result)) : $response->body(),
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
}
