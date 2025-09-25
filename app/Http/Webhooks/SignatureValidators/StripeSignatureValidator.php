<?php
namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Stripe\Webhook as StripeWebhook;
use Spatie\WebhookClient\WebhookConfig;
use Stripe\Exception\SignatureVerificationException;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;

class StripeSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {

        // Header name & secret come from the WebhookConfig object
        $signatureHeader = $config->signatureHeaderName ?? 'Stripe-Signature';

        $settingKey = 'webhook_secret';
        $signingSecret = gateway_setting('stripe', $settingKey);

        if (empty($signingSecret)) {
            logger("StripeSignatureValidator: missing signing secret for stripe:{$settingKey}");
            return false;
        }

        try {
            // Throws if body or signature is invalid
            StripeWebhook::constructEvent(
                $request->getContent(),
                $request->header($signatureHeader),
                $signingSecret,
                300 // optional tolerance (seconds)
            );
            return true;
        } catch (SignatureVerificationException $e) {
            logger('StripeSignatureValidator@isValid  Stripe sig verify failed: '.$e->getMessage());
            return false;
        } catch (\Throwable $e) {
            logger('StripeSignatureValidator@isValid Stripe webhook error: '.$e->getMessage());
            return false;
        }
    }
}
