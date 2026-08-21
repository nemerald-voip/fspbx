<?php

namespace App\Services;

class SipRegistrationAddressResolver
{
    /**
     * Resolve display addresses from the registration fields retained by FreeSWITCH.
     *
     * @return array{contact: string, user: string, transport: string, lan_ip: string, wan_ip: string, port: string}
     */
    public function resolve(
        string $contact,
        string $callId,
        string $agent,
        string $networkIp = '',
        string|int|null $networkPort = null
    ): array {
        $contactUri = $this->extractSipUri($contact);
        $contactParts = $this->parseSipUri($contactUri);
        $fsPathParts = $this->parseSipUri(
            rawurldecode($this->extractParameter($contact, 'fs_path') ?? '')
        );
        $receivedParts = $this->parseHostPort(
            $this->extractParameter($contact, 'received') ?? ''
        );

        $contactIp = $this->validIp($contactParts['host'] ?? null);
        $realIp = $this->validIp(
            $this->parseHostPort($this->extractParameter($contact, 'real') ?? '')['host'] ?? null
        );
        $callIdIp = $this->callIdIp($callId, $agent);

        $lanIp = $this->firstLocalIp([$contactIp, $realIp, $callIdIp]);
        if ($lanIp === '' && $contactIp === null && isset($contactParts['host'])) {
            // Preserve hostname contacts used by hosted phone services and phone-control targeting.
            $lanIp = $contactParts['host'];
        }

        $wanIp = $this->firstPublicIp([
            $fsPathParts['host'] ?? null,
            $receivedParts['host'] ?? null,
            $networkIp,
        ]);

        $port = $this->firstValidPort([
            $contactParts['port'] ?? null,
            $fsPathParts['port'] ?? null,
            $receivedParts['port'] ?? null,
            $networkPort,
        ]);

        if ($port === '' && $contactParts !== []) {
            $port = ($contactParts['scheme'] ?? 'sip') === 'sips' ? '5061' : '5060';
        }

        $transport = $this->extractParameter($contact, 'transport');

        return [
            'contact' => $contactUri,
            'user' => (string) ($contactParts['user'] ?? ''),
            'transport' => $transport !== null ? strtoupper($transport) : '',
            'lan_ip' => $lanIp,
            'wan_ip' => $wanIp,
            'port' => $port,
        ];
    }

    private function extractSipUri(string $contact): string
    {
        if (preg_match('/<\s*((?:sips?):[^>]+)>/i', $contact, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/((?:sips?):\S+)/i', $contact, $matches)) {
            return trim($matches[1], " \t\n\r\0\x0B<>");
        }

        return trim($contact);
    }

    /**
     * @return array{scheme: string, user: string, host: string, port: string|null}|array{}
     */
    private function parseSipUri(string $uri): array
    {
        if (! preg_match(
            '/^(sips?):([^@;]+)@(\[[^\]]+\]|[^;:?\s>]+)(?::(\d+))?(?:[;?]|$)/i',
            trim($uri),
            $matches
        )) {
            return [];
        }

        return [
            'scheme' => strtolower($matches[1]),
            'user' => $matches[2],
            'host' => trim($matches[3], '[]'),
            'port' => $matches[4] ?? null,
        ];
    }

    /**
     * @return array{host: string, port: string|null}|array{}
     */
    private function parseHostPort(string $value): array
    {
        $value = trim($value, " \t\n\r\0\x0B\"");

        if ($value === '') {
            return [];
        }

        if (preg_match('/^\[([^\]]+)](?::(\d+))?$/', $value, $matches)) {
            return [
                'host' => $matches[1],
                'port' => $matches[2] ?? null,
            ];
        }

        if (preg_match('/^([^:;]+)(?::(\d+))?$/', $value, $matches)) {
            return [
                'host' => $matches[1],
                'port' => $matches[2] ?? null,
            ];
        }

        return [];
    }

    private function extractParameter(string $contact, string $name): ?string
    {
        $name = preg_quote($name, '/');

        if (preg_match('/(?:^|[;?])'.$name.'=([^;>\s]+)/i', $contact, $matches)) {
            return trim($matches[1], " \t\n\r\0\x0B\"");
        }

        return null;
    }

    private function callIdIp(string $callId, string $agent): ?string
    {
        $atPosition = strrpos($callId, '@');

        if ($atPosition === false) {
            return null;
        }

        $candidate = trim(substr($callId, $atPosition + 1));

        if (stripos($agent, 'grandstream') !== false || stripos($agent, 'ooma') !== false) {
            $candidate = str_ireplace(
                ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'],
                ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                $candidate
            );
        } elseif (preg_match('/\bCL750A\b/i', $agent)) {
            $candidate = str_replace('_', '.', $candidate);
        }

        $candidateParts = $this->parseHostPort($candidate);

        return $this->validIp($candidateParts['host'] ?? $candidate);
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    private function firstLocalIp(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $ip = $this->validIp($candidate);

            if ($ip !== null && ! $this->isPublicIp($ip)) {
                return $ip;
            }
        }

        return '';
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    private function firstPublicIp(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $ip = $this->validIp($candidate);

            if ($ip !== null && $this->isPublicIp($ip)) {
                return $ip;
            }
        }

        return '';
    }

    private function validIp(mixed $candidate): ?string
    {
        if (! is_string($candidate) && ! is_int($candidate)) {
            return null;
        }

        $ip = trim((string) $candidate, '[]');

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    private function firstValidPort(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $port = filter_var($candidate, FILTER_VALIDATE_INT, [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 65535,
                ],
            ]);

            if ($port !== false) {
                return (string) $port;
            }
        }

        return '';
    }
}
