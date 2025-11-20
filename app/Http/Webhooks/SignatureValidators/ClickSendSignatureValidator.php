<?php

namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;

class ClickSendSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        // 1. Retrieve the subaccount_id from the incoming request payload
        $payloadSubaccountId = $request->input('subaccount_id');

        // 2. Retrieve the expected subaccount_id from your system config.
        $expectedSubaccountId = config('clicksend.subaccount_id') ?? null;

        // 3. Validate
        if (!$payloadSubaccountId) {
            logger('ClickSend Webhook Validation Failed: No subaccount_id in payload.', $request->all());
            return false;
        }

        // Strict comparison to ensure types match (string vs int issues are common, so we cast to string)
        if ((string) $payloadSubaccountId !== (string) $expectedSubaccountId) {
            logger("ClickSend Webhook Validation Failed: Invalid subaccount_id. Received: {$payloadSubaccountId}, Expected: {$expectedSubaccountId}");
            return false;
        }

        return true;
    }
}