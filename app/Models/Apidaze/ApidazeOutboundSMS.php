<?php

namespace App\Models\Apidaze;

use App\Jobs\SendSmsNotificationToSlack;
use App\Models\Messages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

/**
 * @property string|null $message_uuid
 */
class ApidazeOutboundSMS extends Model
{
    public $message_uuid;

    /**
     * Send the outbound SMS/MMS message via Apidaze.
     */
    public function send(): bool
    {
        $message = Messages::where('message_uuid', $this->message_uuid)->first();

        if (!$message) {
            logger("Could not find SMS entity for message_uuid {$this->message_uuid}");
            return false;
        }

        $apiKey = config('apidaze.api_key');
        $apiSecret = config('apidaze.api_secret');
        $baseUrl = rtrim(config('apidaze.base_url'), '/');

        if (empty($apiKey) || empty($apiSecret) || empty($baseUrl)) {
            $message->status = 'Apidaze credentials are not configured';
            $message->save();

            $this->handleError($message, 'Apidaze credentials are not configured');
            return false;
        }

        $url = $baseUrl . '/' . $apiKey . '/sms/send';

        $fileUrls = $this->buildMediaUrlsForApidaze($message);
        $isMms = !empty($fileUrls);

        // Apidaze docs show body as required.
        // For media-only MMS, send a single space if there is no text.
        $body = (string) ($message->message ?? '');
        if ($isMms && trim($body) === '') {
            $body = ' ';
        }

        $data = [
            'from' => $this->normalizePhoneNumberForApidaze($message->source),
            'to' => $this->normalizePhoneNumberForApidaze($message->destination),
            'body' => $body,
            'message_type' => $isMms ? 'MMS' : 'SMS',
            'num_retries' => 3,
            'skip_defer' => false,
        ];

        if ($isMms) {
            $data['file_urls'] = $fileUrls;
            $data['skip_file_error'] = false;
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(30)
                ->post($url . '?api_secret=' . urlencode($apiSecret), $data);

            $result = $response->json();

            if ($response->successful()) {
                $message->reference_id = $this->extractReferenceId($result) ?? $message->reference_id;
                $message->status = $this->extractSuccessStatus($result);
                $message->save();

                return true;
            }

            $errorMsg = $this->extractErrorMessage($result, $response->body());

            logger('Apidaze API error: ' . $errorMsg);
            logger('Apidaze API response: ' . $response->body());

            $message->reference_id = $this->extractReferenceId($result) ?? $message->reference_id;
            $message->status = $errorMsg ?: 'failed';
            $message->save();

            $this->handleError($message, $errorMsg);

            return false;
        } catch (\Throwable $e) {
            logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            $message->status = $e->getMessage();
            $message->save();

            $this->handleError($message, $e->getMessage());

            return false;
        }
    }

    private function buildMediaUrlsForApidaze(Messages $message): array
    {
        $media = $this->decodeMedia($message->media);

        if (empty($media)) {
            return [];
        }

        $urls = [];

        foreach ($media as $item) {
            // Backward compatibility for older records that already store direct URLs
            if (is_string($item) && !empty($item)) {
                $urls[] = $item;
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            // Preferred for your new private-storage flow:
            // Apidaze will fetch this URL, so it must be publicly reachable
            // and return the real file bytes directly.
            if (!empty($item['access_path'])) {
                $urls[] = url($item['access_path']);
                continue;
            }

            // Legacy support
            if (!empty($item['url'])) {
                $urls[] = $item['url'];
                continue;
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    private function decodeMedia($media): array
    {
        if (is_string($media)) {
            $media = json_decode($media, true);
        }

        return is_array($media) ? $media : [];
    }

    private function extractReferenceId($result): ?string
    {
        if (!is_array($result)) {
            return null;
        }

        return $result['details']['uuid']
            ?? $result['message_id']
            ?? $result['id']
            ?? $result['uuid']
            ?? null;
    }

    private function extractSuccessStatus($result): string
    {
        if (!is_array($result)) {
            return 'queued';
        }

        if (($result['ok'] ?? false) === true) {
            return 'success';
        }

        return $result['status']
            ?? $result['message_status']
            ?? 'queued';
    }

    private function extractErrorMessage($result, string $fallbackBody = ''): string
    {
        if (is_array($result)) {
            if (!empty($result['error'])) {
                return is_string($result['error'])
                    ? $result['error']
                    : json_encode($result['error']);
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
        }

        return $fallbackBody !== '' ? $fallbackBody : 'failed';
    }

    private function handleError(Messages $message, ?string $specificError = null): void
    {
        $errorMessage = $specificError ?: $message->status;

        $error = "*Outbound Apidaze SMS Failed*: From: {$message->source} To: {$message->destination}\nError: {$errorMessage}";

        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');
    }

    private function normalizePhoneNumberForApidaze(?string $number): string
    {
        return preg_replace('/\D+/', '', (string) $number);
    }
}
