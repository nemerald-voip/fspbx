<?php

namespace App\Services\AiTools;

class RetellSignatureValidator
{
    private const MAX_AGE_MILLISECONDS = 300000;

    public function valid(string $rawBody, ?string $signature, string $apiKey, ?int $nowMilliseconds = null): bool
    {
        if (! preg_match('/^v=(\d+),d=([0-9a-f]{64})$/', (string) $signature, $matches)) {
            return false;
        }

        $timestamp = (int) $matches[1];
        $nowMilliseconds ??= (int) floor(microtime(true) * 1000);

        if (abs($nowMilliseconds - $timestamp) > self::MAX_AGE_MILLISECONDS) {
            return false;
        }

        $digest = hash_hmac('sha256', $rawBody . $timestamp, $apiKey);

        return hash_equals($digest, $matches[2]);
    }
}
