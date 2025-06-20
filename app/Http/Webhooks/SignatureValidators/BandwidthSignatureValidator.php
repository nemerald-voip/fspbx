<?php
namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;

class BandwidthSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config) : bool {
        

        logger($request);

        // logger('messageId: '. $messageId);
        // logger('apiKey: '. $apiKey);
        // logger('verificationToken: '. $verificationToken);
        // logger('computedHash: '. $computedHash);


        return true;
    }
}