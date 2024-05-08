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
logger('validator');
        $apiKey = config('ringotel.token');  // Retrieve the API key from the config file

        return $request['api_key'] === $apiKey;
    }
}