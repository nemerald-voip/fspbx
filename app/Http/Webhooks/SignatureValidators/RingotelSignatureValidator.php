<?php
namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;

class RingotelSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config) : bool {
        
        //If there is a way to validate that this request came from Ringotel then let's implement the logic here

        $apiKey = config('ringotel.token') ?? get_domain_setting('ringotel_api_token');  // Retrieve the API key

        return $request['api_key'] === $apiKey;
    }
}