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
        $logStatus = $this->syncPostmarkStatusFromDetails($log, $payload, $messageId);

        return [
            'available' => true,
            'provider' => 'Postmark',
            'message_id' => $messageId,
            'subject' => $payload['Subject'] ?? $log->subject,
            'status' => $logStatus ?? $payload['Status'] ?? null,
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
                'count' => 10,
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

            if ($response->successful() && filled(data_get($response->json(), 'Messages.0.MessageID'))) {
                return $this->closestMessageId($response->json(), $createdAt);
            }

            $params = [
                'count' => 10,
                'offset' => 0,
                'recipient' => $this->emailAddress($log->to),
                'subject' => (string) $log->subject,
                'fromdate' => $createdAt->copy()->subHours(2)->setTimezone('America/New_York')->format('Y-m-d\TH:i:s'),
                'todate' => $createdAt->copy()->addHours(2)->setTimezone('America/New_York')->format('Y-m-d\TH:i:s'),
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

        return $this->closestMessageId($response->json(), $createdAt);
    }

    private function closestMessageId(array $payload, Carbon $createdAt): string
    {
        $message = collect($payload['Messages'] ?? [])
            ->sortBy(function ($message) use ($createdAt) {
                if (empty($message['ReceivedAt'])) {
                    return PHP_INT_MAX;
                }

                try {
                    return abs(Carbon::parse($message['ReceivedAt'])->diffInSeconds($createdAt));
                } catch (\Throwable) {
                    return PHP_INT_MAX;
                }
            })
            ->first();

        return (string) data_get($message, 'MessageID', '');
    }

    private function emailAddress(?string $value): string
    {
        if (preg_match('/<([^>]+)>/', (string) $value, $matches)) {
            return trim($matches[1]);
        }

        return trim((string) $value);
    }

    private function syncPostmarkStatusFromDetails(EmailLog $log, array $payload, string $messageId): ?string
    {
        $events = collect($payload['MessageEvents'] ?? []);
        $status = null;
        $summary = null;

        if ($events->contains(fn ($event) => ($event['Type'] ?? $event['RecordType'] ?? null) === 'SpamComplaint')) {
            $status = 'permanent_failed';
            $summary = 'Postmark spam complaint';
        } elseif ($events->contains(fn ($event) => in_array(($event['Type'] ?? $event['RecordType'] ?? null), ['Bounced', 'Bounce'], true))) {
            $status = 'permanent_failed';
            $summary = 'Postmark bounce';
        } elseif ($events->contains(fn ($event) => ($event['Type'] ?? $event['RecordType'] ?? null) === 'Transient')) {
            $status = 'failed';
            $event = $events->first(fn ($event) => ($event['Type'] ?? $event['RecordType'] ?? null) === 'Transient');
            $summary = 'Postmark transient delivery issue: ' . (data_get($event, 'Details.DeliveryMessage') ?: data_get($event, 'Description') ?: 'Temporary delivery issue');
        }

        $updates = [
            'provider_message_id' => $messageId,
            'provider_message_stream' => $payload['MessageStream'] ?? $log->provider_message_stream,
        ];

        if ($status !== null) {
            $updates['status'] = $status;
            $updates['sent_debug_info'] = $summary;
        }

        $log->forceFill($updates)->save();

        return $status;
    }
}
