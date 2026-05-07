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
            fax_webhook_debug('PostmarkWebhookProfile: no valid recipient in request', [
                'recipient' => $toEmail,
            ]);
            return false;
        }
        $phoneNumber = strstr($toEmail, '@', true);

        // 2. Extract sender
        $fromEmail = strtolower($request['FromFull']['Email'] ?? '');
        if (!$fromEmail) {
            fax_webhook_debug('PostmarkWebhookProfile: no valid sender in request', [
                'recipient' => $toEmail,
                'from'      => $request['FromFull']['Email'] ?? null,
            ]);
            return false;
        }

        fax_webhook_debug('PostmarkWebhookProfile: email-to-fax webhook received', [
            'from'            => $fromEmail,
            'recipient'       => $toEmail,
            'raw_destination' => $phoneNumber,
        ]);

        // 3. Authorize sender (sets fax_uuid; dispatches notification on failure)
        if (!$this->resolveAuthorization($fromEmail, $phoneNumber, $request)) {
            fax_webhook_debug('PostmarkWebhookProfile: sender authorization failed', [
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
            'subject'          => $request['Subject'] ?? '',
            'body'             => $request['TextBody'] ?? '',
            'fax_attachments'  => $attachments,
        ]);

        fax_webhook_debug('PostmarkWebhookProfile: email-to-fax webhook normalized', [
            'from'              => $fromEmail,
            'fax_uuid'          => $request['fax_uuid'] ?? null,
            'raw_destination'   => $phoneNumber,
            'fax_destination'   => $request['fax_destination'] ?? null,
            'subject_present'   => !empty($request['Subject'] ?? ''),
            'attachment_count'  => count($attachments),
            'attachment_names'  => collect($attachments)->pluck('original_name')->values()->all(),
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
                fax_webhook_debug('PostmarkWebhookProfile: attachment stored', [
                    'original_name' => $meta['original_name'] ?? null,
                    'extension'     => $meta['extension'] ?? null,
                    'mime_type'     => $meta['mime_type'] ?? null,
                ]);
            } else {
                fax_webhook_debug('PostmarkWebhookProfile: attachment skipped', [
                    'original_name' => $attachment['Name'] ?? '',
                    'mime_type'     => $attachment['ContentType'] ?? null,
                ]);
            }
        }
        return $stored;
    }
}
