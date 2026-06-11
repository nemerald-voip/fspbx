<?php

namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class TwilioSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $twilioSignature = (string) $request->header('X-Twilio-Signature', '');
        $authToken       = (string) config('twilio.auth_token');

        if ($twilioSignature === '' || $authToken === '') {
            messaging_webhook_debug('TwilioSignatureValidator missing signature or auth token', [
                'has_signature' => $twilioSignature !== '',
                'has_auth_token' => $authToken !== '',
            ]);

            return false;
        }

        $expected = $this->computeSignature($request, $authToken);
        $isValid  = hash_equals($expected, $twilioSignature);

        messaging_webhook_debug('TwilioSignatureValidator verification completed', [
            'is_valid' => $isValid,
        ]);

        return $isValid;
    }

    /**
     * Twilio signature algorithm:
     * 1. Start with the full request URL
     * 2. Sort POST params alphabetically by key
     * 3. Append each {key}{value} (no separator) to the URL string
     * 4. HMAC-SHA1 the result using auth_token, then Base64-encode
     */
    protected function computeSignature(Request $request, string $authToken): string
    {
        $url    = $request->fullUrl();
        $params = $this->originalPostParams($request);

        ksort($params);

        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }

        return base64_encode(hash_hmac('sha1', $data, $authToken, true));
    }

    /**
     * Recover the original, unmodified POST parameters from the raw request body.
     *
     * We cannot use $request->post() here: Laravel's TrimStrings and
     * ConvertEmptyStringsToNull middleware run before this validator and mutate
     * the parameter bag, which changes the recomputed hash and breaks signature
     * verification (notably for Messaging Service webhooks). Twilio signs the
     * values exactly as sent, so we parse them straight from the raw body.
     *
     * @return array<string, mixed>
     */
    protected function originalPostParams(Request $request): array
    {
        $content = $request->getContent();

        if ($content === '') {
            return $request->post();
        }

        $params = [];
        parse_str($content, $params);

        return $params;
    }
}
