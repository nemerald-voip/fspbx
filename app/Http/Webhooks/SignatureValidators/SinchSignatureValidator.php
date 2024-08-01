<?php
namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;

class SinchSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config) : bool {
        
        //If there is a way to validate that this request came from Sinch then let's implement the logic here
        // Otherwise it's defaulted to athenticate all post requests
        $apiKey = config('sinch.inbound_api_key');  // Retrieve the API key from the config file
        $messageId = $request->header('messageID');  // Assuming the message ID is in the header
        $verificationToken = $request->header('verificationToken');  // Assuming the token is in the header
        $computedHash = hash('sha256', $apiKey . $messageId);  // Compute the SHA256 hash

        // logger('apiKey: '. $apiKey);
        // logger('verificationToken: '. $verificationToken);
        // logger('computedHash: '. $computedHash);

        if ($computedHash != $verificationToken) logger($request->all());

        return $computedHash === $verificationToken;

        // return true;
    }
}