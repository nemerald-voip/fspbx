<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Extensions;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class PhoneRegistrationTargetService
{
    /**
     * @param callable(string): ?array{vendor: string, label: string} $identifyVendor
     * @return array{domain: Domain, extension: Extensions, groups: Collection}
     */
    public function resolveCandidates(
        FreeswitchEslService $eslService,
        string $extensionNumber,
        string $domainIdentifier,
        callable $identifyVendor
    ): array {
        ['domain' => $domain, 'extension' => $extension] = $this->resolveTarget($extensionNumber, $domainIdentifier);
        $registrations = $eslService->getAllSipRegistrations()
            ->filter(function (array $registration) use ($extension, $domain) {
                return (string) ($registration['sip_auth_user'] ?? '') === (string) $extension->extension
                    && (string) ($registration['sip_auth_realm'] ?? '') === (string) $domain->domain_name;
            })
            ->values();

        return [
            'domain' => $domain,
            'extension' => $extension,
            'groups' => $this->groupRegistrations($registrations, $identifyVendor),
        ];
    }

    /**
     * @return array{selected: Collection, skipped: Collection}
     */
    public function selectGroups(
        Collection $groups,
        array $options,
        array $validVendors,
        string $registrationLabel
    ): array {
        if (! empty($options['call_id'])) {
            $matches = $groups->filter(function (array $group) use ($options) {
                return collect($group['registrations'])
                    ->contains(fn (array $registration) => (string) ($registration['call_id'] ?? '') === (string) $options['call_id']);
            });

            $group = $this->singleGroupOrFail($matches, $groups, 'call-id', $registrationLabel);

            // Drivers act on the group's first registration; make that the one
            // that matched the requested call-id, not the group's freshest entry.
            [$matched, $rest] = collect($group['registrations'])->partition(
                fn (array $registration) => (string) ($registration['call_id'] ?? '') === (string) $options['call_id']
            );
            $group['registrations'] = $matched->merge($rest)->values()->all();

            return [
                'selected' => collect([$group]),
                'skipped' => collect(),
            ];
        }

        $matches = $groups;

        if (! empty($options['vendor'])) {
            $vendor = Str::lower((string) $options['vendor']);

            if (! in_array($vendor, $validVendors, true)) {
                throw new RuntimeException(
                    "Unknown vendor [{$vendor}]. Valid vendors: " . implode(', ', $validVendors) . '.'
                );
            }

            $matches = $matches->where('vendor', $vendor)->values();
        }

        if (! empty($options['agent'])) {
            $matches = $matches
                ->filter(fn (array $group) => $this->agentMatches(
                    (string) ($group['agent'] ?? ''),
                    (string) $options['agent']
                ))
                ->values();
        }

        if (! empty($options['lan_ip'])) {
            $lanIp = $this->normalizeLanIp((string) $options['lan_ip']);
            $matches = $matches
                ->filter(fn (array $group) => $this->normalizeLanIp((string) $group['lan_ip']) === $lanIp)
                ->values();
        }

        if ($matches->isEmpty()) {
            throw new RuntimeException(
                "No {$registrationLabel} matched the selection. Available choices:\n" . $this->formatGroups($groups)
            );
        }

        if (! empty($options['lan_ip'])) {
            return [
                'selected' => collect([$this->singleGroupOrFail($matches, $groups, 'selection', $registrationLabel)]),
                'skipped' => collect(),
            ];
        }

        $matches = $matches
            ->sortByDesc(fn (array $group) => (int) ($group['registrations'][0]['expsecs'] ?? 0))
            ->values();

        return [
            'selected' => $matches->take(1),
            'skipped' => $matches->slice(1)->values(),
        ];
    }

    public function singleGroupOrFail(
        Collection $matches,
        Collection $allGroups,
        string $context,
        string $registrationLabel
    ): array {
        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->isEmpty()) {
            throw new RuntimeException(
                "No {$registrationLabel} matched the {$context}. Available choices:\n" . $this->formatGroups($allGroups)
            );
        }

        throw new RuntimeException(
            "Multiple registration groups matched the {$context}. Choose a vendor, LAN IP, or call-id:\n"
            . $this->formatGroups($matches)
        );
    }

    /**
     * @param callable(string): ?array{vendor: string, label: string} $identifyVendor
     */
    public function groupRegistrations(Collection $registrations, callable $identifyVendor): Collection
    {
        return $registrations
            ->map(function (array $registration) use ($identifyVendor) {
                $identity = $identifyVendor((string) ($registration['agent'] ?? ''));

                if (! $identity || empty($identity['vendor']) || empty($identity['label'])) {
                    return null;
                }

                return array_merge($registration, [
                    'phone_vendor' => (string) $identity['vendor'],
                    'phone_vendor_label' => (string) $identity['label'],
                ]);
            })
            ->filter()
            ->groupBy(function (array $registration) {
                return implode('|', [
                    $registration['phone_vendor'],
                    $registration['sip_profile_name'] ?? '',
                    $this->normalizeLanIp($this->registrationDeviceIp($registration)),
                ]);
            })
            ->values()
            ->map(function (Collection $registrations, int $index) {
                $registrations = $registrations
                    ->sortByDesc(fn (array $registration) => (int) ($registration['expsecs'] ?? 0))
                    ->values();
                $first = $registrations->first();

                return [
                    'index' => $index + 1,
                    'vendor' => $first['phone_vendor'],
                    'label' => $first['phone_vendor_label'],
                    'agent' => $first['agent'] ?? '',
                    'lan_ip' => $this->registrationDeviceIp($first),
                    'registration_lan_ip' => $first['lan_ip'] ?? '',
                    'sip_profile_name' => $first['sip_profile_name'] ?? '',
                    'count' => $registrations->count(),
                    'registrations' => $registrations->values()->all(),
                ];
            });
    }

    /**
     * @return array{domain: Domain, extension: Extensions}
     */
    public function resolveTarget(string $extensionNumber, string $domainIdentifier): array
    {
        $domain = $this->resolveDomain($domainIdentifier);

        return [
            'domain' => $domain,
            'extension' => $this->resolveExtension($extensionNumber, $domain),
        ];
    }

    private function resolveDomain(string $identifier): Domain
    {
        $domain = Domain::query()
            ->when(Str::isUuid($identifier), fn ($query) => $query->where('domain_uuid', $identifier))
            ->when(! Str::isUuid($identifier), fn ($query) => $query->where('domain_name', $identifier))
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            throw new RuntimeException("Domain [{$identifier}] was not found.");
        }

        return $domain;
    }

    private function resolveExtension(string $extensionNumber, Domain $domain): Extensions
    {
        $extension = Extensions::query()
            ->where('domain_uuid', $domain->domain_uuid)
            ->where('extension', $extensionNumber)
            ->first([
                'extension_uuid',
                'domain_uuid',
                'extension',
                'user_context',
                'effective_caller_id_name',
            ]);

        if (! $extension) {
            throw new RuntimeException("Extension [{$extensionNumber}] was not found in {$domain->domain_name}.");
        }

        return $extension;
    }

    private function agentMatches(string $agent, string $pattern): bool
    {
        $regex = '/' . str_replace('/', '\\/', $pattern) . '/i';
        $result = @preg_match($regex, $agent);

        if ($result !== false) {
            return $result === 1;
        }

        return str_contains(Str::lower($agent), Str::lower($pattern));
    }

    private function formatGroups(Collection $groups): string
    {
        return $groups
            ->map(function (array $group) {
                return sprintf(
                    '[%d] vendor=%s profile=%s lan_ip=%s count=%d agent=%s',
                    $group['index'],
                    $group['vendor'],
                    $group['sip_profile_name'] ?: '(unknown)',
                    $group['lan_ip'] ?: '(unknown)',
                    $group['count'],
                    $group['agent'] ?: '(unknown)'
                );
            })
            ->implode("\n");
    }

    private function normalizeLanIp(string $lanIp): string
    {
        return trim(Str::lower($lanIp));
    }

    private function registrationDeviceIp(array $registration): string
    {
        return $this->deviceIpFromAgent((string) ($registration['agent'] ?? ''))
            ?: $this->deviceIpFromContact((string) ($registration['contact'] ?? ''))
            ?: (string) ($registration['lan_ip'] ?? '');
    }

    private function deviceIpFromAgent(string $agent): ?string
    {
        if (preg_match('/\bX-LAN:\s*([^\/;\s]+)/i', $agent, $matches)) {
            $ip = trim($matches[1]);

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return null;
    }

    private function deviceIpFromContact(string $contact): ?string
    {
        $contactUri = $this->extractSipUri($contact);

        if (! $contactUri) {
            return null;
        }

        if (preg_match('/^sips?:[^@]+@\[?([0-9a-f:.]+)\]?(?::\d+)?(?:[;?]|$)/i', $contactUri, $matches)
            && filter_var($matches[1], FILTER_VALIDATE_IP)) {
            return $matches[1];
        }

        return null;
    }

    private function extractSipUri(string $contact): ?string
    {
        if (preg_match('/<(sip:[^>]+)>/i', $contact, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(sip:\S+)/i', $contact, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
