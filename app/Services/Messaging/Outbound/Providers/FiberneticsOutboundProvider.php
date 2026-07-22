<?php

namespace App\Services\Messaging\Outbound\Providers;

use App\Models\Messages;
use App\Services\MessageMediaObjectStorageService;
use App\Services\Messaging\Outbound\Contracts\OutboundProviderInterface;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class FiberneticsOutboundProvider implements OutboundProviderInterface
{
    public function __construct(
        protected MessageMediaObjectStorageService $mediaStorage
    ) {}

    public function send(Messages $message): OutboundSendResultData
    {
        try {
            return ! empty($message->media)
                ? $this->sendMms($message)
                : $this->sendSms($message);
        } catch (Throwable $e) {
            messaging_webhook_debug('FiberneticsOutboundProvider exception', [
                'message_uuid' => $message->message_uuid,
                'type' => ! empty($message->media) ? 'mms' : 'sms',
                'error' => $e->getMessage(),
            ]);

            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'providerResponse' => ['exception' => $e->getMessage()],
            ]);
        }
    }

    protected function sendSms(Messages $message): OutboundSendResultData
    {
        $url = trim((string) config('fibernetics.sms_url'));
        $username = trim((string) config('fibernetics.sms_username'));
        $password = (string) config('fibernetics.sms_password');

        if ($url === '' || $username === '' || $password === '') {
            return $this->failure('Fibernetics SMS credentials are not configured');
        }

        $text = (string) ($message->message ?? '');

        if ($text === '') {
            return $this->failure('Message has no text or media');
        }

        [$coding, $charset, $encodedText] = $this->encodeSmsText($text);
        $query = [
            'password' => $password,
            'username' => $username,
            'to' => $this->formatE164($message->destination),
            'from' => $this->formatE164($message->source),
            'coding' => $coding,
        ];

        if ($charset !== null) {
            $query['charset'] = $charset;
        }

        $requestUrl = $url . (str_contains($url, '?') ? '&' : '?')
            . http_build_query($query, '', '&', PHP_QUERY_RFC3986)
            . '&text=' . ($coding === 2 ? $this->percentEncodeBytes($encodedText) : rawurlencode($encodedText));

        messaging_webhook_debug('Fibernetics outbound SMS request', [
            'message_uuid' => $message->message_uuid,
            'endpoint' => $url,
            'coding' => $coding,
            'charset' => $charset,
        ]);

        $response = Http::timeout((int) config('fibernetics.timeout', 60))
            ->accept('text/plain, */*')
            ->get($requestUrl);

        $body = trim($response->body());
        $providerResponse = [
            'http_status' => $response->status(),
            'body' => $body,
            'coding' => $coding,
            'charset' => $charset,
        ];

        if ($response->successful() && $this->smsResponseAccepted($body)) {
            return OutboundSendResultData::from([
                'success' => true,
                'status' => 'success',
                'providerReferenceId' => $this->smsReferenceId($body),
                'providerResponse' => $providerResponse,
            ]);
        }

        return OutboundSendResultData::from([
            'success' => false,
            'status' => 'failed',
            'error' => $body !== '' ? $body : "Fibernetics SMS returned HTTP {$response->status()}",
            'providerResponse' => $providerResponse,
        ]);
    }

    protected function sendMms(Messages $message): OutboundSendResultData
    {
        $url = trim((string) config('fibernetics.mm7_url'));
        $username = trim((string) config('fibernetics.mm7_username'));
        $password = (string) config('fibernetics.mm7_password');

        if ($url === '' || $username === '' || $password === '') {
            return $this->failure('Fibernetics MM7 credentials are not configured');
        }

        $attachment = $this->firstAttachment($message);
        $transactionId = 'mms-' . Str::uuid();
        $contentId = Str::random(24) . '@fspbx';
        $soap = $this->buildSubmitRequest($message, $username, $transactionId, $contentId);
        [$body, $contentType] = $this->buildMultipartBody($soap, $attachment, $contentId);

        if (count($message->media ?? []) > 1) {
            messaging_webhook_debug('Fibernetics extra MMS media ignored', [
                'message_uuid' => $message->message_uuid,
                'media_count' => count($message->media),
                'used_media' => $attachment['name'],
            ]);
        }

        messaging_webhook_debug('Fibernetics outbound MM7 request', [
            'message_uuid' => $message->message_uuid,
            'endpoint' => $url,
            'transaction_id' => $transactionId,
            'attachment_name' => $attachment['name'],
            'attachment_size' => strlen($attachment['binary']),
        ]);

        $response = Http::withOptions([
            'verify' => (bool) config('fibernetics.mm7_verify_ssl', false),
        ])
            ->withBasicAuth($username, $password)
            ->withHeaders([
                'SOAPAction' => '',
                'MIME-Version' => '1.0',
            ])
            ->timeout((int) config('fibernetics.timeout', 60))
            ->withBody($body, $contentType)
            ->post($url);

        return $this->parseMm7Response($response, $transactionId);
    }

    protected function firstAttachment(Messages $message): array
    {
        $item = collect($message->media ?? [])->first();

        if (! is_array($item)) {
            throw new RuntimeException('Fibernetics MMS media is not in a supported stored-media format');
        }

        $name = preg_replace('/[\r\n"]+/', '_', basename((string) ($item['original_name'] ?? $item['stored_name'] ?? 'attachment.bin')));
        $mimeType = trim(explode(';', (string) ($item['mime_type'] ?? ''))[0]);

        if (! empty($item['bucket']) && ! empty($item['object_key'])) {
            $object = $this->mediaStorage->getObjectForDomain(
                domainUuid: (string) $message->domain_uuid,
                bucket: (string) $item['bucket'],
                objectKey: (string) $item['object_key']
            );

            $binary = (string) $object['body'];
            $mimeType = $mimeType ?: (string) ($object['content_type'] ?? 'application/octet-stream');
        } else {
            $mediaUrl = $item['url'] ?? $item['access_path'] ?? null;

            if (! is_string($mediaUrl) || $mediaUrl === '') {
                throw new RuntimeException('Fibernetics MMS media could not be loaded');
            }

            $mediaUrl = filter_var($mediaUrl, FILTER_VALIDATE_URL) ? $mediaUrl : url($mediaUrl);
            $mediaResponse = Http::timeout((int) config('fibernetics.timeout', 60))->get($mediaUrl);
            $mediaResponse->throw();
            $binary = $mediaResponse->body();
            $mimeType = $mimeType ?: (string) ($mediaResponse->header('Content-Type') ?: 'application/octet-stream');
        }

        if ($binary === '') {
            throw new RuntimeException('Fibernetics MMS media is empty');
        }

        $mimeType = trim(explode(';', $mimeType)[0]);
        if (! preg_match('#^[A-Za-z0-9.+-]+/[A-Za-z0-9.+-]+$#', $mimeType)) {
            $mimeType = 'application/octet-stream';
        }

        return [
            'name' => $name !== '' ? $name : 'attachment.bin',
            'mime_type' => $mimeType !== '' ? $mimeType : 'application/octet-stream',
            'binary' => $binary,
        ];
    }

    protected function buildSubmitRequest(
        Messages $message,
        string $username,
        string $transactionId,
        string $contentId
    ): string {
        $namespace = 'http://www.3gpp.org/ftp/Specs/archive/23_series/23.140/schema/REL-6-MM7-1-4';
        $version = $this->xml((string) config('fibernetics.mm7_version', '6.8.0'));
        $subject = trim((string) ($message->message ?? ''));
        $subject = $subject !== '' ? $subject : (string) config('fibernetics.mm7_subject', config('app.name', 'Message'));

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:mm7="' . $namespace . '">'
            . '<soapenv:Header>'
            . '<mm7:TransactionID soapenv:mustUnderstand="1">' . $this->xml($transactionId) . '</mm7:TransactionID>'
            . '</soapenv:Header>'
            . '<soapenv:Body>'
            . '<mm7:SubmitReq>'
            . '<mm7:MM7Version>' . $version . '</mm7:MM7Version>'
            . '<mm7:SenderIdentification>'
            . '<mm7:VASPID>' . $this->xml($username) . '</mm7:VASPID>'
            . '<mm7:SenderAddress><mm7:Number>' . $this->xml($this->formatE164($message->source)) . '</mm7:Number></mm7:SenderAddress>'
            . '</mm7:SenderIdentification>'
            . '<mm7:Recipients><mm7:To><mm7:Number>' . $this->xml($this->formatE164($message->destination)) . '</mm7:Number></mm7:To></mm7:Recipients>'
            . '<mm7:TimeStamp>' . now()->toIso8601String() . '</mm7:TimeStamp>'
            . '<mm7:Subject>' . $this->xml($subject) . '</mm7:Subject>'
            . '<mm7:Content href="cid:' . $this->xml($contentId) . '"/>'
            . '</mm7:SubmitReq>'
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';
    }

    protected function buildMultipartBody(string $soap, array $attachment, string $contentId): array
    {
        $boundary = 'MM7-' . Str::random(32);
        $rootContentId = 'soap-envelope@fspbx';
        $eol = "\r\n";
        $body = '--' . $boundary . $eol
            . 'Content-Type: application/xop+xml; charset=UTF-8; type="application/soap+xml"' . $eol
            . 'Content-Transfer-Encoding: binary' . $eol
            . 'Content-ID: <' . $rootContentId . '>' . $eol . $eol
            . $soap . $eol
            . '--' . $boundary . $eol
            . 'Content-Type: ' . $attachment['mime_type'] . $eol
            . 'Content-Transfer-Encoding: base64' . $eol
            . 'Content-ID: <' . $contentId . '>' . $eol
            . 'Content-Disposition: attachment; filename="' . addcslashes($attachment['name'], "\\\"") . '"' . $eol . $eol
            . chunk_split(base64_encode($attachment['binary']), 76, $eol)
            . '--' . $boundary . '--' . $eol;

        $contentType = 'multipart/related; boundary="' . $boundary
            . '"; type="application/xop+xml"; start="<' . $rootContentId
            . '>"; start-info="application/soap+xml"';

        return [$body, $contentType];
    }

    protected function parseMm7Response(Response $response, string $transactionId): OutboundSendResultData
    {
        $body = trim($response->body());
        $parsed = [
            'http_status' => $response->status(),
            'transaction_id' => $transactionId,
            'body' => $body,
        ];

        if ($body !== '') {
            $xml = @simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);

            if ($xml !== false) {
                $parsed['status_code'] = $this->xpathValue($xml, 'StatusCode');
                $parsed['status_text'] = $this->xpathValue($xml, 'StatusText');
                $parsed['message_id'] = $this->xpathValue($xml, 'MessageID');
                $parsed['fault'] = $this->xpathValue($xml, 'faultstring');
            }
        }

        $statusCode = isset($parsed['status_code']) ? (int) $parsed['status_code'] : null;
        $accepted = $response->successful()
            && $statusCode !== null
            && $statusCode >= 1000
            && $statusCode < 2000;

        if ($accepted) {
            return OutboundSendResultData::from([
                'success' => true,
                'status' => 'success',
                'providerReferenceId' => $parsed['message_id'] ?: $transactionId,
                'providerResponse' => $parsed,
            ]);
        }

        $error = $parsed['fault']
            ?? $parsed['status_text']
            ?? ($body !== '' ? $body : "Fibernetics MM7 returned HTTP {$response->status()}");

        return OutboundSendResultData::from([
            'success' => false,
            'status' => 'failed',
            'providerReferenceId' => $parsed['message_id'] ?? null,
            'error' => $error,
            'providerResponse' => $parsed,
        ]);
    }

    protected function encodeSmsText(string $text): array
    {
        if (! preg_match('/[^\x00-\x7F]/', $text)) {
            return [0, null, $text];
        }

        return [2, 'UTF-16BE', mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')];
    }

    protected function percentEncodeBytes(string $value): string
    {
        return implode('', array_map(
            fn (string $byte) => sprintf('%%%02X', ord($byte)),
            str_split($value)
        ));
    }

    protected function smsResponseAccepted(string $body): bool
    {
        if (preg_match('/^\s*(-?\d+)\s*:/', $body, $matches)) {
            return (int) $matches[1] === 0;
        }

        return ! preg_match('/\b(error|failed|denied|invalid|rejected)\b/i', $body);
    }

    protected function smsReferenceId(string $body): ?string
    {
        if (preg_match('/(?:id|message[_ -]?id)\s*[:=]\s*([A-Za-z0-9._:-]+)/i', $body, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function formatE164(?string $number): string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        return $digits === '' ? '' : '+' . $digits;
    }

    protected function xpathValue(SimpleXMLElement $xml, string $localName): ?string
    {
        $nodes = $xml->xpath('//*[local-name()="' . $localName . '"]');
        $value = isset($nodes[0]) ? trim((string) $nodes[0]) : '';

        return $value !== '' ? $value : null;
    }

    protected function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected function failure(string $error): OutboundSendResultData
    {
        return OutboundSendResultData::from([
            'success' => false,
            'status' => 'failed',
            'error' => $error,
        ]);
    }
}
