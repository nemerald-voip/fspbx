<?php

namespace App\Services;

use App\Models\EmailLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class EmailProviderDetailsService
{
    public function getDetails(EmailLog $log): array
    {
        $provider = strtolower((string) $log->provider);

        if ($provider === '') {
            return [
                'available' => false,
                'message' => 'No email provider was recorded for this email. Send a new email after the latest update, then check again.',
            ];
        }

        return match ($provider) {
            'postmark' => $this->getPostmarkDetails($log),
            default => [
                'available' => false,
                'message' => 'Delivery details are not available for this email provider.',
            ],
        };
    }

    private function getPostmarkDetails(EmailLog $log): array
    {
        $token = config('services.postmark.token');

        if (blank($token)) {
            return [
                'available' => false,
                'message' => 'Postmark API token is not configured.',
            ];
        }

        $messageId = trim((string) $log->provider_message_id);

        if ($messageId === '') {
            $messageId = $this->findPostmarkMessageId($log, $token);

            if ($messageId === '') {
                return [
                    'available' => false,
                    'message' => 'Postmark did not return a matching message for this email log. It may not have received the metadata yet, or the message may be outside retention.',
                ];
            }

            $log->forceFill([
                'provider_message_id' => $messageId,
            ])->save();
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-Postmark-Server-Token' => $token])
                ->timeout(15)
                ->get('https://api.postmarkapp.com/messages/outbound/' . rawurlencode($messageId) . '/details');
        } catch (\Throwable $exception) {
            logger('EmailProviderDetailsService Postmark lookup error: ' . $exception->getMessage());

            return [
                'available' => false,
                'message' => 'Unable to connect to Postmark for delivery details.',
            ];
        }

        if ($response->status() === 404) {
            return [
                'available' => false,
                'message' => 'Postmark no longer has details for this message.',
            ];
        }

        if (! $response->successful()) {
            return [
                'available' => false,
                'message' => 'Postmark returned an error while fetching delivery details.',
                'status' => $response->status(),
                'details' => $response->json(),
            ];
        }

        $payload = $response->json();

        return [
            'available' => true,
            'provider' => 'Postmark',
            'message_id' => $messageId,
            'subject' => $payload['Subject'] ?? $log->subject,
            'status' => $payload['Status'] ?? null,
            'message_stream' => $payload['MessageStream'] ?? $log->provider_message_stream,
            'events' => $payload['MessageEvents'] ?? [],
            'raw' => $payload,
        ];
    }

    private function findPostmarkMessageId(EmailLog $log, string $token): string
    {
        try {
            $createdAt = $log->created_at
                ? Carbon::parse($log->created_at)
                : now();

            $params = [
                'count' => 1,
                'offset' => 0,
                'metadata_email_log_uuid' => (string) $log->uuid,
                'fromdate' => $createdAt->copy()->subDay()->setTimezone('America/New_York')->format('Y-m-d\TH:i:s'),
                'todate' => $createdAt->copy()->addDay()->setTimezone('America/New_York')->format('Y-m-d\TH:i:s'),
            ];

            if (! blank($log->provider_message_stream)) {
                $params['messagestream'] = $log->provider_message_stream;
            }

            $response = Http::acceptJson()
                ->withHeaders(['X-Postmark-Server-Token' => $token])
                ->timeout(15)
                ->get('https://api.postmarkapp.com/messages/outbound', $params);
        } catch (\Throwable $exception) {
            logger('EmailProviderDetailsService Postmark search error: ' . $exception->getMessage());

            return '';
        }

        if (! $response->successful()) {
            logger('EmailProviderDetailsService Postmark search failed', [
                'email_log_uuid' => $log->uuid,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return '';
        }

        return (string) data_get($response->json(), 'Messages.0.MessageID', '');
    }
}
