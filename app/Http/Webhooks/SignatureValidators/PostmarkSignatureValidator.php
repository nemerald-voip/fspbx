<?php

namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class PostmarkSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $username = (string) config('services.postmark.webhook_username');
        $password = (string) config('services.postmark.webhook_password');

        // Missing configuration must never allow unauthenticated webhooks.
        if ($username === '' || $password === '') {
            throw new UnauthorizedHttpException('Basic realm="Postmark webhook"');
        }

        $usernameMatches = hash_equals($username, (string) $request->getUser());
        $passwordMatches = hash_equals($password, (string) $request->getPassword());

        if (! $usernameMatches || ! $passwordMatches) {
            // Stop before the webhook profile, storage, and queue dispatch.
            throw new UnauthorizedHttpException('Basic realm="Postmark webhook"');
        }

        return true;
    }
}
