<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Data\DownloadedMediaData;
use App\Services\Messaging\Data\InboundMessageEventData;
use libphonenumber\PhoneNumberFormat;
use RuntimeException;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

class FiberneticsWebhookParser implements MessagingWebhookParser
{
    /**
     * @return iterable<object>
     */
    public function parse(WebhookCall $webhookCall): iterable
    {
        $payload = $webhookCall->payload ?? [];
        $from = $this->normalizePhoneNumber($payload['from'] ?? null);
        $to = $this->normalizePhoneNumber($payload['to'] ?? null);

        if ($from === null || $to === null) {
            return;
        }

        $text = $this->decodeText($payload);
        $reference = hash('sha256', implode('|', [
            $from,
            $to,
            (string) ($payload['time'] ?? ''),
            (string) ($payload['binary'] ?? $text),
            (string) ($payload['metadata'] ?? ''),
        ]));

        yield InboundMessageEventData::from([
            'provider' => 'fibernetics',
            'providerReferenceId' => $reference,
            'from' => $from,
            'to' => [$to],
            'text' => $text,
            'mediaUrls' => [],
            'providerEvent' => 'incoming_sms',
        ]);
    }

    public function downloadMedia(string $url): DownloadedMediaData
    {
        throw new RuntimeException('Fibernetics SMS webhooks do not contain downloadable media');
    }

    protected function decodeText(array $payload): string
    {
        $text = (string) ($payload['message'] ?? '');
        $binary = $payload['binary'] ?? null;

        if (is_string($binary) && ($payload['binary_encoding'] ?? null) === 'base64') {
            $decoded = base64_decode($binary, true);
            $binary = $decoded === false ? null : $decoded;
        }

        $charset = strtoupper(trim((string) ($payload['charset'] ?? '')));
        $coding = (string) ($payload['coding'] ?? '');

        if (is_string($binary) && $binary !== '' && ($coding === '2' || $charset === 'UTF-16BE')) {
            try {
                return trim(mb_convert_encoding($binary, 'UTF-8', 'UTF-16BE'));
            } catch (Throwable) {
                // Fall back to Fibernetics' decoded message value.
            }
        }

        if ($text !== '' && in_array($charset, ['WINDOWS-1252', 'ISO-8859-1'], true)) {
            try {
                return trim(mb_convert_encoding($text, 'UTF-8', $charset));
            } catch (Throwable) {
                // Fall back to the decoded message value below.
            }
        }

        return trim($text);
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
}
