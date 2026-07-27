<?php

namespace App\Services;

use Illuminate\Support\Str;

class YealinkRpsRequestSigner
{
    public function headers(
        string $method,
        string $path,
        string $accessKeyId,
        string $accessKeySecret,
        ?string $body = null,
        array $query = [],
        ?string $timestamp = null,
        ?string $nonce = null,
    ): array {
        $method = strtoupper($method);
        $path = '/' . ltrim($path, '/');
        $timestamp ??= (string) (int) floor(microtime(true) * 1000);
        $nonce ??= str_replace('-', '', Str::uuid()->toString());

        $headers = [
            'X-Ca-Key' => $accessKeyId,
            'X-Ca-Nonce' => $nonce,
            'X-Ca-Timestamp' => $timestamp,
        ];

        if ($body !== null) {
            $headers['Content-MD5'] = base64_encode(md5($body, true));
        }

        $signatureHeaders = collect($headers)
            ->sortKeys()
            ->map(fn (string $value, string $key) => "{$key}:{$value}")
            ->implode("\n");

        $stringToSign = $method . "\n"
            . $signatureHeaders . "\n"
            . ltrim($path, '/');

        if ($body === null) {
            $stringToSign .= "\n" . $this->formatQuery($query);
        }

        $headers['X-Ca-Signature'] = base64_encode(
            hash_hmac('sha256', $stringToSign, $accessKeySecret, true)
        );

        return $headers;
    }

    private function formatQuery(array $query): string
    {
        ksort($query);

        return collect($query)
            ->map(function (mixed $value, string $key) {
                $value = trim((string) $value);

                return $value === '' ? $key : "{$key}={$value}";
            })
            ->implode('&');
    }
}
