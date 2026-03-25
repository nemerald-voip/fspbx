<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class ClickSendWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        $payload = $request->all();

        $shouldProcess = $this->isInboundPayload($payload) || $this->isReceiptPayload($payload);

        messaging_webhook_debug('ClickSend webhook profile evaluated request', [
            'should_process' => $shouldProcess,
            'content_type' => $request->header('Content-Type'),
            'payload_keys' => array_keys($payload),
        ]);

        return $shouldProcess;
    }

    protected function isInboundPayload(array $payload): bool
    {
        return filled($payload['from'] ?? null)
            && filled($payload['to'] ?? null)
            && (
                array_key_exists('body', $payload)
                || array_key_exists('message', $payload)
                || array_key_exists('text', $payload)
                || array_key_exists('media', $payload)
                || array_key_exists('media_url', $payload)
                || array_key_exists('_media_file_url', $payload)
                || array_key_exists('attachments', $payload)
            );
    }

    protected function isReceiptPayload(array $payload): bool
    {
        return filled($payload['message_id'] ?? null)
            && (
                array_key_exists('status_code', $payload)
                || array_key_exists('status_text', $payload)
                || array_key_exists('error_text', $payload)
                || array_key_exists('status', $payload)
            );
    }
}