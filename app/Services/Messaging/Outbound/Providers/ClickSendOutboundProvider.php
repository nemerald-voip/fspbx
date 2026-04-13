<?php

namespace App\Services\Messaging\Outbound\Providers;

use App\Models\Messages;
use App\Services\Messaging\Outbound\Contracts\OutboundProviderInterface;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ClickSendOutboundProvider implements OutboundProviderInterface
{
    public function send(Messages $message): OutboundSendResultData
    {
        messaging_webhook_debug('ClickSendOutboundProvider send() started', [
            'message_uuid' => $message->message_uuid,
            'source' => $message->source,
            'destination' => $message->destination,
            'type' => $message->type,
        ]);

        $mediaUrls = $this->buildMediaUrls($message);

        messaging_webhook_debug('ClickSendOutboundProvider media resolved', [
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

        $baseUrl = rtrim((string) config('clicksend.base_url'), '/');
        $username = (string) config('clicksend.username');
        $apiKey = (string) config('clicksend.api_key');

        if ($baseUrl === '' || $username === '' || $apiKey === '') {
            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => 'ClickSend credentials are not configured',
            ]);
        }

        if ($hasMedia && count($mediaUrls) > 1) {
            messaging_webhook_debug('ClickSendOutboundProvider extra MMS media ignored', [
                'message_uuid' => $message->message_uuid,
                'media_count' => count($mediaUrls),
                'used_media_url' => $mediaUrls[0],
            ]);
        }

        $isMms = $hasMedia;
        $url = $baseUrl . ($isMms ? '/v3/mms/send' : '/v3/sms/send');

        $payload = $isMms
            ? $this->buildMmsPayload($message, $text, $mediaUrls[0] ?? null)
            : $this->buildSmsPayload($message, $text);

        messaging_webhook_debug('ClickSendOutboundProvider request payload', [
            'message_uuid' => $message->message_uuid,
            'url' => $url,
            'payload' => $payload,
        ]);

        try {
            $response = Http::withBasicAuth($username, $apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->post($url, $payload);

            $result = $response->json();

            messaging_webhook_debug('ClickSendOutboundProvider response received', [
                'message_uuid' => $message->message_uuid,
                'http_status' => $response->status(),
                'response' => $result,
            ]);

            if ($this->isSuccessfulResponse($response->successful(), $result)) {
                return OutboundSendResultData::from([
                    'success' => true,
                    'status' => 'success',
                    'providerReferenceId' => $this->extractReferenceId($result),
                    'providerResponse' => is_array($result) ? $result : [],
                ]);
            }

            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => $this->extractError($result, $response->body()),
                'providerResponse' => is_array($result) ? $result : [],
                'providerReferenceId' => $this->extractReferenceId($result),
            ]);
        } catch (Throwable $e) {
            messaging_webhook_debug('ClickSendOutboundProvider exception', [
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
            'messages' => [[
                'to' => (string) $message->destination,
                'body' => $text,
                'from' => (string) $message->source,
                'source' => config('app.name'),
                'custom_string' => (string) $message->message_uuid,
            ]],
        ];
    }

    protected function buildMmsPayload(Messages $message, string $text, ?string $mediaUrl): array
    {
        $body = $text !== '' ? $text : 'File';

        return [
            'media_file' => $mediaUrl,
            'messages' => [[
                'to' => (string) $message->destination,
                'from' => (string) $message->source,
                'subject' => $this->mmsSubject(),
                'body' => $body,
                'source' => config('app.name'),
                'custom_string' => (string) $message->message_uuid,
            ]],
        ];
    }

    protected function mmsSubject(): string
    {
        return Str::limit(
            (string) config('clicksend.mms_subject', config('app.name', 'Message')),
            20,
            ''
        );
    }

    protected function isSuccessfulResponse(bool $httpSuccessful, mixed $result): bool
    {
        if (! $httpSuccessful || ! is_array($result)) {
            return false;
        }

        if (($result['response_code'] ?? null) !== 'SUCCESS') {
            return false;
        }

        $message = $result['data']['messages'][0] ?? null;

        if (! is_array($message)) {
            return false;
        }

        $messageStatus = strtoupper((string) ($message['status'] ?? ''));

        return in_array($messageStatus, [
            'SUCCESS',
            'QUEUED',
            'SENT',
            'MESSAGE_QUEUED',
        ], true);
    }

    protected function extractReferenceId(mixed $result): ?string
    {
        if (! is_array($result)) {
            return null;
        }

        return $result['data']['messages'][0]['message_id']
            ?? $result['data']['message_id']
            ?? null;
    }

    protected function extractError(mixed $result, string $fallback): string
    {
        if (is_array($result)) {
            return $result['data']['messages'][0]['status']
                ?? $result['data']['messages'][0]['error']
                ?? $result['response_msg']
                ?? $result['response_code']
                ?? json_encode($result);
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
