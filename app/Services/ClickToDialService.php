<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Extensions;
use App\Models\MobileAppUsers;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class ClickToDialService
{
    private const ACTION_FREESWITCH = 'freeswitch';
    private const ACTION_API = 'api';

    private array $userAgents = [
        'poly' => [
            'label' => 'Poly Edge',
            'action' => self::ACTION_FREESWITCH,
            // Poly Edge E (fw 8.3.1) ignores out-of-dialog CSTA (session-mode only)
            // but dials via its REST API delivered over SIP NOTIFY. Requires
            // apps.restapi.enabled=1 and apps.restapi.sipNotify.enabled=1 in
            // provisioning (bindRequired=0); the phone reports errors back via
            // SIP INFO with application/poly-report+json.
            'event_string' => 'ACTION-URI',
            'content_type' => 'application/JSON',
            'body_template' => '{"command-URI": "/api/v1/callctrl/dial",'
                . ' "data": {"Dest": "{destination}", "Line": "1", "Type": "TEL"}}',
            'patterns' => ['polyedge'],
        ],
        'polycom' => [
            'label' => 'Polycom (legacy UCS)',
            'action' => self::ACTION_FREESWITCH,
            // UCS older than 6.4.2 200-OKs ACTION-URI NOTIFYs but silently
            // discards them (REST-over-NOTIFY arrived in UCS 6.4.2), so there
            // is no negative signal to probe against — dial via auto-answer
            // originate instead. detectVendor() promotes UCS >= 6.4.2 agents
            // (e.g. VVX x50/x01/x11 on 6.4.x, CCX, Trio) to the 'poly' entry.
            // Listed after 'poly' so PolyEdge agents match the Edge entry first.
            'transport' => 'originate',
            'patterns' => ['polycom', 'vvx', 'soundpoint', 'soundstation', 'trio', 'ccx', 'poly'],
        ],
        'yealink' => [
            'label' => 'Yealink',
            'action' => self::ACTION_FREESWITCH,
            // Yealink ignores out-of-dialog CSTA; it dials via Action URI over SIP NOTIFY.
            'event_string' => 'ACTION-URI',
            'content_type' => 'message/sipfrag',
            'body_template' => 'number={destination}&outgoing_uri=sip:{extension}@{domain}',
            // Stock Yealink firmware always reports "Yealink <MODEL> <version>";
            // "sip-t" catches OEM builds that drop the brand. Unmatched models
            // degrade to the generic auto-answer fallback rather than a list of
            // hardcoded model tokens.
            'patterns' => ['yealink', 'sip-t'],
        ],
        'grandstream' => [
            'label' => 'Grandstream',
            'action' => self::ACTION_FREESWITCH,
            // Grandstream CSTA is session-mode only (INVITE+INFO) and its other
            // remote-control APIs are HTTP-only, so dial via auto-answer originate.
            // Misdetection is harmless: the generic fallback uses the same transport.
            'transport' => 'originate',
            'patterns' => ['grandstream'],
        ],
        'snom' => [
            'label' => 'Snom',
            'action' => self::ACTION_FREESWITCH,
            // Snom rejects out-of-dialog CSTA with 481; it dials via a silent
            // minibrowser document pushed over SIP NOTIFY (firmware 10.1.82.0+).
            'event_string' => 'xml',
            'content_type' => 'application/snomxml',
            'body_template' => '<?xml version="1.0" encoding="UTF-8"?>'
                . '<IPPhoneSilent document_id="click_to_dial">'
                . '<fetch mil="10">phone://mb_exit#numberdial={destination}</fetch>'
                . '</IPPhoneSilent>',
            'patterns' => ['snom'],
        ],
        'ringotel' => [
            'label' => 'Ringotel',
            'action' => self::ACTION_API,
            'patterns' => ['ringotel'],
        ],
        'generic' => [
            'label' => 'Generic (auto-answer)',
            'action' => self::ACTION_FREESWITCH,
            // Fallback for vendors without a native push-to-dial mechanism:
            // auto-answer originate works on any phone honoring Call-Info
            // answer-after=0 (e.g. Fanvil, Htek).
            'transport' => 'originate',
            'patterns' => [],
        ],
    ];

    // Softphone/push registrations that must never be force-answered.
    private array $uncontrollableAgents = ['bria', 'push', 'csc_'];

    // UCS release that introduced REST API delivery over SIP NOTIFY (ACTION-URI).
    private const POLYCOM_REST_NOTIFY_MIN_VERSION = '6.4.2';

    public function __construct(private PhoneRegistrationTargetService $registrationTargets)
    {
    }

    public function makeCall(
        FreeswitchEslService $eslService,
        string $extensionNumber,
        string $domainIdentifier,
        string $destination,
        array $options = []
    ): array {
        $target = $this->registrationTargets->resolveCandidates(
            $eslService,
            $extensionNumber,
            $domainIdentifier,
            fn (string $agent) => $this->registrationIdentity($agent)
        );
        $domain = $target['domain'];
        $extension = $target['extension'];
        $groups = $target['groups'];

        if ($groups->isEmpty()) {
            throw new RuntimeException("No controllable registrations found for {$extensionNumber}@{$domain->domain_name}.");
        }

        $selection = $this->registrationTargets->selectGroups(
            $groups,
            $options,
            array_keys($this->userAgents),
            'controllable registrations'
        );
        $selectedGroups = $selection['selected'];
        $skippedGroups = $selection['skipped'];
        $apiGroups = $selectedGroups->filter(fn (array $group) => $this->actionForVendor((string) $group['vendor']) === self::ACTION_API);
        $freeswitchGroups = $selectedGroups->filter(fn (array $group) => $this->actionForVendor((string) $group['vendor']) === self::ACTION_FREESWITCH);

        if ($apiGroups->isNotEmpty()) {
            $selected = $this->registrationTargets->singleGroupOrFail(
                $apiGroups,
                $groups,
                'API selection',
                'controllable registrations'
            );
            $result = $this->sendApiMakeCall((string) $selected['vendor'], $extension, $domain, $destination, $options);
            $eslService->disconnect();

            return [
                'domain' => $domain,
                'extension' => $extension,
                'group' => $selected,
                'groups' => [$selected],
                'skipped_groups' => $skippedGroups->values()->all(),
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
            $callId = (string) ($registration['call_id'] ?? '');
            $eventString = trim((string) ($options['event'] ?? $definition['event_string'] ?? '')) ?: 'uaCSTA';

            $notifyOverride = ! empty($options['event']) || ! empty($options['content_type']) || ! empty($options['body']);

            if (! $notifyOverride && ($definition['transport'] ?? 'notify') === 'originate') {
                $results[] = $this->sendAutoAnswerOriginate(
                    $eslService,
                    $group,
                    $registration,
                    $extension,
                    $domain,
                    $destination,
                    (bool) ($options['dry_run'] ?? false)
                );
                continue;
            }

            if ($profile === '' || $callId === '') {
                $results[] = [
                    'sent' => false,
                    'reason' => 'Registration profile or call-id could not be resolved.',
                    'vendor' => $vendor,
                    'agent' => $group['agent'] ?? '',
                    'lan_ip' => $deviceIp,
                    'sip_profile_name' => $profile,
                    'registration' => $registration,
                ];
                continue;
            }

            $contentType = trim((string) ($options['content_type'] ?? $definition['content_type'] ?? ''))
                ?: 'application/csta+xml';
            $headers = [
                'profile' => $profile,
                'event-string' => $eventString,
                'user' => $extension->extension,
                'host' => $domain->domain_name,
                'call-id' => $callId,
                'content-type' => $contentType,
            ];
            $body = trim((string) ($options['body'] ?? ''))
                ?: $this->buildNotifyBody($definition, (string) $extension->extension, $domain->domain_name, $destination);

            $result = ($options['dry_run'] ?? false)
                ? 'dry-run'
                : $eslService->sendEvent('NOTIFY', $headers, $body, false);
            $sent = ($options['dry_run'] ?? false) || $result !== null;

            $results[] = [
                'sent' => $sent,
                'reason' => $sent ? null : 'FreeSWITCH ESL did not accept the NOTIFY event.',
                'transport' => 'esl-notify',
                'vendor' => $vendor,
                'agent' => $group['agent'] ?? '',
                'lan_ip' => $deviceIp,
                'sip_profile_name' => $profile,
                'target_uri' => $extension->extension . '@' . $domain->domain_name,
                'destination' => $destination,
                'event_string' => $eventString,
                'call_id' => $callId,
                'command' => sprintf(
                    'sendevent NOTIFY profile=%s event-string=%s user=%s host=%s call-id=%s content-type=%s',
                    $profile,
                    $eventString,
                    $extension->extension,
                    $domain->domain_name,
                    $callId,
                    $contentType
                ),
                'body' => $body,
                'result' => $result,
            ];
        }

        $eslService->disconnect();

        return [
            'domain' => $domain,
            'extension' => $extension,
            'group' => $freeswitchGroups->first(),
            'groups' => $freeswitchGroups->values()->all(),
            'skipped_groups' => $skippedGroups->values()->all(),
            'results' => $results,
        ];
    }

    public function candidateGroups(
        FreeswitchEslService $eslService,
        string $extensionNumber,
        string $domainIdentifier
    ): Collection {
        return $this->registrationTargets->resolveCandidates(
            $eslService,
            $extensionNumber,
            $domainIdentifier,
            fn (string $agent) => $this->registrationIdentity($agent)
        )['groups'];
    }

    private function registrationIdentity(string $agent): ?array
    {
        $vendor = $this->detectVendor($agent);

        return $vendor
            ? ['vendor' => $vendor, 'label' => $this->userAgents[$vendor]['label']]
            : null;
    }

    private function detectVendor(string $agent): ?string
    {
        $haystack = Str::lower(trim($agent));

        if ($haystack === '') {
            return null;
        }

        foreach ($this->userAgents as $vendor => $definition) {
            foreach ($definition['patterns'] as $pattern) {
                if (str_contains($haystack, $pattern)) {
                    if ($vendor === 'polycom' && $this->polycomSupportsRestNotify($agent)) {
                        return 'poly';
                    }

                    return $vendor;
                }
            }
        }

        foreach ($this->uncontrollableAgents as $pattern) {
            if (str_contains($haystack, $pattern)) {
                return null;
            }
        }

        return 'generic';
    }

    private function polycomSupportsRestNotify(string $agent): bool
    {
        $version = null;

        if (preg_match('/-UA\/(\d+(?:\.\d+){1,3})/i', $agent, $matches)) {
            $version = $matches[1];
        } elseif (preg_match('/\b(\d+\.\d+\.\d+(?:\.\d+)?)\b/', $agent, $matches)) {
            $version = $matches[1];
        }

        return $version !== null
            && version_compare($version, self::POLYCOM_REST_NOTIFY_MIN_VERSION, '>=');
    }

    private function sendAutoAnswerOriginate(
        FreeswitchEslService $eslService,
        array $group,
        array $registration,
        Extensions $extension,
        Domain $domain,
        string $destination,
        bool $dryRun
    ): array {
        $vendor = (string) $group['vendor'];
        $profile = (string) ($group['sip_profile_name'] ?? $registration['sip_profile_name'] ?? '');
        $contact = (string) ($registration['contact'] ?? '');

        $base = [
            'vendor' => $vendor,
            'agent' => $group['agent'] ?? '',
            'lan_ip' => (string) ($group['lan_ip'] ?? ''),
            'sip_profile_name' => $profile,
            'target_uri' => $extension->extension . '@' . $domain->domain_name,
            'destination' => $destination,
        ];

        if ($profile === '' || $contact === '') {
            return $base + [
                'sent' => false,
                'reason' => 'Registration profile or contact could not be resolved.',
                'registration' => $registration,
            ];
        }

        if (! preg_match('/^[\w*#+.@-]+$/', $destination)) {
            return $base + [
                'sent' => false,
                'reason' => 'Destination contains characters that cannot be passed to originate.',
            ];
        }

        $context = (string) ($extension->user_context ?: $domain->domain_name);
        $callerName = trim(preg_replace('/[\'",{}\[\]]/', '', (string) $extension->effective_caller_id_name))
            ?: (string) $extension->extension;

        // Caller ID is the controlled extension so the recipient and CDRs see the real
        // caller. sip_auto_answer and the sip_h_Call-Info header sofia caches for it
        // must be unset before the transfer or the bridged leg inherits them and
        // auto-answers the destination phone as well. The display name is set via the
        // inline dialplan because quoted spaces inside {} do not survive ESL parsing.
        $command = sprintf(
            'originate {domain_uuid=%5$s,domain_name=%6$s,sip_invite_domain=%6$s,'
                . 'origination_caller_id_number=%7$s,origination_caller_id_name=%7$s,'
                . 'sip_auto_answer=true,ignore_early_media=true}sofia/%2$s/%3$s'
                . ' \'unset:sip_auto_answer,unset:ignore_early_media,unset:sip_h_Call-Info,'
                . 'set:effective_caller_id_number=%7$s,set:effective_caller_id_name=%8$s,'
                . 'set:caller_id_number=%7$s,set:caller_id_name=%8$s,'
                . 'transfer:%1$s XML %4$s\' inline',
            $destination,
            $profile,
            $contact,
            $context,
            $domain->domain_uuid,
            $domain->domain_name,
            $extension->extension,
            $callerName
        );

        $result = $dryRun ? 'dry-run' : $eslService->executeCommand($command, false);
        $sent = $dryRun || (is_string($result) && str_starts_with(trim($result), '+OK'));

        return $base + [
            'sent' => $sent,
            'reason' => $sent ? null : 'FreeSWITCH did not accept the originate command.',
            'transport' => 'esl-originate',
            'command' => $command,
            'result' => $result,
        ];
    }

    private function buildNotifyBody(?array $definition, string $extension, string $domain, string $destination): string
    {
        $template = (string) ($definition['body_template'] ?? '');

        if ($template !== '') {
            return strtr($template, [
                '{extension}' => $extension,
                '{domain}' => $domain,
                '{destination}' => $destination,
            ]);
        }

        return $this->buildMakeCallXml($extension, $destination);
    }

    private function buildMakeCallXml(string $callingDevice, string $destination): string
    {
        $namespace = 'http://www.ecma-international.org/standards/ecma-323/csta/ed3';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . "<MakeCall xmlns=\"{$namespace}\">"
            . '<callingDevice>' . htmlspecialchars($callingDevice, ENT_XML1) . '</callingDevice>'
            . '<calledDirectoryNumber>' . htmlspecialchars($destination, ENT_XML1) . '</calledDirectoryNumber>'
            . '<autoOriginate>doNotPrompt</autoOriginate>'
            . '</MakeCall>';
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
}
