<?php

namespace App\Services\Messaging\Outbound\Providers;

use App\Models\Messages;
use App\Services\Messaging\Outbound\Contracts\OutboundProviderInterface;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;
use Illuminate\Support\Facades\Http;
use Throwable;

class ApidazeOutboundProvider implements OutboundProviderInterface
{
    public function send(Messages $message): OutboundSendResultData
    {
        messaging_webhook_debug('ApidazeOutboundProvider send() started', [
            'message_uuid' => $message->message_uuid,
            'source' => $message->source,
            'destination' => $message->destination,
            'type' => $message->type,
        ]);

        $mediaUrls = $this->buildMediaUrls($message);

        messaging_webhook_debug('ApidazeOutboundProvider media resolved', [
            'message_uuid' => $message->message_uuid,
            'media_count' => count($mediaUrls),
            'media_urls' => $mediaUrls,
        ]);

        $text = trim((string) ($message->message ?? ''));
        $hasMedia = !empty($mediaUrls);

        if ($text === '' && ! $hasMedia) {
            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => 'Message has no text or media',
            ]);
        }

        $apiKey = config('apidaze.api_key');
        $apiSecret = config('apidaze.api_secret');
        $baseUrl = rtrim((string) config('apidaze.base_url'), '/');
        $endpoint = (string) config('apidaze.endpoint');

        if (empty($apiKey) || empty($apiSecret) || empty($baseUrl) || empty($endpoint)) {
            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => 'Apidaze credentials are not configured',
            ]);
        }

        $payload = [
            'from' => preg_replace('/\D+/', '', (string) $message->source),
            'to' => preg_replace('/\D+/', '', (string) $message->destination),
            'body' => $text !== '' ? $text : ' ',
            'message_type' => $hasMedia ? 'MMS' : 'SMS',
            'num_retries' => 3,
            'skip_defer' => false,
        ];

        if ($hasMedia) {
            $payload['file_urls'] = $mediaUrls;
            $payload['skip_file_error'] = false;
        }

        messaging_webhook_debug('ApidazeOutboundProvider request payload', [
            'message_uuid' => $message->message_uuid,
            'payload' => $payload,
        ]);

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(30)
                ->post($baseUrl . '/' . $apiKey . $endpoint . '?api_secret=' . urlencode($apiSecret), $payload);

            $result = $response->json();

            messaging_webhook_debug('ApidazeOutboundProvider response received', [
                'message_uuid' => $message->message_uuid,
                'http_status' => $response->status(),
                'response' => $result,
            ]);

            if (
                $response->successful() &&
                (! is_array($result) || (($result['ok'] ?? true) === true))
            ) {
                return OutboundSendResultData::from([
                    'success' => true,
                    'status' => ($result['ok'] ?? false) === true ? 'success' : ($result['status'] ?? 'queued'),
                    'providerReferenceId' => $result['details']['uuid']
                        ?? $result['message_id']
                        ?? $result['id']
                        ?? $result['uuid']
                        ?? null,
                    'providerResponse' => is_array($result) ? $result : [],
                ]);
            }

            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => is_array($result)
                    ? ($result['error'] ?? $result['message'] ?? $result['errors'] ?? json_encode($result))
                    : $response->body(),
                'providerResponse' => is_array($result) ? $result : [],
            ]);
        } catch (Throwable $e) {
            messaging_webhook_debug('ApidazeOutboundProvider exception', [
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