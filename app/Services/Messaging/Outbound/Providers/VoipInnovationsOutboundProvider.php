<?php

declare(strict_types=1);

namespace App\Services\Messaging\Outbound\Providers;

use App\Models\Messages;
use App\Services\MessageMediaObjectStorageService;
use App\Services\Messaging\Outbound\Contracts\OutboundProviderInterface;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;
use GuzzleHttp\Psr7\StreamWrapper;
use Psr\Http\Message\StreamInterface;
use SoapClient;
use SoapFault;
use SoapVar;
use Throwable;

/** VoIP Innovations SOAP provider for SMS and single-JPEG MMS. */
class VoipInnovationsOutboundProvider implements OutboundProviderInterface
{
    public function __construct(private MessageMediaObjectStorageService $storage) {}

    public function send(Messages $message): OutboundSendResultData
    {
        $text = trim((string) ($message->message ?? ''));
        $media = $message->media ?? [];
        if (is_string($media)) {
            $media = json_decode($media, true);
        }
        if (!is_array($media)) {
            return $this->failure('Invalid attachment metadata');
        }
        $hasMedia = count($media) > 0;
        if ($text === '' && !$hasMedia) {
            return $this->failure('Message has no text or media');
        }

        $username = (string) config('voipinnovations.username', '');
        $password = (string) config('voipinnovations.password', '');
        $endpoint = (string) config('voipinnovations.endpoint', '');
        if ($username === '' || $password === '' || $endpoint === '') {
            return $this->failure('VoIP Innovations API credentials/endpoint are not configured');
        }
        if (!class_exists(SoapClient::class)) {
            return $this->failure('PHP SOAP extension is not installed');
        }
        $sender = $this->normalizeNumber($message->source);
        $recipient = $this->normalizeNumber($message->destination);
        if ($sender === null || $recipient === null) {
            return $this->failure('Invalid sender or recipient telephone number');
        }

        // Load media BEFORE sending so a storage failure cannot create an ambiguous carrier outcome.
        $files = [];
        if ($hasMedia) {
            try {
                $files = $this->prepareMmsFiles($message, $media);
            } catch (Throwable $e) {
                // Do not leak S3 object keys, tenant identifiers, or request information.
                return $this->failure('VI MMS attachment validation or storage retrieval failed: ' . $this->mediaError($e));
            }
        }

        $operation = $hasMedia ? 'SendMMS' : 'SendSMSWithDLR';
        $params = [
            'login' => $username,
            'secret' => $password,
            'sender' => $sender,
            'recipient' => $recipient,
            'message' => $text,
        ];
        if ($hasMedia) {
            $params['files'] = ['MMSFile' => $files];
        }

        try {
            // SOAP trace MUST remain off: login/secret and private media are in the envelope.
            $client = new SoapClient($endpoint . '?WSDL', [
                'soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => false,
                'connection_timeout' => (int) config('voipinnovations.connection_timeout', 15),
                'cache_wsdl' => WSDL_CACHE_MEMORY,
            ]);
            $response = $client->{$operation}($params);
            $resultKey = $operation . 'Result';
            $result = is_object($response) ? ($response->{$resultKey} ?? null) : null;
            $details = is_object($result) ? ($result->MsgDetails ?? null) : null;
            $success = is_object($details) && ($details->success ?? false) === true;
            $uuid = is_object($details) ? ($details->uuid ?? null) : null;
            if (!is_string($uuid) || trim($uuid) === '') {
                $uuid = is_object($result) ? ($result->Uuid ?? null) : null;
            }
            $reference = is_scalar($uuid) && trim((string) $uuid) !== '' ? trim((string) $uuid) : null;
            $responseMessage = is_object($result) ? ($result->responseMessage ?? null) : null;
            $metadata = [
                'operation' => $operation,
                'responseCode' => is_object($result) ? ($result->responseCode ?? null) : null,
                'responseMessage' => is_scalar($responseMessage) ? (string) $responseMessage : null,
                'messageCode' => is_object($details) ? ($details->code ?? null) : null,
                'messageStatus' => is_object($details) ? ($details->status ?? null) : null,
            ];
            if ($success && $reference !== null) {
                return OutboundSendResultData::from([
                    'success' => true,
                    'status' => 'success',
                    'providerReferenceId' => $reference,
                    'providerResponse' => $metadata,
                ]);
            }
            return OutboundSendResultData::from([
                'success' => false,
                'status' => 'failed',
                'error' => $success
                    ? 'VI accepted message without a correlation UUID; do not retry automatically'
                    : (is_scalar($responseMessage) && (string) $responseMessage !== ''
                        ? (string) $responseMessage : 'VI did not confirm message submission'),
                'providerReferenceId' => $reference,
                'providerResponse' => $metadata,
            ]);
        } catch (SoapFault $e) {
            // A transport error may happen after carrier acceptance. Never auto-resubmit on this basis.
            return $this->failure('VI SOAP operation failed or outcome uncertain; reconcile before retrying');
        } catch (Throwable $e) {
            return $this->failure('VI provider operation failed; inspect provider connectivity/configuration');
        }
    }

    /** @param array<int, mixed> $media */
    private function prepareMmsFiles(Messages $message, array $media): array
    {
        // Pilot only: one JPEG. Do not silently drop additional attachments.
        if (count($media) !== 1) {
            throw new \InvalidArgumentException('Only one attachment is supported by the VI MMS pilot');
        }
        $item = array_values($media)[0];
        if (!is_array($item) || empty($item['bucket']) || empty($item['object_key'])) {
            throw new \InvalidArgumentException('Attachment is missing S3 storage references');
        }
        $maxBytes = (int) config('voipinnovations.mms_max_file_bytes', 1048576);
        $maxBytes = max(1, min($maxBytes, 5 * 1024 * 1024));
        if (isset($item['size']) && (int) $item['size'] > $maxBytes) {
            throw new \InvalidArgumentException('Attachment exceeds pilot limit');
        }
        $object = $this->storage->getObjectForDomain(
            (string) $message->domain_uuid,
            (string) $item['bucket'],
            (string) $item['object_key'],
        );
        if (isset($object['content_length']) && (int) $object['content_length'] > $maxBytes) {
            throw new \InvalidArgumentException('Stored attachment exceeds pilot limit');
        }
        $body = $object['body'];
        $stream = $body instanceof StreamInterface ? StreamWrapper::getResource($body) : $body;
        if (!is_resource($stream)) {
            throw new \RuntimeException('Unsupported media stream type');
        }
        try {
            $binary = stream_get_contents($stream, $maxBytes + 1);
        } finally {
            fclose($stream);
        }
        if (!is_string($binary) || $binary === '' || strlen($binary) > $maxBytes) {
            throw new \InvalidArgumentException('Attachment empty or exceeds pilot limit');
        }
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        if ($detected !== 'image/jpeg') {
            throw new \InvalidArgumentException('Pilot supports JPEG attachments only');
        }
        $filename = basename((string) ($item['original_name'] ?? 'photo.jpg'));
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'photo.jpg';
        $filename = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 80) . '.jpg';

        // XSD_BASE64BINARY instructs ext-soap to encode raw binary once.
        // Do NOT base64_encode() before constructing this SoapVar.
        return [[
            'FileName' => $filename,
            'FileContent' => new SoapVar($binary, XSD_BASE64BINARY),
        ]];
    }

    private function mediaError(Throwable $e): string
    {
        return $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Unable to read attachment';
    }

    private function normalizeNumber(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);
        if (strlen($digits) === 10) return '1' . $digits;
        return strlen($digits) === 11 && str_starts_with($digits, '1') ? $digits : null;
    }

    private function failure(string $error): OutboundSendResultData
    {
        return OutboundSendResultData::from(['success' => false, 'status' => 'failed', 'error' => $error]);
    }
}
