<?php

namespace App\Http\Controllers;

use DOMDocument;
use DOMXPath;
use App\Services\Messaging\FiberneticsMmsWebhookAuditService;
use App\Services\Messaging\FiberneticsMmsWebhookQueueService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

class FiberneticsMmsWebhookController extends Controller
{
    public function __construct(
        private readonly FiberneticsMmsWebhookAuditService $audit,
        private readonly FiberneticsMmsWebhookQueueService $queue,
    ) {}

    public function __invoke(Request $request): Response
    {
        $clientIp = (string) $request->ip();

        if (! $this->isAllowedSource($clientIp)) {
            messaging_webhook_debug('Fibernetics inbound MMS diagnostic rejected by IP allowlist', [
                'client_ip' => $clientIp,
            ]);

            abort(403);
        }

        $contentType = (string) $request->header('Content-Type', '');

        if (str_starts_with(strtolower($contentType), 'multipart/related')) {
            $mm7 = $this->inspectMm7Request($request->getContent(), $contentType);

            messaging_webhook_debug('Fibernetics inbound MM7 diagnostic received', [
                'client_ip' => $clientIp,
                'content_type' => $contentType,
                'content_length' => $request->header('Content-Length'),
                'message' => $mm7['message'],
                'parts' => $mm7['parts'],
            ]);

            $message = $mm7['message'];
            $transactionId = (string) ($message['transaction_id'] ?? '');
            $version = (string) ($message['mm7_version'] ?? '6.8.0');
            $operation = $message['operation'] ?? null;
            $sender = $message['sender'] ?? null;
            $recipients = array_values(array_filter($message['recipients'] ?? []));

            try {
                $webhookCall = $this->audit->record($request, $mm7);
            } catch (Throwable $e) {
                report($e);

                return $this->mm7Response(
                    $transactionId,
                    $version,
                    $operation,
                    '3000',
                    'Temporary processing failure',
                    500
                );
            }

            if ($transactionId === '' || ! is_string($sender) || $sender === '' || $recipients === []) {
                messaging_webhook_debug('Fibernetics inbound MM7 request is missing routing fields', [
                    'transaction_id_present' => $transactionId !== '',
                    'sender_present' => is_string($sender) && $sender !== '',
                    'recipient_count' => count($recipients),
                ]);
                $this->audit->markFailed(
                    $webhookCall,
                    'Missing sender, recipient, or transaction ID'
                );

                return $this->mm7Response(
                    $transactionId,
                    $version,
                    $operation,
                    '4004',
                    'Missing sender, recipient, or transaction ID'
                );
            }

            try {
                $this->queue->queue($webhookCall, $mm7['media']);
            } catch (Throwable $e) {
                $this->audit->markFailed($webhookCall, $e);
                report($e);

                return $this->mm7Response(
                    $transactionId,
                    $version,
                    $operation,
                    '3000',
                    'Temporary processing failure',
                    500
                );
            }

            return $this->mm7Response(
                $transactionId,
                $version,
                $operation
            );
        }

        messaging_webhook_debug('Fibernetics inbound MMS diagnostic received', [
            'client_ip' => $clientIp,
            'content_type' => $contentType,
            'content_length' => $request->header('Content-Length'),
            'query' => $this->summarizeFields($request->query->all()),
            'fields' => $this->summarizeFields($request->request->all()),
            'files' => $this->summarizeFiles($request->allFiles()),
        ]);

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    private function isAllowedSource(string $clientIp): bool
    {
        foreach (config('fibernetics.mms_webhook_ips', []) as $cidr) {
            try {
                if (IpUtils::checkIp($clientIp, $cidr)) {
                    return true;
                }
            } catch (InvalidArgumentException) {
                // Invalid configured networks never grant access.
            }
        }

        return false;
    }

    private function summarizeFields(array $fields): array
    {
        $summary = [];

        foreach ($fields as $key => $value) {
            $key = (string) $key;
            $lowerKey = strtolower($key);

            if (preg_match('/password|passwd|secret|token|authorization/', $lowerKey)) {
                $summary[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $summary[$key] = $this->summarizeFields($value);
                continue;
            }

            if (is_string($value)) {
                if (preg_match('/binary|content|payload|data|body/', $lowerKey)) {
                    $summary[$key] = ['length' => strlen($value)];
                    continue;
                }

                $summary[$key] = mb_strimwidth($value, 0, 500, '...');
                continue;
            }

            $summary[$key] = $value;
        }

        return $summary;
    }

    private function summarizeFiles(array $files, string $prefix = ''): array
    {
        $summary = [];

        foreach ($files as $key => $file) {
            $field = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if ($file instanceof UploadedFile) {
                $summary[] = [
                    'field' => $field,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'error' => $file->getError(),
                    'valid' => $file->isValid(),
                ];
                continue;
            }

            if (is_array($file)) {
                $summary = array_merge($summary, $this->summarizeFiles($file, $field));
            }
        }

        return $summary;
    }

    private function inspectMm7Request(string $body, string $contentType): array
    {
        $xml = null;
        $media = [];
        $textParts = [];
        $parts = $this->inspectMultipartParts($body, $contentType, $xml, $media, $textParts);
        $message = $xml !== null
            ? $this->inspectSoapEnvelope($xml)
            : ['parse_error' => 'SOAP/XML MIME part was not found'];
        $text = implode("\n", array_values(array_filter([
            $message['subject'] ?? null,
            ...$textParts,
        ], static fn ($value): bool => is_string($value) && trim($value) !== '')));

        return [
            'message' => $message,
            'parts' => $parts,
            'media' => $media,
            'text' => trim($text),
        ];
    }

    private function inspectMultipartParts(
        string $body,
        string $contentType,
        ?string &$soapXml,
        array &$media,
        array &$textParts,
        int $depth = 0
    ): array {
        $boundary = $this->contentTypeParameter($contentType, 'boundary');
        $parts = [];

        if ($boundary === null || $boundary === '') {
            return [];
        }

        foreach (explode('--' . $boundary, $body) as $rawPart) {
            $rawPart = ltrim($rawPart, "\r\n");

            if ($rawPart === '' || str_starts_with($rawPart, '--')) {
                continue;
            }

            $segments = preg_split("/\r?\n\r?\n/", $rawPart, 2);

            if (! is_array($segments) || count($segments) !== 2) {
                continue;
            }

            [$rawHeaders, $content] = $segments;
            $headers = $this->parsePartHeaders($rawHeaders);
            $content = preg_replace("/\r?\n$/", '', $content) ?? $content;
            $partContentType = $headers['content-type'] ?? 'application/octet-stream';
            $transferEncoding = strtolower($headers['content-transfer-encoding'] ?? '');
            $decodedContent = $transferEncoding === 'base64'
                ? base64_decode(preg_replace('/\s+/', '', $content) ?? '', true)
                : $content;
            $decodedLength = is_string($decodedContent) ? strlen($decodedContent) : null;
            $isXml = str_contains(strtolower($partContentType), 'xml');
            $isMultipart = str_starts_with(strtolower($partContentType), 'multipart/');
            $isSoapXml = $depth === 0 && $isXml && $soapXml === null;
            $originalName = $this->contentTypeParameter(
                $headers['content-disposition'] ?? $partContentType,
                'filename'
            ) ?? $this->contentTypeParameter(
                $headers['content-disposition'] ?? $partContentType,
                'name'
            ) ?? $this->contentTypeParameter($partContentType, 'name');

            if ($isSoapXml && is_string($decodedContent)) {
                $soapXml = $decodedContent;
            }

            $parts[] = [
                'depth' => $depth,
                'content_id' => trim($headers['content-id'] ?? '', '<>'),
                'content_type' => $partContentType,
                'transfer_encoding' => $transferEncoding !== '' ? $transferEncoding : null,
                'original_name' => $originalName,
                'encoded_size' => strlen($content),
                'decoded_size' => $decodedLength,
                'is_xml' => $isXml,
                'is_multipart' => $isMultipart,
                'text_preview' => str_starts_with(strtolower($partContentType), 'text/plain')
                    && is_string($decodedContent)
                        ? mb_strimwidth($decodedContent, 0, 500, '...')
                        : null,
            ];

            if (! $isMultipart && ! $isSoapXml && is_string($decodedContent) && $decodedContent !== '') {
                if (str_starts_with(strtolower($partContentType), 'text/plain')) {
                    $textParts[] = trim($decodedContent);
                } elseif (! str_starts_with(strtolower($partContentType), 'application/smil')) {
                    $media[] = [
                        'binary' => $decodedContent,
                        'original_name' => $originalName
                            ?: trim($headers['content-id'] ?? '', '<>')
                            ?: 'attachment.bin',
                        'mime_type' => trim(explode(';', $partContentType, 2)[0]),
                    ];
                }
            }

            if ($isMultipart && is_string($decodedContent)) {
                $parts = array_merge(
                    $parts,
                    $this->inspectMultipartParts(
                        $decodedContent,
                        $partContentType,
                        $soapXml,
                        $media,
                        $textParts,
                        $depth + 1
                    )
                );
            }
        }

        return $parts;
    }

    private function inspectSoapEnvelope(string $xml): array
    {
        $document = new DOMDocument();

        if (! @$document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return ['parse_error' => 'SOAP/XML MIME part could not be parsed'];
        }

        $xpath = new DOMXPath($document);
        $value = static function (DOMXPath $xpath, string $expression): ?string {
            $result = trim((string) $xpath->evaluate('string(' . $expression . ')'));

            return $result !== '' ? $result : null;
        };
        $recipients = [];

        foreach ($xpath->query('//*[local-name()="Recipients"]//*[local-name()="Number"]') ?: [] as $node) {
            $number = trim($node->textContent);

            if ($number !== '') {
                $recipients[] = $number;
            }
        }

        return [
            'operation' => $value($xpath, 'local-name((//*[local-name()="Body"]/*)[1])'),
            'transaction_id' => $value($xpath, '(//*[local-name()="TransactionID"])[1]'),
            'mm7_version' => $value($xpath, '(//*[local-name()="MM7Version"])[1]'),
            'message_id' => $value($xpath, '(//*[local-name()="MessageID"])[1]'),
            'linked_id' => $value($xpath, '(//*[local-name()="LinkedID"])[1]'),
            'sender' => $value($xpath, '(//*[local-name()="SenderAddress"]//*[local-name()="Number"])[1]'),
            'recipients' => array_values(array_unique($recipients)),
            'subject' => $value($xpath, '(//*[local-name()="Subject"])[1]'),
            'content_href' => $value($xpath, '(//*[local-name()="Content"]/@href)[1]'),
            'timestamp' => $value($xpath, '(//*[local-name()="TimeStamp"])[1]'),
        ];
    }

    private function parsePartHeaders(string $rawHeaders): array
    {
        $headers = [];
        $rawHeaders = preg_replace("/\r?\n[ \t]+/", ' ', $rawHeaders) ?? $rawHeaders;

        foreach (preg_split("/\r?\n/", $rawHeaders) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return $headers;
    }

    private function contentTypeParameter(string $value, string $parameter): ?string
    {
        $quoted = preg_quote($parameter, '/');

        if (! preg_match('/(?:^|;)\s*' . $quoted . '\s*=\s*(?:"([^"]*)"|([^;\s]*))/i', $value, $matches)) {
            return null;
        }

        return $matches[1] !== '' ? $matches[1] : ($matches[2] ?? null);
    }

    private function mm7Response(
        string $transactionId,
        string $version,
        ?string $operation,
        string $statusCode = '1000',
        string $statusText = 'Success',
        int $httpStatus = 200
    ): Response {
        $namespace = 'http://www.3gpp.org/ftp/Specs/archive/23_series/23.140/schema/REL-6-MM7-1-4';
        $transactionId = htmlspecialchars($transactionId, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $version = htmlspecialchars($version, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $statusCode = htmlspecialchars($statusCode, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $statusText = htmlspecialchars($statusText, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $responseElement = $operation === 'SubmitReq' ? 'SubmitRsp' : 'DeliverRsp';
        $messageId = $responseElement === 'SubmitRsp'
            ? '<mm7:MessageID>fspbx-' . hash('sha256', $transactionId) . '</mm7:MessageID>'
            : '';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:mm7="' . $namespace . '">'
            . '<soapenv:Header><mm7:TransactionID soapenv:mustUnderstand="1">' . $transactionId . '</mm7:TransactionID></soapenv:Header>'
            . '<soapenv:Body><mm7:' . $responseElement . '><mm7:MM7Version>' . $version . '</mm7:MM7Version>'
            . '<mm7:Status><mm7:StatusCode>' . $statusCode . '</mm7:StatusCode><mm7:StatusText>' . $statusText . '</mm7:StatusText></mm7:Status>'
            . $messageId
            . '</mm7:' . $responseElement . '></soapenv:Body></soapenv:Envelope>';

        return response($xml, $httpStatus)->header('Content-Type', 'text/xml; charset=UTF-8');
    }
}
