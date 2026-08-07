<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Data\DeliveryStatusEventData;
use App\Services\Messaging\Data\DownloadedMediaData;
use App\Services\Messaging\Data\InboundMessageEventData;
use Illuminate\Support\Facades\Http;
use libphonenumber\PhoneNumberFormat;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

class TwilioWebhookParser implements MessagingWebhookParser
{
    /**
     * @return iterable<object>
     */
    public function parse(WebhookCall $webhookCall): iterable
    {
        $payload = $webhookCall->payload ?? [];

        $messageSid    = (string) ($payload['MessageSid'] ?? ($payload['SmsSid'] ?? ''));
        $messageStatus = (string) ($payload['MessageStatus'] ?? ($payload['SmsStatus'] ?? ''));

        messaging_webhook_debug('TwilioWebhookParser parse() started', [
            'message_sid'    => $messageSid,
            'message_status' => $messageStatus,
            'has_body'       => array_key_exists('Body', $payload),
        ]);

        if (! is_array($payload) || $messageSid === '') {
            messaging_webhook_debug('TwilioWebhookParser invalid payload — missing MessageSid');

            return;
        }

        // Twilio sends MessageStatus/SmsStatus = "received" on INBOUND messages, and one of
        // queued/sent/delivered/undelivered/failed on delivery-status callbacks. Only the
        // latter set should be treated as a delivery status event — "received" (or an empty
        // status) is an inbound message.
        if ($messageStatus !== '' && strtolower($messageStatus) !== 'received') {
            $event = DeliveryStatusEventData::from([
                'provider'      => 'twilio',
                'referenceId'   => $messageSid,
                'status'        => $this->mapDeliveryStatus($messageStatus),
                'description'   => $this->extractDeliveryDescription($payload, $messageStatus),
                'providerEvent' => 'delivery_status',
            ]);

            messaging_webhook_debug('TwilioWebhookParser delivery event parsed', [
                'reference_id'    => $event->referenceId,
                'provider_status' => $messageStatus,
                'local_status'    => $event->status,
                'description'     => $event->description,
            ]);

            yield $event;

            return;
        }

        // Inbound message: must have From/To (Body may be empty for MMS-only messages).
        $from = $this->normalizePhoneNumber((string) ($payload['From'] ?? ''));
        $to   = $this->normalizePhoneNumber((string) ($payload['To'] ?? ''));

        if ($from === null || $to === null) {
            messaging_webhook_debug('TwilioWebhookParser could not resolve From/To', [
                'from_raw' => $payload['From'] ?? null,
                'to_raw'   => $payload['To'] ?? null,
            ]);

            return;
        }

        $mediaUrls = $this->extractMediaUrls($payload);

        $event = InboundMessageEventData::from([
            'provider'            => 'twilio',
            'providerReferenceId' => $messageSid,
            'from'                => $from,
            'to'                  => [$to],
            'text'                => trim((string) ($payload['Body'] ?? '')),
            'mediaUrls'           => $mediaUrls,
            'providerEvent'       => 'message.received',
        ]);

        messaging_webhook_debug('TwilioWebhookParser inbound event parsed', [
            'provider_reference_id' => $event->providerReferenceId,
            'from'                  => $event->from,
            'to'                    => $event->to,
            'text_length'           => strlen((string) $event->text),
            'media_count'           => count($mediaUrls),
        ]);

        yield $event;
    }

    public function downloadMedia(string $url): DownloadedMediaData
    {
        messaging_webhook_debug('TwilioWebhookParser downloadMedia() started', [
            'url' => $url,
        ]);

        $accountSid = (string) config('twilio.account_sid');
        $authToken  = (string) config('twilio.auth_token');

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->timeout(60)
            ->accept('*/*')
            ->get($url);

        $response->throw();

        $binary       = $response->body();
        $mimeType     = $response->header('Content-Type') ?: 'application/octet-stream';
        $originalName = $this->extractFilenameFromUrl($url, $mimeType);

        messaging_webhook_debug('TwilioWebhookParser downloadMedia() completed', [
            'url'           => $url,
            'mime_type'     => $mimeType,
            'size'          => strlen($binary),
            'original_name' => $originalName,
        ]);

        return DownloadedMediaData::from([
            'binary'       => $binary,
            'originalName' => $originalName,
            'mimeType'     => $mimeType,
            'size'         => strlen($binary),
            'sourceUrl'    => $url,
        ]);
    }

    protected function extractMediaUrls(array $payload): array
    {
        $numMedia = (int) ($payload['NumMedia'] ?? 0);
        $urls     = [];

        for ($i = 0; $i < $numMedia; $i++) {
            $url = $payload["MediaUrl{$i}"] ?? null;

            if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    protected function mapDeliveryStatus(string $providerStatus): string
    {
        return match ($providerStatus) {
            'delivered' => 'delivered',
            'undelivered', 'failed' => 'failed',
            default => 'failed',
        };
    }

    protected function extractDeliveryDescription(array $payload, string $providerStatus): ?string
    {
        $errorCode    = $payload['ErrorCode'] ?? null;
        $errorMessage = $payload['ErrorMessage'] ?? null;

        if ($errorCode || $errorMessage) {
            return implode(': ', array_filter([$errorCode ? "Code {$errorCode}" : null, $errorMessage]));
        }

        return $providerStatus !== '' ? $providerStatus : null;
    }

    protected function normalizePhoneNumber(?string $number): ?string
    {
        if (! filled($number)) {
            return null;
        }

        try {
            return formatPhoneNumber(
                $number,
                get_domain_setting('country') ?? 'US',
                PhoneNumberFormat::E164
            );
        } catch (Throwable) {
            $digits = preg_replace('/\D+/', '', (string) $number);

            return $digits ? '+' . $digits : null;
        }
    }

    protected function extractFilenameFromUrl(string $url, string $mimeType): string
    {
        $path     = parse_url($url, PHP_URL_PATH) ?: '';
        $filename = basename($path);

        if ($filename && str_contains($filename, '.')) {
            return $filename;
        }

        return 'attachment.' . $this->extensionFromMimeType($mimeType);
    }

    protected function extensionFromMimeType(string $mimeType): string
    {
        return match (strtolower(trim(explode(';', $mimeType)[0]))) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'video/mp4'  => 'mp4',
            'audio/mpeg' => 'mp3',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            default      => 'bin',
        };
    }
}
