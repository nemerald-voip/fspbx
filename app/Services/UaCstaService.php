<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Extensions;
use App\Models\MobileAppUsers;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class UaCstaService
{
    private const ACTION_FREESWITCH = 'freeswitch';
    private const ACTION_API = 'api';

    private array $userAgents = [
        'poly' => [
            'label' => 'Poly / Polycom',
            'action' => self::ACTION_FREESWITCH,
            'patterns' => ['poly', 'polycom', 'vvx', 'edge'],
        ],
        'yealink' => [
            'label' => 'Yealink',
            'action' => self::ACTION_FREESWITCH,
            'patterns' => [
                'yealink',
                'sip-t',
                't31',
                't33',
                't42',
                't46',
                't48',
                't53',
                't54',
                't57',
                't58',
                'w60',
                'w70',
                'w73',
                'w76',
                'w80',
                'w90',
            ],
        ],
        'grandstream' => [
            'label' => 'Grandstream',
            'action' => self::ACTION_FREESWITCH,
            'patterns' => ['grandstream', 'gxp', 'grp', 'wp8', 'wp82', 'ht8'],
        ],
        'snom' => [
            'label' => 'Snom',
            'action' => self::ACTION_FREESWITCH,
            'patterns' => ['snom'],
        ],
        'ringotel' => [
            'label' => 'Ringotel',
            'action' => self::ACTION_API,
            'patterns' => ['ringotel'],
        ],
    ];

    public function makeCall(
        FreeswitchEslService $eslService,
        string $extensionNumber,
        string $domainIdentifier,
        string $destination,
        array $options = []
    ): array {
        $domain = $this->resolveDomain($domainIdentifier);
        $extension = $this->resolveExtension($extensionNumber, $domain);
        $groups = $this->registrationsForExtension($eslService, $extension, $domain)
            ->pipe(fn (Collection $registrations) => $this->groupRegistrations($registrations));

        if ($groups->isEmpty()) {
            throw new RuntimeException("No controllable registrations found for {$extensionNumber}@{$domain->domain_name}.");
        }

        $selectedGroups = $this->selectGroups($groups, $options);
        $apiGroups = $selectedGroups->filter(fn (array $group) => $this->actionForVendor((string) $group['vendor']) === self::ACTION_API);
        $freeswitchGroups = $selectedGroups->filter(fn (array $group) => $this->actionForVendor((string) $group['vendor']) === self::ACTION_FREESWITCH);

        if ($apiGroups->isNotEmpty() && $freeswitchGroups->isNotEmpty()) {
            throw new RuntimeException("Both API and FreeSWITCH endpoints matched. Choose a vendor, LAN IP, or call-id:\n" . $this->formatGroups($selectedGroups));
        }

        if ($apiGroups->isNotEmpty()) {
            $selected = $this->singleGroupOrFail($apiGroups, $groups, 'API selection');
            $result = $this->sendApiMakeCall((string) $selected['vendor'], $extension, $domain, $destination, $options);
            $eslService->disconnect();

            return [
                'domain' => $domain,
                'extension' => $extension,
                'group' => $selected,
                'groups' => [$selected],
                'results' => [$result],
            ];
        }

        $results = [];
        foreach ($freeswitchGroups as $group) {
            $vendor = (string) $group['vendor'];
            $definition = $this->userAgents[$vendor] ?? null;
            $registration = $group['registrations'][0] ?? [];
            $profile = (string) ($group['sip_profile_name'] ?? $registration['sip_profile_name'] ?? '');
            $deviceIp = (string) ($group['lan_ip'] ?? '');
            $useSdp = (bool) ($options['sdp'] ?? false) || (bool) ($definition['sdp'] ?? false);

            if ($profile === '' || $deviceIp === '') {
                $results[] = [
                    'sent' => false,
                    'reason' => 'Registration profile or endpoint IP could not be resolved.',
                    'vendor' => $vendor,
                    'agent' => $group['agent'] ?? '',
                    'lan_ip' => $deviceIp,
                    'sip_profile_name' => $profile,
                    'registration' => $registration,
                ];
                continue;
            }

            $command = $this->buildCstaCommand(
                $profile,
                $extension->extension . '@' . $domain->domain_name,
                $destination,
                $deviceIp,
                $useSdp
            );
            $result = ($options['dry_run'] ?? false)
                ? 'dry-run'
                : $eslService->executeCommand($command, false);
            $sent = $this->commandSucceeded($result, (bool) ($options['dry_run'] ?? false));

            $results[] = [
                'sent' => $sent,
                'transport' => 'esl',
                'vendor' => $vendor,
                'agent' => $group['agent'] ?? '',
                'lan_ip' => $deviceIp,
                'sip_profile_name' => $profile,
                'target_uri' => $extension->extension . '@' . $domain->domain_name,
                'destination' => $destination,
                'sdp' => $useSdp,
                'command' => $command,
                'result' => $result,
            ];
        }

        $eslService->disconnect();

        return [
            'domain' => $domain,
            'extension' => $extension,
            'group' => $freeswitchGroups->first(),
            'groups' => $freeswitchGroups->values()->all(),
            'results' => $results,
        ];
    }

    public function candidateGroups(
        FreeswitchEslService $eslService,
        string $extensionNumber,
        string $domainIdentifier
    ): Collection {
        $domain = $this->resolveDomain($domainIdentifier);
        $extension = $this->resolveExtension($extensionNumber, $domain);

        return $this->registrationsForExtension($eslService, $extension, $domain)
            ->pipe(fn (Collection $registrations) => $this->groupRegistrations($registrations));
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
            ->first(['extension_uuid', 'domain_uuid', 'extension', 'user_context']);

        if (! $extension) {
            throw new RuntimeException("Extension [{$extensionNumber}] was not found in {$domain->domain_name}.");
        }

        return $extension;
    }

    private function registrationsForExtension(
        FreeswitchEslService $eslService,
        Extensions $extension,
        Domain $domain
    ): Collection {
        return $eslService->getAllSipRegistrations()
            ->filter(function (array $registration) use ($extension, $domain) {
                return (string) ($registration['sip_auth_user'] ?? '') === (string) $extension->extension
                    && (string) ($registration['sip_auth_realm'] ?? '') === (string) $domain->domain_name;
            })
            ->values();
    }

    private function groupRegistrations(Collection $registrations): Collection
    {
        return $registrations
            ->map(function (array $registration) {
                $vendor = $this->detectVendor((string) ($registration['agent'] ?? ''));

                return $vendor ? array_merge($registration, ['uacsta_vendor' => $vendor]) : null;
            })
            ->filter()
            ->groupBy(function (array $registration) {
                return implode('|', [
                    $registration['uacsta_vendor'],
                    $registration['sip_profile_name'] ?? '',
                    $this->normalizeLanIp($this->registrationDeviceIp($registration)),
                ]);
            })
            ->values()
            ->map(function (Collection $registrations, int $index) {
                $first = $registrations->first();
                $deviceIp = $this->registrationDeviceIp($first);

                return [
                    'index' => $index + 1,
                    'vendor' => $first['uacsta_vendor'],
                    'label' => $this->userAgents[$first['uacsta_vendor']]['label'],
                    'agent' => $first['agent'] ?? '',
                    'lan_ip' => $deviceIp,
                    'registration_lan_ip' => $first['lan_ip'] ?? '',
                    'sip_profile_name' => $first['sip_profile_name'] ?? '',
                    'count' => $registrations->count(),
                    'registrations' => $registrations->values()->all(),
                ];
            });
    }

    private function selectGroups(Collection $groups, array $options): Collection
    {
        if (! empty($options['call_id'])) {
            $matches = $groups->filter(function (array $group) use ($options) {
                return collect($group['registrations'])
                    ->contains(fn (array $registration) => (string) ($registration['call_id'] ?? '') === (string) $options['call_id']);
            });

            return collect([$this->singleGroupOrFail($matches, $groups, 'call-id')]);
        }

        $matches = $groups;

        if (! empty($options['vendor'])) {
            $vendor = strtolower((string) $options['vendor']);
            $matches = $matches->where('vendor', $vendor)->values();
        }

        if (! empty($options['lan_ip'])) {
            $lanIp = $this->normalizeLanIp((string) $options['lan_ip']);
            $matches = $matches
                ->filter(fn (array $group) => $this->normalizeLanIp((string) $group['lan_ip']) === $lanIp)
                ->values();
        }

        if ($matches->isEmpty()) {
            throw new RuntimeException("No uaCSTA registrations matched the selection. Available choices:\n" . $this->formatGroups($groups));
        }

        if (! empty($options['lan_ip'])) {
            return collect([$this->singleGroupOrFail($matches, $groups, 'selection')]);
        }

        return $matches->values();
    }

    private function singleGroupOrFail(Collection $matches, Collection $allGroups, string $context): array
    {
        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->isEmpty()) {
            throw new RuntimeException("No uaCSTA registrations matched the {$context}. Available choices:\n" . $this->formatGroups($allGroups));
        }

        throw new RuntimeException("Multiple uaCSTA registration groups matched the {$context}. Choose a vendor, LAN IP, or call-id:\n" . $this->formatGroups($matches));
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

    private function detectVendor(string $agent): ?string
    {
        $haystack = Str::lower($agent);

        foreach ($this->userAgents as $vendor => $definition) {
            foreach ($definition['patterns'] as $pattern) {
                if (str_contains($haystack, $pattern)) {
                    return $vendor;
                }
            }
        }

        return null;
    }

    private function normalizeLanIp(string $lanIp): string
    {
        return trim(Str::lower($lanIp));
    }

    private function registrationDeviceIp(array $registration): string
    {
        $agentIp = $this->deviceIpFromAgent((string) ($registration['agent'] ?? ''));

        return $agentIp
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

    private function buildCstaCommand(
        string $profile,
        string $aor,
        string $destination,
        string $deviceIp,
        bool $sdp
    ): string {
        $parts = [
            'sofia_csta_call',
            $this->freeswitchArg($profile, 'profile'),
            $this->freeswitchArg($aor, 'extension address'),
            $this->freeswitchArg($destination, 'destination'),
            $this->freeswitchArg($deviceIp, 'endpoint IP'),
        ];

        if ($sdp) {
            $parts[] = 'sdp';
        }

        return implode(' ', $parts);
    }

    private function freeswitchArg(string $value, string $label): string
    {
        $value = trim($value);

        if ($value === '' || preg_match('/\s/', $value)) {
            throw new RuntimeException("Invalid {$label} for sofia_csta_call.");
        }

        return $value;
    }

    private function actionForVendor(string $vendor): ?string
    {
        return $this->userAgents[$vendor]['action'] ?? null;
    }

    private function sendApiMakeCall(
        string $vendor,
        Extensions $extension,
        Domain $domain,
        string $destination,
        array $options
    ): array {
        return match ($vendor) {
            'ringotel' => $this->sendRingotelMakeCall($extension, $domain, $destination, $options),
            default => throw new RuntimeException("API make-call is not implemented for {$vendor}."),
        };
    }

    private function sendRingotelMakeCall(
        Extensions $extension,
        Domain $domain,
        string $destination,
        array $options
    ): array {
        $mobileApp = MobileAppUsers::query()
            ->where('extension_uuid', $extension->extension_uuid)
            ->where('domain_uuid', $domain->domain_uuid)
            ->first(['mobile_app_user_uuid', 'org_id', 'conn_id', 'user_id']);

        if (! $mobileApp) {
            throw new RuntimeException("No Ringotel mobile app user is linked to extension {$extension->extension}.");
        }

        $ringotel = app(RingotelApiService::class);
        $organization = $ringotel->getOrganization($mobileApp->org_id);
        $ringotelDomain = $organization->domain ?? null;

        if (! $ringotelDomain) {
            throw new RuntimeException("Ringotel organization domain could not be resolved for org {$mobileApp->org_id}.");
        }

        $payload = [
            'from' => (string) $extension->extension,
            'tonumber' => $destination,
            'toname' => (string) $extension->extension,
            'domain' => (string) $ringotelDomain,
        ];

        $result = ($options['dry_run'] ?? false)
            ? ['dry_run' => true, 'payload' => $payload]
            : $ringotel->initCall($payload);

        return [
            'sent' => true,
            'transport' => 'ringotel-api',
            'vendor' => 'ringotel',
            'agent' => 'Ringotel',
            'lan_ip' => '',
            'sip_profile_name' => '',
            'target_uri' => $mobileApp->user_id,
            'command' => 'Ringotel initCall',
            'result' => $result,
            'ringotel' => [
                'org_id' => $mobileApp->org_id,
                'conn_id' => $mobileApp->conn_id,
                'user_id' => $mobileApp->user_id,
                'domain' => $ringotelDomain,
                'payload' => $payload,
                'requested_destination' => $destination,
            ],
        ];
    }

    private function commandSucceeded(mixed $result, bool $dryRun): bool
    {
        if ($dryRun) {
            return true;
        }

        if (! is_string($result)) {
            return false;
        }

        return trim($result) !== '' && ! str_starts_with(trim($result), '-ERR');
    }
}
