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
        $resolvedAliases = [];
        foreach ($nameservers as $nameserver) {
            $answers = $this->authoritativeAnswers($fqdn, $nameserver, $lookupFailures, [], $resolvedAliases);

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
            $reason = 'Authoritative DNS returned no address records.';
            foreach ($lookupFailures as $name => $failures) {
                if (isset($failures['CNAME'])) {
                    $reason = 'Unable to resolve '.$name.': '.$failures['CNAME'];
                    break;
                }
            }

            return $this->result(
                false,
                'active_unknown',
                $reason,
                $fqdn,
                $nameservers,
                $answersByNameserver,
                $nodeIps,
                ['lookup_failures' => $lookupFailures]
            );
        }

        $matchesNode = ! empty(array_intersect($first ?? [], $nodeIps));
        if ($matchesNode && ! empty(array_diff($first, $nodeIps))) {
            return $this->result(
                false,
                'active_unknown',
                'Authoritative DNS includes both this node and other addresses.',
                $fqdn,
                $nameservers,
                $answersByNameserver,
                $nodeIps,
                ['lookup_failures' => $lookupFailures]
            );
        }

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

    private function authoritativeZone(string $fqdn, bool $useConfiguredZone = true): ?string
    {
        $configuredZone = $useConfiguredZone ? $this->setting('scheduled_announcements_authoritative_zone') : null;
        if (! empty($configuredZone)) {
            return strtolower(trim($configuredZone, " \t\n\r\0\x0B."));
        }

        $labels = explode('.', $fqdn);
        for ($i = 0; $i < count($labels) - 1; $i++) {
            $candidate = implode('.', array_slice($labels, $i));

            if (! empty($this->dnsRecords($candidate, DNS_NS))) {
                return strtolower($candidate);
            }
        }

        return null;
    }

    private function nameserversForZone(string $zone): array
    {
        $records = $this->dnsRecords($zone, DNS_NS);
        $nameservers = [];

        foreach ($records as $record) {
            $target = $record['target'] ?? null;
            if ($target) {
                $nameservers[] = strtolower(rtrim($target, '.'));
            }
        }

        return array_values(array_unique($nameservers));
    }

    protected function dnsRecords(string $hostname, int $type): array
    {
        return @dns_get_record($hostname, $type) ?: [];
    }

    private function authoritativeAnswers(
        string $fqdn,
        string $nameserver,
        array &$lookupFailures,
        array $visited,
        array &$resolvedAliases
    ): ?array
    {
        if (in_array($fqdn, $visited, true) || count($visited) >= 8) {
            $lookupFailures[$fqdn]['CNAME'] = 'CNAME loop or maximum chain length exceeded.';

            return [];
        }

        $visited[] = $fqdn;
        $answers = [];
        $aliases = [];
        $statuses = [];
        foreach (['A', 'AAAA'] as $type) {
            $timeout = $this->dnsTimeoutSeconds();

            try {
                $result = Process::timeout($timeout + 1)->run([
                    'dig',
                    '-4',
                    '+norecurse',
                    '+noall',
                    '+comments',
                    '+answer',
                    '+tries=1',
                    '+time=' . $timeout,
                    $fqdn,
                    $type,
                    '@' . $nameserver,
                ]);
            } catch (Throwable $e) {
                $lookupFailures[$nameserver][$type] = $e->getMessage();

                return null;
            }

            if (! $result->successful()) {
                $lookupFailures[$nameserver][$type] = trim($result->errorOutput() ?: $result->output())
                    ?: 'dig exited with status ' . $result->exitCode();

                return null;
            }

            $output = $result->output();
            if (! preg_match('/status: ([A-Z]+)/', $output, $status)
                || ! in_array($status[1], ['NOERROR', 'NXDOMAIN'], true)
                || ! preg_match('/^;; flags: [^;]*\baa\b/m', $output)) {
                $lookupFailures[$nameserver][$fqdn.' '.$type] = 'No authoritative answer ('.($status[1] ?? 'missing status').').';

                return null;
            }

            $statuses[] = $status[1];
            foreach (explode("\n", $output) as $line) {
                if (! preg_match('/^(\S+)\s+\d+\s+IN\s+(A|AAAA|CNAME)\s+(\S+)\s*$/i', trim($line), $record)
                    || strtolower(rtrim($record[1], '.')) !== $fqdn) {
                    continue;
                }

                if (strtoupper($record[2]) === 'CNAME') {
                    $aliases[] = strtolower(rtrim($record[3], '.'));
                } elseif (strtoupper($record[2]) === $type && ($ip = $this->normalizeIp($record[3])) !== null) {
                    $answers[] = $ip;
                }
            }
        }

        $aliases = array_values(array_unique($aliases));
        if (count(array_unique($statuses)) > 1
            || count($aliases) > 1
            || (! empty($aliases) && ! empty($answers))) {
            $lookupFailures[$nameserver][$fqdn] = 'Conflicting address or CNAME answers.';

            return [];
        }

        if (! empty($aliases)) {
            return $this->resolveAlias($aliases[0], $visited, $lookupFailures, $resolvedAliases);
        }

        return array_values(array_unique($answers));
    }

    /** Resolve each CNAME hop using that name's own authorities, once per decision. */
    private function resolveAlias(string $fqdn, array $visited, array &$lookupFailures, array &$resolvedAliases): array
    {
        if (in_array($fqdn, $visited, true) || count($visited) >= 8
            || ! preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $fqdn)) {
            $lookupFailures[$fqdn]['CNAME'] = 'Invalid CNAME target, loop, or maximum chain length exceeded.';

            return [];
        }

        if (array_key_exists($fqdn, $resolvedAliases)) {
            return $resolvedAliases[$fqdn];
        }

        // The original name's configured zone cannot identify authorities for
        // an external target such as an Azure Traffic Manager profile.
        $zone = $this->authoritativeZone($fqdn, false);
        $nameservers = $zone ? $this->nameserversForZone($zone) : [];
        if (empty($nameservers)) {
            $lookupFailures[$fqdn]['CNAME'] = 'Unable to discover CNAME target authorities.';

            return $resolvedAliases[$fqdn] = [];
        }

        $first = null;
        foreach ($nameservers as $nameserver) {
            $answers = $this->authoritativeAnswers($fqdn, $nameserver, $lookupFailures, $visited, $resolvedAliases);
            if ($answers === null) {
                continue;
            }

            sort($answers);
            if ($first !== null && $first !== $answers) {
                $lookupFailures[$fqdn]['CNAME'] = 'CNAME target authorities disagreed.';

                return $resolvedAliases[$fqdn] = [];
            }
            $first = $answers;
        }

        if (empty($first)) {
            $lookupFailures[$fqdn]['CNAME'] ??= 'CNAME target has no authoritative address records.';
        }

        return $resolvedAliases[$fqdn] = $first ?? [];
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

        return $this->autoDetectedNodeIps();
    }

    /** Public addresses detected from this node, independent of replicated settings. */
    public function autoDetectedNodeIps(): array
    {
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

    protected function setting(string $subcategory): ?string
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
