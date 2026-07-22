<?php

namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;
use Symfony\Component\HttpFoundation\IpUtils;

class FiberneticsSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $clientIp = (string) $request->ip();

        foreach (config('fibernetics.webhook_ips', []) as $cidr) {
            try {
                if (IpUtils::checkIp($clientIp, $cidr)) {
                    return true;
                }
            } catch (InvalidArgumentException) {
                // Invalid configured networks never grant access.
            }
        }

        messaging_webhook_debug('Fibernetics webhook rejected by IP allowlist', [
            'client_ip' => $clientIp,
        ]);

        return false;
    }
}
