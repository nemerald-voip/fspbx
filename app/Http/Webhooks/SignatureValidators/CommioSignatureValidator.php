<?php

namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;

class CommioSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {

        //If there is a way to validate that this request came from Commio then let's implement the logic here
        // Otherwise it's defaulted to athenticate all post requests

        // Your secret token (keep this secure)
        $secretToken = config("commio.webhook_secret");

        // Canonicalize the JSON body
        $canonicalizedJson = $this->canonicalizeRequest($request->all());

        // Create the base string for the HMAC (URL joined with canonicalized JSON)
        $url = $request->fullUrl();
        $baseString = $url . $canonicalizedJson;

        // Generate the HMAC-SHA1 hash
        $hmac = hash_hmac('sha1', $baseString, $secretToken, true);
        $base64Hmac = base64_encode($hmac);

        // Retrieve the X-Commio-Signature header
        $headerSignature = $request->header('X-Commio-Signature');

        return $base64Hmac === $headerSignature || $request->header('user-agent') == "thinq-sms";
    }

    private function canonicalizeRequest($json)
    {
        $canonicalized = $this->sortArrayByKey($json);
        return json_encode($canonicalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function sortArrayByKey($array)
    {
        $keys = array_keys($array);
        sort($keys);
        $sortedArray = [];
        foreach ($keys as $key) {
            if (is_array($array[$key])) {
                $sortedArray[$key] = $this->sortArrayByKey($array[$key]);
            } else {
                $sortedArray[$key] = $array[$key];
            }
        }
        return $sortedArray;
    }
}
