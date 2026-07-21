<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Extensions;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PolyPhoneControlDriver implements PhoneControlDriver
{
    // UCS release that introduced REST API delivery over SIP NOTIFY. Below
    // this, a legacy Polycom phone (VVX, Trio, CCX, SoundPoint/SoundStation)
    // 200-OKs the ACTION-URI NOTIFY but silently discards it — no negative
    // signal to probe for, so those stay on the Generic/PBX-side driver.
    // Same threshold and detection ClickToDialService already uses to decide
    // whether a legacy Polycom agent gets REST-over-NOTIFY dialing.
    private const POLYCOM_REST_NOTIFY_MIN_VERSION = '6.4.2';

    private const LEGACY_POLYCOM_PATTERNS = ['polycom', 'vvx', 'soundpoint', 'soundstation', 'trio', 'ccx'];

    private const ACTIONS = [
        self::ACTION_HOLD,
        self::ACTION_RESUME,
        self::ACTION_BLIND_TRANSFER,
        self::ACTION_ATTENDED_TRANSFER,
        self::ACTION_COMPLETE_TRANSFER,
        self::ACTION_CANCEL_TRANSFER,
        self::ACTION_CONFERENCE,
        self::ACTION_MUTE_ON,
        self::ACTION_MUTE_OFF,
        self::ACTION_END_CALL,
        self::ACTION_ANSWER_CALL,
    ];

    public function __construct(
        private PbxCallControl $pbx,
        private int $stepDelayMicroseconds = 2_000_000
    ) {
    }

    public function vendor(): string
    {
        return 'poly';
    }

    public function label(): string
    {
        return 'Poly';
    }

    public function matchesAgent(string $agent): bool
    {
        $haystack = Str::lower(trim($agent));

        if ($haystack === '') {
            return false;
        }

        if (str_contains($haystack, 'polyedge')) {
            return true;
        }

        foreach (self::LEGACY_POLYCOM_PATTERNS as $pattern) {
            if (str_contains($haystack, $pattern)) {
                return $this->polycomSupportsRestNotify($agent);
            }
        }

        return false;
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

    public function supportedActions(): array
    {
        return self::ACTIONS;
    }

    public function send(
        FreeswitchEslService $eslService,
        Extensions $extension,
        Domain $domain,
        array $group,
        string $action,
        ?string $destination = null,
        ?string $activeCallId = null,
        bool $dryRun = false
    ): array {
        $action = Str::lower(trim($action));
        $registration = $group['registrations'][0] ?? [];
        $profile = (string) ($group['sip_profile_name'] ?? $registration['sip_profile_name'] ?? '');
        $registrationCallId = (string) ($registration['call_id'] ?? '');
        $base = [
            'vendor' => $this->vendor(),
            'agent' => $group['agent'] ?? '',
            'lan_ip' => (string) ($group['lan_ip'] ?? ''),
            'sip_profile_name' => $profile,
            'target_uri' => $extension->extension . '@' . $domain->domain_name,
        ];

        if ($profile === '' || $registrationCallId === '') {
            return $base + [
                'sent' => false,
                'reason' => 'Registration profile or call-id could not be resolved.',
                'registration' => $registration,
            ];
        }

        // Completing an attended transfer has no REST endpoint (transferCall is
        // blind-only, even with mgmt/transferType set to Consultative), so the
        // PBX joins the two far legs itself with uuid_bridge.
        if ($action === self::ACTION_COMPLETE_TRANSFER) {
            return $base + $this->completeTransferViaBridge($eslService, $extension, $domain, $dryRun);
        }

        // No REST conference endpoint either, so the PBX mixes the 3-way in
        // mod_conference: the consultation pair moves into a dynamic room and
        // the far leg of the held call is pulled in after it.
        if ($action === self::ACTION_CONFERENCE) {
            return $base + $this->conferenceViaPbx($eslService, $extension, $domain, $dryRun);
        }

        $headers = [
            'profile' => $profile,
            'event-string' => 'ACTION-URI',
            'user' => $extension->extension,
            'host' => $domain->domain_name,
            'call-id' => $registrationCallId,
            'content-type' => 'application/JSON',
        ];

        if ($action === self::ACTION_CANCEL_TRANSFER) {
            // End the active consultation call specifically; the original call
            // stays on hold (parity with the other vendors' cancel-transfer).
            $consult = $this->pbx->channelsForExtension($eslService, $extension, $domain)
                ->first(fn (array $channel) => ($channel['callstate'] ?? '') !== 'HELD');

            if (! $consult) {
                return $base + [
                    'sent' => false,
                    'reason' => 'No active consultation call was found to cancel.',
                ];
            }

            $activeCallId = (string) $eslService->executeCommand(
                'uuid_getvar ' . $consult['uuid'] . ' sip_call_id',
                false
            );
        }

        $commands = $this->buildCommands($action, $destination, $activeCallId);
        $bodies = array_map(fn (array $command) => json_encode($command), $commands);
        $result = 'dry-run';

        foreach ($bodies as $index => $body) {
            if (! $dryRun) {
                if ($index > 0 && $this->stepDelayMicroseconds > 0) {
                    usleep($this->stepDelayMicroseconds);
                }

                $result = $eslService->sendEvent('NOTIFY', $headers, $body, false);

                if ($result === null) {
                    return $base + [
                        'sent' => false,
                        'reason' => sprintf(
                            'FreeSWITCH ESL did not accept the Poly REST NOTIFY (step %d of %d).',
                            $index + 1,
                            count($bodies)
                        ),
                        'registration_call_id' => $registrationCallId,
                    ];
                }
            }
        }

        return $base + [
            'sent' => true,
            'reason' => null,
            'transport' => 'esl-notify',
            'event_string' => 'ACTION-URI',
            'registration_call_id' => $registrationCallId,
            'command' => sprintf(
                'sendevent NOTIFY profile=%s event-string=ACTION-URI user=%s host=%s call-id=%s content-type=application/JSON',
                $profile,
                $extension->extension,
                $domain->domain_name,
                $registrationCallId
            ),
            'body' => implode(' | ', $bodies),
            'result' => $result,
        ];
    }

    /**
     * @return array<int, array{command-URI: string, data?: array<string, string>}>
     */
    public function buildCommands(
        string $action,
        ?string $destination = null,
        ?string $activeCallId = null
    ): array {
        $action = Str::lower(trim($action));
        $destination = $this->optionalSafeValue($destination, 'Destination');
        $activeCallId = $activeCallId !== null && trim($activeCallId) !== '' ? trim($activeCallId) : null;

        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException(
                "Action [{$action}] is not supported for Poly. Supported actions: "
                . implode(', ', self::ACTIONS) . '.'
            );
        }

        if (in_array($action, [self::ACTION_COMPLETE_TRANSFER, self::ACTION_CONFERENCE], true)) {
            throw new InvalidArgumentException(
                "{$action} is completed by the PBX for Poly Edge; it has no NOTIFY command."
            );
        }

        if (in_array($action, [self::ACTION_BLIND_TRANSFER, self::ACTION_ATTENDED_TRANSFER], true)
            && $destination === null) {
            throw new InvalidArgumentException("A destination is required for {$action}.");
        }

        // transferCall, answerCall, and the cancel-transfer endCall require a
        // call reference (the phone drops the call outright on a Ref-less
        // transferCall — verified on an Edge E350, fw 8.3.1). The phone accepts
        // the call's SIP call-id as Ref.
        if (in_array($action, [
            self::ACTION_BLIND_TRANSFER,
            self::ACTION_ATTENDED_TRANSFER,
            self::ACTION_ANSWER_CALL,
            self::ACTION_CANCEL_TRANSFER,
        ], true) && $activeCallId === null) {
            throw new InvalidArgumentException(
                "{$action} requires the call reference; it is resolved automatically unless --force is used."
            );
        }

        $ref = $activeCallId !== null ? ['Ref' => $activeCallId] : [];

        return match ($action) {
            self::ACTION_HOLD => [$this->command('/api/v1/callctrl/holdCall', $ref)],
            self::ACTION_RESUME => [$this->command('/api/v1/callctrl/resumeCall', $ref)],
            self::ACTION_BLIND_TRANSFER => [$this->command(
                '/api/v1/callctrl/transferCall',
                $ref + ['TransferDest' => $destination]
            )],
            // REST has no consultative transfer, so compose it: hold the
            // original call, then dial the consultation call.
            self::ACTION_ATTENDED_TRANSFER => [
                $this->command('/api/v1/callctrl/holdCall', $ref),
                $this->command('/api/v1/callctrl/dial', [
                    'Dest' => $destination,
                    'Line' => '1',
                    'Type' => 'TEL',
                ]),
            ],
            self::ACTION_CANCEL_TRANSFER => [$this->command('/api/v1/callctrl/endCall', $ref)],
            self::ACTION_MUTE_ON => [$this->command('/api/v1/callctrl/mute', ['state' => '1'])],
            self::ACTION_MUTE_OFF => [$this->command('/api/v1/callctrl/mute', ['state' => '0'])],
            self::ACTION_END_CALL => [$this->command('/api/v1/callctrl/endCall', $ref)],
            self::ACTION_ANSWER_CALL => [$this->command('/api/v1/callctrl/answerCall', $ref)],
        };
    }

    public function actionIsToggle(string $action): bool
    {
        return false;
    }

    /**
     * Join the far legs of the held call and the active consultation with
     * uuid_bridge; the phone's own legs then clear via hangup_after_bridge.
     * No REST endpoint exists for this (transferCall is blind-only), so it's
     * PBX-side via the shared PbxCallControl (see also the Generic driver).
     */
    private function completeTransferViaBridge(
        FreeswitchEslService $eslService,
        Extensions $extension,
        Domain $domain,
        bool $dryRun
    ): array {
        if ($dryRun) {
            return ['sent' => true, 'reason' => null, 'command' => '(dry-run) complete-transfer', 'body' => '', 'result' => 'dry-run'];
        }

        $channels = $this->pbx->channelsForExtension($eslService, $extension, $domain);

        return $this->pbx->bridgeHeldAndActive($eslService, $channels) + ['transport' => 'esl-uuid-bridge', 'body' => ''];
    }

    /**
     * Build a local 3-way in mod_conference: needs one held call and one
     * active consultation (same state as complete-transfer). The phone keeps
     * its active call; its far-end audio becomes the conference mix. No REST
     * conference endpoint exists, so this is PBX-side via PbxCallControl.
     */
    private function conferenceViaPbx(
        FreeswitchEslService $eslService,
        Extensions $extension,
        Domain $domain,
        bool $dryRun
    ): array {
        if ($dryRun) {
            return ['sent' => true, 'reason' => null, 'command' => '(dry-run) conference', 'body' => '', 'result' => 'dry-run'];
        }

        $channels = $this->pbx->channelsForExtension($eslService, $extension, $domain);

        return $this->pbx->conferenceHeldAndActive($eslService, $channels, 'phonectl-' . $extension->extension)
            + ['transport' => 'esl-conference', 'body' => ''];
    }

    private function command(string $uri, array $data): array
    {
        return $data === []
            ? ['command-URI' => $uri]
            : ['command-URI' => $uri, 'data' => $data];
    }

    private function optionalSafeValue(?string $value, string $label): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (! preg_match('/^[A-Za-z0-9_*#+.@:-]+$/', $value)) {
            throw new InvalidArgumentException("{$label} contains unsupported characters.");
        }

        return $value;
    }
}
