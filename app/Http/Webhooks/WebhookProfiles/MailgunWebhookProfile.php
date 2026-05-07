<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Illuminate\Http\Request;
use App\Services\FaxSendService;

class MailgunWebhookProfile extends FaxWebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        // 1. Extract raw destination
        $recipientEmail = $request['recipient'] ?? null;
        if (!$recipientEmail || strpos($recipientEmail, '@') === false) {
            fax_webhook_debug('MailgunWebhookProfile: no valid recipient in request', [
                'recipient' => $recipientEmail,
            ]);
            return false;
        }
        $phoneNumber = strstr($recipientEmail, '@', true);

        // 2. Extract sender
        $fromEmail = $this->extractEmail($request['from'] ?? $request['From'] ?? null);
        if (!$fromEmail) {
            fax_webhook_debug('MailgunWebhookProfile: no valid sender in request', [
                'recipient' => $recipientEmail,
                'from'      => $request['from'] ?? $request['From'] ?? null,
            ]);
            return false;
        }

        fax_webhook_debug('MailgunWebhookProfile: email-to-fax webhook received', [
            'from'            => $fromEmail,
            'recipient'       => $recipientEmail,
            'raw_destination' => $phoneNumber,
        ]);

        // 3. Authorize sender (sets fax_uuid; dispatches notification on failure)
        if (!$this->resolveAuthorization($fromEmail, $phoneNumber, $request)) {
            fax_webhook_debug('MailgunWebhookProfile: sender authorization failed', [
                'from'            => $fromEmail,
                'raw_destination' => $phoneNumber,
            ]);
            return false;
        }

        // 4. Normalize destination using the tenant's country setting
        $this->resolveDestination($phoneNumber, $request['fax_uuid'], $request);

        // 5. Normalize the rest of the fields the FaxSendService expects
        $attachments = $this->storeAttachments($request);
        $request->merge([
            'from'             => $fromEmail,
            'subject'          => $request['subject'] ?? $request['Subject'] ?? '',
            'body'             => $request['body-plain'] ?? $request['stripped-text'] ?? $request['body-html'] ?? '',
            'fax_attachments'  => $attachments,
        ]);

        fax_webhook_debug('MailgunWebhookProfile: email-to-fax webhook normalized', [
            'from'              => $fromEmail,
            'fax_uuid'          => $request['fax_uuid'] ?? null,
            'raw_destination'   => $phoneNumber,
            'fax_destination'   => $request['fax_destination'] ?? null,
            'subject_present'   => !empty($request['subject'] ?? $request['Subject'] ?? ''),
            'attachment_count'  => count($attachments),
            'attachment_names'  => collect($attachments)->pluck('original_name')->values()->all(),
        ]);

        return true;
    }

    /**
     * Persist Mailgun's multipart attachments to the fax disk.
     */
    private function storeAttachments(Request $request): array
    {
        $stored = [];
        foreach ($request->allFiles() as $key => $file) {
            // Mailgun names attachment fields attachment-1, attachment-2, ...
            if (!str_starts_with($key, 'attachment-')) {
                continue;
            }
            $meta = FaxSendService::storeUploadedAttachment($file);
            if ($meta !== null) {
                $stored[] = $meta;
                fax_webhook_debug('MailgunWebhookProfile: attachment stored', [
                    'field'         => $key,
                    'original_name' => $meta['original_name'] ?? null,
                    'extension'     => $meta['extension'] ?? null,
                    'mime_type'     => $meta['mime_type'] ?? null,
                ]);
            } else {
                fax_webhook_debug('MailgunWebhookProfile: attachment skipped', [
                    'field'         => $key,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }
        }
        return $stored;
    }
}
