<?php

namespace App\Services\CallWebhooks;

use InvalidArgumentException;

class PublicWebhookUrlValidator
{
    public function validateAndResolve(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new InvalidArgumentException('The webhook endpoint must use HTTPS.');
        }

        if (! empty($parts['user']) || ! empty($parts['pass'])) {
            throw new InvalidArgumentException('The webhook endpoint cannot contain embedded credentials.');
        }

        $host = trim((string) ($parts['host'] ?? ''), '[]');
        if ($host === '') {
            throw new InvalidArgumentException('The webhook endpoint must include a valid host.');
        }

        $port = (int) ($parts['port'] ?? 443);
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('The webhook endpoint port is invalid.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : $this->resolveHost($host);

        if ($addresses === []) {
            throw new InvalidArgumentException('The webhook endpoint host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw new InvalidArgumentException('The webhook endpoint must resolve only to public IP addresses.');
            }
        }

        return $addresses[0];
    }

    protected function resolveHost(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($address && filter_var($address, FILTER_VALIDATE_IP)) {
                $addresses[] = $address;
            }
        }

        if ($addresses === []) {
            foreach (@gethostbynamel($host) ?: [] as $address) {
                if (filter_var($address, FILTER_VALIDATE_IP)) {
                    $addresses[] = $address;
                }
            }
        }

        return array_values(array_unique($addresses));
    }

    private function isPublicAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
