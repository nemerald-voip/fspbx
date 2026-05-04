<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Illuminate\Http\Request;
use App\Services\FaxSendService;

class PostmarkWebhookProfile extends FaxWebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        // 1. Extract raw destination
        $toEmail = $request['ToFull'][0]['Email'] ?? null;
        if (!$toEmail || strpos($toEmail, '@') === false) {
            logger('PostmarkWebhookProfile: no valid recipient in request');
            return false;
        }
        $phoneNumber = strstr($toEmail, '@', true);

        // 2. Extract sender
        $fromEmail = strtolower($request['FromFull']['Email'] ?? '');
        if (!$fromEmail) {
            logger('PostmarkWebhookProfile: no valid sender in request');
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
            'subject'          => $request['Subject'] ?? '',
            'body'             => $request['TextBody'] ?? '',
            'fax_attachments'  => $this->storeAttachments($request),
        ]);

        return true;
    }

    /**
     * Decode and persist Postmark's base64 attachments to the fax disk.
     */
    private function storeAttachments(Request $request): array
    {
        $stored = [];
        foreach ($request['Attachments'] ?? [] as $attachment) {
            $meta = FaxSendService::storeBase64Attachment(
                $attachment['Name'] ?? '',
                $attachment['Content'] ?? '',
                $attachment['ContentType'] ?? null
            );
            if ($meta !== null) {
                $stored[] = $meta;
            }
        }
        return $stored;
    }
}
