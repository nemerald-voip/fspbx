<?php

namespace App\Models\Sinch;

use App\Models\Messages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use App\Jobs\SendSmsNotificationToSlack;

/**
 * @property string|null $message_uuid
 */
class SinchOutboundSMS extends Model
{
    public $message_uuid;

    /**
     * Send the outbound SMS/MMS message.
     */
    public function send(): bool
    {
        $message = Messages::where('message_uuid', $this->message_uuid)->first();

        if (!$message) {
            logger("Could not find message entity for message_uuid {$this->message_uuid}");
            return false;
        }

        $baseUrl = rtrim(config('sinch.message_broker_url'), '/');
        $apiKey = config('sinch.api_key');

        if (empty($baseUrl) || empty($apiKey)) {
            $message->status = 'Sinch credentials are not configured';
            $message->save();

            $this->handleError($message);
            return false;
        }

        $mediaUrls = $this->buildMediaUrlsForSinch($message);
        $hasMedia = !empty($mediaUrls);

        $text = trim((string) ($message->message ?? ''));

        if ($text === '' && !$hasMedia) {
            $message->status = 'Message has no text or media';
            $message->save();

            $this->handleError($message);
            return false;
        }

        $data = [
            'from' => preg_replace('/\D+/', '', (string) $message->source),
            'to' => [
                preg_replace('/\D+/', '', (string) $message->destination),
            ],
            'referenceId' => $message->message_uuid,
        ];

        if ($text !== '') {
            $data['text'] = $text;
        }

        if ($hasMedia) {
            $data['mediaUrls'] = $mediaUrls;
        }

        // logger($data);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->asJson()
                ->timeout(30)
                ->post($baseUrl . '/publishMessages', $data);

            $result = $response->json();

            // logger($result);

            if ($response->successful() && ($result['success'] ?? false) === true) {
                $message->status = 'success';

                $referenceId = $this->extractReferenceId($result);
                if ($referenceId) {
                    $message->reference_id = $referenceId;
                }

                $message->save();
                return true;
            }

            $message->status = $this->extractErrorMessage($result, $response->body());
            $message->save();

            $this->handleError($message);
            return false;
        } catch (\Throwable $e) {
            logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            $message->status = $e->getMessage();
            $message->save();

            $this->handleError($message);
            return false;
        }
    }

    /**
     * Determine if the outbound message was sent successfully.
     */
    public function wasSent(): bool
    {
        return true;
    }

    private function buildMediaUrlsForSinch(Messages $message): array
    {
        $media = is_array($message->media) ? $message->media : [];

        if (empty($media)) {
            return [];
        }

        $urls = [];

        foreach ($media as $item) {
            // Backward compatibility if older rows store plain URLs
            if (is_string($item) && !empty($item)) {
                $urls[] = $item;
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            if (!empty($item['access_path'])) {
                $urls[] = filter_var($item['access_path'], FILTER_VALIDATE_URL)
                    ? $item['access_path']
                    : url($item['access_path']);
                continue;
            }

            if (!empty($item['url'])) {
                $urls[] = $item['url'];
                continue;
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }



    private function extractReferenceId(array $result): ?string
    {
        return $result['result']['referenceId']
            ?? $result['referenceId']
            ?? null;
    }

    private function extractErrorMessage($result, string $fallbackBody = ''): string
    {
        if (is_array($result)) {
            if (!empty($result['detail'])) {
                return is_string($result['detail'])
                    ? $result['detail']
                    : json_encode($result['detail']);
            }

            if (!empty($result['reason'])) {
                return is_string($result['reason'])
                    ? $result['reason']
                    : json_encode($result['reason']);
            }

            if (!empty($result['message'])) {
                return is_string($result['message'])
                    ? $result['message']
                    : json_encode($result['message']);
            }

            if (!empty($result['errors'])) {
                return is_string($result['errors'])
                    ? $result['errors']
                    : json_encode($result['errors']);
            }

            if (!empty($result['response']['detail'])) {
                return is_string($result['response']['detail'])
                    ? $result['response']['detail']
                    : json_encode($result['response']['detail']);
            }
        }

        return $fallbackBody !== '' ? $fallbackBody : 'unknown error';
    }

    private function handleError(Messages $message): void
    {
        $label = strtoupper($message->type ?? 'sms');

        $error = "*Outbound Sinch {$label} Failed*: From: {$message->source} To: {$message->destination}\n{$message->status}";

        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');
    }
}