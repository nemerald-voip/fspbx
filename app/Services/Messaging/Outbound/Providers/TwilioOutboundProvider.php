<?php

namespace App\Services\Messaging\Outbound\Providers;

use App\Models\Messages;
use App\Services\Messaging\Outbound\Contracts\OutboundProviderInterface;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;
use Illuminate\Support\Facades\Http;
use Throwable;

class TwilioOutboundProvider implements OutboundProviderInterface
{
    public function send(Messages $message): OutboundSendResultData
    {
        messaging_webhook_debug('TwilioOutboundProvider send() started', [
            'message_uuid' => $message->message_uuid,
            'source' => $message->source,
            'destination' => $message->destination,
            'type' => $message->type,
        ]);

        $accountSid = (string) config('twilio.account_sid');
        $authToken  = (string) config('twilio.auth_token');
        $baseUrl    = rtrim((string) config('twilio.base_url'), '/');

        if ($accountSid === '' || $authToken === '') {
            return OutboundSendResultData::from([
                'success' => false,
                'status'  => 'failed',
                'error'   => 'Twilio credentials are not configured',
            ]);
        }

        $text      = trim((string) ($message->message ?? ''));
        $mediaUrls = $this->buildMediaUrls($message);
        $hasMedia  = ! empty($mediaUrls);

        messaging_webhook_debug('TwilioOutboundProvider media resolved', [
            'message_uuid' => $message->message_uuid,
            'media_count'  => count($mediaUrls),
            'media_urls'   => $mediaUrls,
        ]);

        if ($text === '' && ! $hasMedia) {
            return OutboundSendResultData::from([
                'success' => false,
                'status'  => 'failed',
                'error'   => 'Message has no text or media',
            ]);
        }

        $payload = [
            'From' => (string) $message->source,
            'To'   => (string) $message->destination,
        ];

        if ($text !== '') {
            $payload['Body'] = $text;
        }

        // Twilio expects the MediaUrl parameter repeated for each attachment,
        // which PHP form encoding cannot express, so build the body manually.
        $formPairs = [];
        foreach ($payload as $key => $value) {
            $formPairs[] = urlencode($key) . '=' . urlencode($value);
        }
        foreach (array_slice($mediaUrls, 0, 10) as $url) {
            $formPairs[] = 'MediaUrl=' . urlencode($url);
        }
        $formBody = implode('&', $formPairs);

        $endpoint = "{$baseUrl}/Accounts/{$accountSid}/Messages.json";

        messaging_webhook_debug('TwilioOutboundProvider request payload', [
            'message_uuid' => $message->message_uuid,
            'endpoint'     => $endpoint,
            'payload'      => $payload,
            'media_urls'   => array_slice($mediaUrls, 0, 10),
        ]);

        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->withBody($formBody, 'application/x-www-form-urlencoded')
                ->acceptJson()
                ->timeout(30)
                ->post($endpoint);

            $result = $response->json();

            messaging_webhook_debug('TwilioOutboundProvider response received', [
                'message_uuid' => $message->message_uuid,
                'http_status'  => $response->status(),
                'response'     => $result,
            ]);

            if ($response->successful() && is_array($result) && isset($result['sid'])) {
                $providerStatus = strtolower((string) ($result['status'] ?? 'queued'));

                return OutboundSendResultData::from([
                    'success'             => true,
                    'status'              => $this->mapSuccessStatus($providerStatus),
                    'providerReferenceId' => $result['sid'],
                    'providerResponse'    => $result,
                ]);
            }

            return OutboundSendResultData::from([
                'success'             => false,
                'status'              => 'failed',
                'error'               => $this->extractError($result, $response->body()),
                'providerReferenceId' => is_array($result) ? ($result['sid'] ?? null) : null,
                'providerResponse'    => is_array($result) ? $result : [],
            ]);
        } catch (Throwable $e) {
            messaging_webhook_debug('TwilioOutboundProvider exception', [
                'message_uuid' => $message->message_uuid,
                'error'        => $e->getMessage(),
                'file'         => $e->getFile(),
                'line'         => $e->getLine(),
            ]);

            return OutboundSendResultData::from([
                'success'          => false,
                'status'           => 'failed',
                'error'            => $e->getMessage(),
                'providerResponse' => [
                    'exception' => $e->getMessage(),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                ],
            ]);
        }
    }

    protected function mapSuccessStatus(string $providerStatus): string
    {
        return match ($providerStatus) {
            'queued', 'accepted', 'scheduled', 'sending', 'sent' => 'queued',
            'delivered' => 'success',
            default => 'queued',
        };
    }

    protected function extractError(mixed $result, string $fallback): string
    {
        if (is_array($result)) {
            if (! empty($result['message'])) {
                $code = $result['code'] ?? null;

                return $code ? "Code {$code}: {$result['message']}" : (string) $result['message'];
            }

            return json_encode($result);
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
