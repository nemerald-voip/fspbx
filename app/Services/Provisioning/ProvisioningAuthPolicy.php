<?php

namespace App\Services\Provisioning;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\IpUtils;

class ProvisioningAuthPolicy
{
    public function cidrs(array $settings): array
    {
        return $this->nonEmptyValues($settings['cidr'] ?? null);
    }

    public function username(array $settings): string
    {
        $value = $settings['http_auth_username'] ?? null;

        return is_scalar($value) ? trim((string) $value) : '';
    }

    public function passwords(array $settings): array
    {
        return $this->nonEmptyValues($settings['http_auth_password'] ?? null);
    }

    public function requiresHttpAuthentication(array $settings): bool
    {
        return $this->username($settings) !== '' && $this->passwords($settings) !== [];
    }

    /**
     * An empty CIDR list does not restrict the request. When CIDRs are present,
     * at least one must match. Invalid CIDRs never grant access.
     */
    public function clientIpAllowed(array $cidrs, string $clientIp): bool
    {
        if ($cidrs === []) {
            return true;
        }

        foreach ($cidrs as $cidr) {
            try {
                if (IpUtils::checkIp($clientIp, $cidr)) {
                    return true;
                }
            } catch (InvalidArgumentException) {
                // Ignore malformed rows. If none of the configured rows match,
                // the CIDR requirement fails closed.
            }
        }

        return false;
    }

    private function nonEmptyValues(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return collect($values)
            ->filter(fn ($item) => is_scalar($item))
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn (string $item) => $item !== '')
            ->unique()
            ->values()
            ->all();
    }
}
