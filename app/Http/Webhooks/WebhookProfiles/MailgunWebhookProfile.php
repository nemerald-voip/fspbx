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
            logger('MailgunWebhookProfile: no valid recipient in request');
            return false;
        }
        $phoneNumber = strstr($recipientEmail, '@', true);

        // 2. Extract sender
        $fromEmail = $this->extractEmail($request['from'] ?? $request['From'] ?? null);
        if (!$fromEmail) {
            logger('MailgunWebhookProfile: no valid sender in request');
            return false;
        }

        // 3. Authorize sender (sets fax_uuid; dispatches notification on failure)
        if (!$this->resolveAuthorization($fromEmail, $phoneNumber, $request)) {
            return false;
        }

        // 4. Normalize destination using the tenant's country setting
        $this->resolveDestination($phoneNumber, $request['fax_uuid'], $request);

        // 5. Normalize the rest of the fields the FaxSendService expects
        $request->merge([
            'from'             => $fromEmail,
            'subject'          => $request['subject'] ?? $request['Subject'] ?? '',
            'body'             => $request['body-plain'] ?? $request['stripped-text'] ?? $request['body-html'] ?? '',
            'fax_attachments'  => $this->storeAttachments($request),
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
            }
        }
        return $stored;
    }
}
