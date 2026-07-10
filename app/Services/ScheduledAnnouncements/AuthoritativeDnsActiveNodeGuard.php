<?php

namespace App\Services\ScheduledAnnouncements;

use App\Models\DefaultSettings;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Throwable;

class AuthoritativeDnsActiveNodeGuard
{
    /**
     * @param  string|null  $fqdn  Override the active FQDN (e.g. another feature's
     *                             failover record) instead of reading the
     *                             scheduled-announcements settings.
     * @param  array|null  $nodeIps  Override this node's IPs (auto-detected list)
     *                               instead of reading scheduled_announcements_node_ips.
     */
    public function canExecute(?FreeswitchEslService $esl = null, ?string $fqdn = null, ?array $nodeIps = null): array
    {
        $fqdn = ($fqdn !== null && trim($fqdn) !== '')
            ? strtolower(trim($fqdn, " \t\n\r\0\x0B."))
            : $this->activeFqdn();
        $nodeIps = $nodeIps !== null ? $this->normalizeIps($nodeIps) : $this->nodeIps();

        if (empty($fqdn)) {
            return $this->result(false, 'active_unknown', 'No active FQDN could be determined from settings or APP_URL.', $fqdn, [], [], $nodeIps);
        }

        if (empty($nodeIps)) {
            return $this->result(false, 'active_unknown', 'No local or external node IP addresses could be determined.', $fqdn, [], [], $nodeIps);
        }

        $zone = $this->authoritativeZone($fqdn);
        if (empty($zone)) {
            return $this->result(false, 'active_unknown', 'Unable to discover the authoritative DNS zone.', $fqdn, [], [], $nodeIps);
        }

        $nameservers = $this->nameserversForZone($zone);
        if (empty($nameservers)) {
            return $this->result(false, 'active_unknown', 'Unable to discover authoritative nameservers.', $fqdn, [], [], $nodeIps);
        }

        if (! $this->digIsAvailable()) {
            return $this->result(
                false,
                'active_unknown',
                'The dig DNS lookup command is not available.',
                $fqdn,
                $nameservers,
                [],
                $nodeIps
            );
        }

        $answersByNameserver = [];
        $lookupFailures = [];
        foreach ($nameservers as $nameserver) {
            $answers = $this->authoritativeAnswers($fqdn, $nameserver, $lookupFailures);

            if ($answers === null) {
                continue;
            }

            $answersByNameserver[$nameserver] = $answers;
        }

        if (empty($answersByNameserver)) {
            return $this->result(
                false,
                'active_unknown',
                'No authoritative nameservers answered.',
                $fqdn,
                $nameservers,
                [],
                $nodeIps,
                ['lookup_failures' => $lookupFailures]
            );
        }

        $first = null;
        foreach ($answersByNameserver as $answers) {
            $normalized = array_values(array_unique($answers));
            sort($normalized);

            if ($first === null) {
                $first = $normalized;
                continue;
            }

            if ($first !== $normalized) {
                return $this->result(
                    false,
                    'active_unknown',
                    'Authoritative nameservers disagreed.',
                    $fqdn,
                    $nameservers,
                    $answersByNameserver,
                    $nodeIps,
                    ['lookup_failures' => $lookupFailures]
                );
            }
        }

        if (empty($first)) {
            return $this->result(
                false,
                'active_unknown',
                'Authoritative DNS returned no address records.',
                $fqdn,
                $nameservers,
                $answersByNameserver,
                $nodeIps,
                ['lookup_failures' => $lookupFailures]
            );
        }

        $matchesNode = ! empty(array_intersect($first ?? [], $nodeIps));
        if (! $matchesNode) {
            return $this->result(
                false,
                'standby',
                'Authoritative DNS points to another node.',
                $fqdn,
                $nameservers,
                $answersByNameserver,
                $nodeIps,
                ['lookup_failures' => $lookupFailures]
            );
        }

        if ($esl !== null && ! $this->freeswitchIsHealthy($esl)) {
            return $this->result(
                false,
                'active_unknown',
                'FreeSWITCH ESL health is uncertain.',
                $fqdn,
                $nameservers,
                $answersByNameserver,
                $nodeIps,
                ['lookup_failures' => $lookupFailures]
            );
        }

        return $this->result(
            true,
            'active',
            'Authoritative DNS points to this node.',
            $fqdn,
            $nameservers,
            $answersByNameserver,
            $nodeIps,
            ['lookup_failures' => $lookupFailures]
        );
    }

    private function activeFqdn(): ?string
    {
        $setting = $this->setting('scheduled_announcements_active_fqdn');
        if (! empty($setting)) {
            return strtolower(trim($setting, " \t\n\r\0\x0B."));
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $host ? strtolower(trim($host, " \t\n\r\0\x0B.")) : null;
    }

    private function authoritativeZone(string $fqdn): ?string
    {
        $configuredZone = $this->setting('scheduled_announcements_authoritative_zone');
        if (! empty($configuredZone)) {
            return strtolower(trim($configuredZone, " \t\n\r\0\x0B."));
        }

        $labels = explode('.', $fqdn);
        for ($i = 0; $i < count($labels) - 1; $i++) {
            $candidate = implode('.', array_slice($labels, $i));

            if (! empty(@dns_get_record($candidate, DNS_NS))) {
                return strtolower($candidate);
            }
        }

        return null;
    }

    private function nameserversForZone(string $zone): array
    {
        $records = @dns_get_record($zone, DNS_NS) ?: [];
        $nameservers = [];

        foreach ($records as $record) {
            $target = $record['target'] ?? null;
            if ($target) {
                $nameservers[] = strtolower(rtrim($target, '.'));
            }
        }

        return array_values(array_unique($nameservers));
    }

    private function authoritativeAnswers(string $fqdn, string $nameserver, array &$lookupFailures): ?array
    {
        $answers = [];
        $answered = false;
        foreach (['A', 'AAAA'] as $type) {
            $timeout = $this->dnsTimeoutSeconds();

            try {
                $result = Process::timeout($timeout + 1)->run([
                    'dig',
                    '-4',
                    '+short',
                    '+tries=1',
                    '+time=' . $timeout,
                    $fqdn,
                    $type,
                    '@' . $nameserver,
                ]);
            } catch (Throwable $e) {
                $lookupFailures[$nameserver][$type] = $e->getMessage();

                continue;
            }

            if (! $result->successful()) {
                $lookupFailures[$nameserver][$type] = trim($result->errorOutput() ?: $result->output())
                    ?: 'dig exited with status ' . $result->exitCode();

                continue;
            }

            $answered = true;
            foreach (explode("\n", trim($result->output())) as $line) {
                $line = trim($line);
                if ($line !== '' && filter_var($line, FILTER_VALIDATE_IP)) {
                    $answers[] = $this->normalizeIp($line);
                }
            }
        }

        return $answered ? array_values(array_unique($answers)) : null;
    }

    private function digIsAvailable(): bool
    {
        return Cache::remember('scheduled_announcements_dig_available', 300, function () {
            try {
                return Process::run(['which', 'dig'])->successful();
            } catch (Throwable) {
                return false;
            }
        });
    }

    private function nodeIps(): array
    {
        $configuredIps = $this->setting('scheduled_announcements_node_ips');
        if (! empty($configuredIps)) {
            return $this->normalizeIps(preg_split('/[\s,]+/', $configuredIps) ?: []);
        }

        return Cache::remember('scheduled_announcements_node_ips:auto', 300, function () {
            $ips = [];

            try {
                $hostnameIps = Process::timeout(1)->run(['hostname', '-I'])->output();
                $ips = array_merge($ips, preg_split('/\s+/', trim($hostnameIps)) ?: []);
            } catch (Throwable) {
                // Continue to external discovery.
            }

            foreach (['https://api.ipify.org', 'https://checkip.amazonaws.com', 'https://ifconfig.me/ip'] as $endpoint) {
                try {
                    $response = Http::timeout(1)->get($endpoint);
                    if ($response->successful()) {
                        $ips[] = trim($response->body());
                    }
                } catch (Throwable) {
                    // External discovery is best-effort; failing closed happens above.
                }
            }

            return $this->normalizeIps($ips);
        });
    }

    private function normalizeIps(array $ips): array
    {
        $normalized = [];
        foreach ($ips as $ip) {
            $ip = $this->normalizeIp((string) $ip);

            if ($ip !== null && ! $this->isPrivateOrLoopback($ip)) {
                $normalized[] = $ip;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeIp(string $ip): ?string
    {
        $ip = trim($ip, " \t\n\r\0\x0B[]");

        return filter_var($ip, FILTER_VALIDATE_IP) ? strtolower($ip) : null;
    }

    private function isPrivateOrLoopback(string $ip): bool
    {
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    private function freeswitchIsHealthy(FreeswitchEslService $esl): bool
    {
        try {
            if (! $esl->isConnected()) {
                $esl->reconnect();

                if (! $esl->isConnected()) {
                    return false;
                }
            }

            return trim((string) $esl->executeCommand('switchname', false)) !== '';
        } catch (Throwable) {
            return false;
        }
    }

    private function dnsTimeoutSeconds(): int
    {
        $milliseconds = (int) ($this->setting('scheduled_announcements_dns_timeout_ms') ?: 800);

        return max(1, (int) ceil($milliseconds / 1000));
    }

    private function setting(string $subcategory): ?string
    {
        $value = DefaultSettings::where('default_setting_category', 'scheduled_jobs')
            ->where('default_setting_subcategory', $subcategory)
            ->where('default_setting_enabled', true)
            ->value('default_setting_value');

        return is_string($value) ? trim($value) : null;
    }

    private function result(
        bool $active,
        string $status,
        string $reason,
        ?string $fqdn,
        array $nameservers,
        array $answers,
        array $nodeIps,
        array $extra = []
    ): array {
        return array_merge([
            'active' => $active,
            'status' => $status,
            'reason' => $reason,
            'fqdn' => $fqdn,
            'nameservers' => $nameservers,
            'answers' => $answers,
            'node_ips' => $nodeIps,
        ], $extra);
    }
}
