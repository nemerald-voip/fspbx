<?php

namespace App\Services\Messaging;

use App\Models\WhCall;
use Illuminate\Http\Request;
use Throwable;

class FiberneticsMmsWebhookAuditService
{
    public function record(Request $request, array $mm7): WhCall
    {
        return WhCall::create([
            'name' => 'fibernetics_messaging',
            'url' => $request->fullUrl(),
            'headers' => $this->safeHeaders($request),
            'payload' => $this->summarizePayload($request, $mm7),
            'exception' => null,
        ]);
    }

    public function markFailed(?WhCall $webhookCall, string|Throwable $error): void
    {
        if ($webhookCall === null) {
            return;
        }

        try {
            $webhookCall->exception = [
                'code' => $error instanceof Throwable ? $error->getCode() : 0,
                'message' => $error instanceof Throwable ? $error->getMessage() : $error,
            ];
            $webhookCall->save();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function safeHeaders(Request $request): array
    {
        return array_filter([
            'content-type' => $request->headers->all('content-type'),
            'content-length' => $request->headers->all('content-length'),
            'user-agent' => $request->headers->all('user-agent'),
        ]);
    }

    private function summarizePayload(Request $request, array $mm7): array
    {
        $message = is_array($mm7['message'] ?? null) ? $mm7['message'] : [];
        $media = [];

        foreach ($mm7['media'] ?? [] as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $binary = $attachment['binary'] ?? null;
            $media[] = [
                'original_name' => $attachment['original_name'] ?? null,
                'mime_type' => $attachment['mime_type'] ?? null,
                'size' => is_string($binary) ? strlen($binary) : null,
            ];
        }

        return [
            'provider' => 'fibernetics',
            'protocol' => 'mm7',
            'client_ip' => $request->ip(),
            'operation' => $message['operation'] ?? null,
            'transaction_id' => $message['transaction_id'] ?? null,
            'message_id' => $message['message_id'] ?? null,
            'mm7_version' => $message['mm7_version'] ?? null,
            'sender' => $message['sender'] ?? null,
            'recipients' => $message['recipients'] ?? [],
            'subject' => $message['subject'] ?? null,
            'text' => $mm7['text'] ?? '',
            'content_href' => $message['content_href'] ?? null,
            'timestamp' => $message['timestamp'] ?? null,
            'media' => $media,
        ];
    }
}
