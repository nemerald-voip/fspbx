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
    private const ACTION_SIP_INFO = 'sip_info';
    private const ACTION_API = 'api';

    private array $userAgents = [
        'poly' => [
            'label' => 'Poly / Polycom',
            'action' => self::ACTION_SIP_INFO,
            'patterns' => ['poly', 'polycom', 'vvx', 'edge'],
            'calling_device' => 'aor',
        ],
        'yealink' => [
            'label' => 'Yealink',
            'action' => self::ACTION_SIP_INFO,
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
            'calling_device' => 'extension',
        ],
        'grandstream' => [
            'label' => 'Grandstream',
            'action' => self::ACTION_SIP_INFO,
            'patterns' => ['grandstream', 'gxp', 'grp', 'wp8', 'wp82', 'ht8'],
            'calling_device' => 'extension',
        ],
        'snom' => [
            'label' => 'Snom',
            'action' => self::ACTION_SIP_INFO,
            'patterns' => ['snom'],
            'calling_device' => 'extension',
        ],
        'ringotel' => [
            'label' => 'Ringotel',
            'action' => self::ACTION_API,
            'patterns' => ['ringotel'],
            'calling_device' => 'extension',
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

        $selected = $this->selectGroup($groups, $options);
        $vendor = (string) $selected['vendor'];
        $definition = $this->userAgents[$vendor] ?? null;

        if (! $definition) {
            throw new RuntimeException("Unsupported user agent vendor [{$vendor}].");
        }

        if ($definition['action'] === self::ACTION_API) {
            $result = $this->sendApiMakeCall($vendor, $extension, $domain, $destination, $options);
            $eslService->disconnect();

            return [
                'domain' => $domain,
                'extension' => $extension,
                'group' => $selected,
                'results' => [$result],
            ];
        }

        $results = [];
        foreach ($selected['registrations'] as $registration) {
            $targetUri = $this->targetUriFromContact((string) ($registration['contact'] ?? ''));

            if ($targetUri === null) {
                $results[] = [
                    'sent' => false,
                    'reason' => 'Registration contact could not be parsed.',
                    'registration' => $registration,
                ];
                continue;
            }

            $body = $this->makeCallBody(
                $this->callingDevice($definition, $extensionNumber, $domain->domain_name),
                $destination
            );

            $fromUri = 'sip:' . $extensionNumber . '@' . $domain->domain_name;
            $command = null;

            $command = $this->buildLuaCommand(
                (string) $registration['sip_profile_name'],
                $targetUri,
                $fromUri,
                $body,
                (bool) ($options['async'] ?? false),
                (bool) ($options['relative_lua_path'] ?? false)
            );

            $result = match (true) {
                (bool) ($options['dry_run'] ?? false) => 'dry-run',
                (bool) ($options['direct_esl'] ?? false) => $this->sendSipInfo($eslService, (string) $registration['sip_profile_name'], $targetUri, $fromUri, $body),
                default => $eslService->executeCommand($command, false),
            };
            $sent = $this->sendSucceeded($result, (bool) ($options['dry_run'] ?? false), (bool) ($options['direct_esl'] ?? false), (bool) ($options['async'] ?? false));

            $results[] = [
                'sent' => $sent,
                'transport' => ($options['direct_esl'] ?? false) ? 'esl' : 'lua',
                'vendor' => $vendor,
                'agent' => $registration['agent'] ?? '',
                'lan_ip' => $registration['lan_ip'] ?? '',
                'sip_profile_name' => $registration['sip_profile_name'] ?? '',
                'target_uri' => $targetUri,
                'command' => $command,
                'result' => $result,
            ];
        }

        $eslService->disconnect();

        return [
            'domain' => $domain,
            'extension' => $extension,
            'group' => $selected,
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
                    $this->normalizeAgent((string) ($registration['agent'] ?? '')),
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
                    'count' => $registrations->count(),
                    'registrations' => $registrations->values()->all(),
                ];
            });
    }

    private function selectGroup(Collection $groups, array $options): array
    {
        if (! empty($options['call_id'])) {
            $matches = $groups->filter(function (array $group) use ($options) {
                return collect($group['registrations'])
                    ->contains(fn (array $registration) => (string) ($registration['call_id'] ?? '') === (string) $options['call_id']);
            });

            return $this->singleGroupOrFail($matches, $groups, 'call-id');
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

        return $this->singleGroupOrFail($matches, $groups, 'selection');
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
                    '[%d] vendor=%s lan_ip=%s count=%d agent=%s',
                    $group['index'],
                    $group['vendor'],
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

    private function normalizeAgent(string $agent): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower($agent)) ?? '');
    }

    private function normalizeLanIp(string $lanIp): string
    {
        return trim(Str::lower($lanIp));
    }

    private function registrationDeviceIp(array $registration): string
    {
        $agentIp = $this->deviceIpFromAgent((string) ($registration['agent'] ?? ''));

        return $agentIp ?: (string) ($registration['lan_ip'] ?? '');
    }

    private function deviceIpFromAgent(string $agent): ?string
    {
        $lastSegment = trim(Str::afterLast($agent, '/'));

        if ($lastSegment === '' || $lastSegment === $agent) {
            return null;
        }

        if (filter_var($lastSegment, FILTER_VALIDATE_IP)) {
            return $lastSegment;
        }

        return null;
    }

    private function targetUriFromContact(string $contact): ?string
    {
        $contactUri = $this->extractSipUri($contact);

        if (! $contactUri) {
            return null;
        }

        if (preg_match('/[;?&]fs_path=([^;>]+)/', $contactUri, $matches)) {
            $path = urldecode($matches[1]);

            if ($path !== '') {
                return $path;
            }
        }

        return preg_replace('/;(fs_nat|fs_path|received)=[^;>]+/', '', $contactUri);
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

    private function callingDevice(array $definition, string $extensionNumber, string $domainName): string
    {
        return ($definition['calling_device'] ?? 'extension') === 'aor'
            ? 'sip:' . $extensionNumber . '@' . $domainName
            : $extensionNumber;
    }

    private function makeCallBody(string $callingDevice, string $destination): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<MakeCall xmlns="http://www.ecma-international.org/standards/ecma-323/csta/ed3">'
            . '<callingDevice>' . $this->xml($callingDevice) . '</callingDevice>'
            . '<calledDirectoryNumber>' . $this->xml($destination) . '</calledDirectoryNumber>'
            . '<autoOriginate>doNotPrompt</autoOriginate>'
            . '</MakeCall>';
    }

    private function buildLuaCommand(
        string $profile,
        string $toUri,
        string $fromUri,
        string $body,
        bool $async,
        bool $relativePath
    ): string
    {
        $script = $relativePath
            ? 'lua/uacsta_makecall.lua'
            : str_replace('\\', '/', base_path('resources/lua/uacsta_makecall.lua'));

        return ($async ? 'bgapi luarun ' : 'lua ') . $script . ' '
            . base64_encode($profile) . ' '
            . base64_encode($toUri) . ' '
            . base64_encode($fromUri) . ' '
            . base64_encode($body);
    }

    private function sendSipInfo(
        FreeswitchEslService $eslService,
        string $profile,
        string $toUri,
        string $fromUri,
        string $body
    ): ?string {
        return $eslService->sendEvent('SEND_INFO', [
            'profile' => $profile,
            'to-uri' => $toUri,
            'from-uri' => $fromUri,
            'content-type' => 'application/csta+xml',
            'content-disposition' => 'signal;handling=required',
            'content-length' => strlen($body),
        ], $body, false);
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
        $ringotelDomain = $options['ringotel_domain'] ?? null;

        if (! $ringotelDomain) {
            $organization = $ringotel->getOrganization($mobileApp->org_id);
            $ringotelDomain = $organization->domain ?? null;
        }

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

    private function sendSucceeded(mixed $result, bool $dryRun, bool $directEsl, bool $async): bool
    {
        if ($dryRun) {
            return true;
        }

        if (! is_string($result)) {
            return false;
        }

        if ($directEsl) {
            return str_starts_with($result, '+OK');
        }

        return $async
            ? str_starts_with($result, '+OK')
            : str_contains($result, '+OK uaCSTA SEND_INFO fired');
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
