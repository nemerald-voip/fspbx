<?php

namespace App\Models\Bandwidth;

use App\Models\Messages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use App\Jobs\SendSmsNotificationToSlack;

/**
 * @property string|null $message_uuid
 */
class BandwidthOutboundSMS extends Model
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

        $baseUrl = rtrim(config('bandwidth.message_base_url'), '/');
        $accountId = config('bandwidth.account_id');
        $applicationId = config('bandwidth.application_id');
        $apiToken = config('bandwidth.api_token');
        $apiSecret = config('bandwidth.api_secret');

        if (empty($baseUrl) || empty($accountId) || empty($applicationId) || empty($apiToken) || empty($apiSecret)) {
            $message->status = 'Bandwidth credentials are not configured';
            $message->save();

            $this->handleError($message);
            return false;
        }

        $url = $baseUrl . '/users/' . $accountId . '/messages';

        $mediaUrls = $this->buildMediaUrlsForBandwidth($message);
        $hasMedia = !empty($mediaUrls);

        $text = trim((string) ($message->message ?? ''));

        if ($text === '' && !$hasMedia) {
            $message->status = 'Message has no text or media';
            $message->save();

            $this->handleError($message);
            return false;
        }

        $data = [
            'from' => (string) $message->source, // Bandwidth expects E.164 here
            'to' => [
                (string) $message->destination,
            ],
            'applicationId' => $applicationId,
            'tag' => (string) $message->message_uuid,
        ];

        if ($text !== '') {
            $data['text'] = $text;
        }

        if ($hasMedia) {
            $data['media'] = $mediaUrls;
        }

        $credentials = $apiToken . ':' . $apiSecret;
        $authHeader = 'Basic ' . base64_encode($credentials);

        try {
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                'Content-Type' => 'application/json; charset=utf-8',
            ])->asJson()->timeout(30)->post($url, $data);

            $result = $response->json();

            if ($response->status() === 202) {
                $message->status = 'success';

                if (is_array($result) && isset($result['id'])) {
                    $message->reference_id = $result['id'];
                }

                $message->save();
                return true;
            }

            $message->status = $this->extractErrorMessage($result, $response->body());

            if (is_array($result) && isset($result['id'])) {
                $message->reference_id = $result['id'];
            }

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

    public function wasSent(): bool
    {
        return true;
    }

    private function buildMediaUrlsForBandwidth(Messages $message): array
    {
        $media = is_array($message->media) ? $message->media : [];

        if (empty($media)) {
            return [];
        }

        $urls = [];

        foreach ($media as $item) {
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

    private function extractErrorMessage($result, string $fallbackBody = ''): string
    {
        if (is_array($result)) {
            if (!empty($result['error'])) {
                return is_string($result['error'])
                    ? $result['error']
                    : json_encode($result['error']);
            }

            if (!empty($result['description'])) {
                return is_string($result['description'])
                    ? $result['description']
                    : json_encode($result['description']);
            }

            if (!empty($result['message'])) {
                return is_string($result['message'])
                    ? $result['message']
                    : json_encode($result['message']);
            }
        }

        return $fallbackBody !== '' ? $fallbackBody : 'failed';
    }

    private function handleError(Messages $message): void
    {
        $label = strtoupper($message->type ?? 'sms');

        $error = "*Outbound Bandwidth {$label} Failed*: From: {$message->source} To: {$message->destination}\n{$message->status}";

        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');
    }
}