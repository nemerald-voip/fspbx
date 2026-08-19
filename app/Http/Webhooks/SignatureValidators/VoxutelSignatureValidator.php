<?php

namespace App\Http\Webhooks\SignatureValidators;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class VoxutelSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $sharedKey = (string) config('voxutel.inbound_api_key');
        $timestamp = (string) $request->header('X-Voxutel-Timestamp');
        $providedSignature = (string) $request->header('X-Voxutel-Signature');

        if ($sharedKey === '' || $timestamp === '' || $providedSignature === '') {
            return false;
        }

        try {
            $timestampValue = CarbonImmutable::parse($timestamp);
        } catch (\Throwable) {
            return false;
        }

        $age = abs(now()->getTimestamp() - $timestampValue->getTimestamp());
        if ($age > (int) config('voxutel.inbound_signature_tolerance_seconds', 300)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $sharedKey);
        $providedSignature = preg_replace('/^sha256=/i', '', $providedSignature);

        return is_string($providedSignature) && hash_equals($expectedSignature, $providedSignature);
    }
}
