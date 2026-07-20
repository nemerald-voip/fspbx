<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Extensions;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Phone-control for vendors with no remote-control mechanism of their own
 * (Grandstream GXP, and everything else that only supports auto-answer
 * click-to-dial). Every action operates directly on the FreeSWITCH channel
 * via PbxCallControl — the phone is never asked to do anything, so its own
 * screen/LEDs do not reflect the change (no hold icon, no mute indicator).
 */
class GenericPhoneControlDriver implements PhoneControlDriver
{
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
    ];

    /**
     * @param string[] $agentPatterns substrings matched against the lowercased
     *   user agent; an empty array makes this instance match every agent — the
     *   fleet-wide "generic" catch-all vendor entry.
     */
    public function __construct(
        private PbxCallControl $pbx,
        private string $vendorSlug,
        private string $vendorLabel,
        private array $agentPatterns = []
    ) {
    }

    public function vendor(): string
    {
        return $this->vendorSlug;
    }

    public function label(): string
    {
        return $this->vendorLabel;
    }

    public function matchesAgent(string $agent): bool
    {
        if ($this->agentPatterns === []) {
            return true;
        }

        $agent = Str::lower(trim($agent));

        foreach ($this->agentPatterns as $pattern) {
            if (str_contains($agent, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function supportedActions(): array
    {
        return self::ACTIONS;
    }

    public function actionIsToggle(string $action): bool
    {
        return false;
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

        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException(
                "Action [{$action}] is not supported for {$this->vendorLabel}. Supported actions: "
                . implode(', ', self::ACTIONS) . '.'
            );
        }

        if (in_array($action, [self::ACTION_BLIND_TRANSFER, self::ACTION_ATTENDED_TRANSFER], true)
            && ($destination === null || trim($destination) === '')) {
            throw new InvalidArgumentException("A destination is required for {$action}.");
        }

        $registration = $group['registrations'][0] ?? [];
        $profile = (string) ($group['sip_profile_name'] ?? $registration['sip_profile_name'] ?? '');
        $contact = (string) ($registration['contact'] ?? '');
        $base = [
            'vendor' => $this->vendor(),
            'agent' => $group['agent'] ?? '',
            'lan_ip' => (string) ($group['lan_ip'] ?? ''),
            'sip_profile_name' => $profile,
            'target_uri' => $extension->extension . '@' . $domain->domain_name,
        ];

        if ($dryRun) {
            return $base + [
                'sent' => true,
                'reason' => null,
                'transport' => 'esl-pbx',
                'command' => "(dry-run) {$action}",
                'body' => '',
                'result' => 'dry-run',
            ];
        }

        $channels = $this->pbx->channelsForExtension($eslService, $extension, $domain);
        $local = $channels->first();

        if ($local === null && $action !== self::ACTION_CANCEL_TRANSFER) {
            return $base + ['sent' => false, 'reason' => 'No active call was found for this extension.'];
        }

        $context = (string) ($extension->user_context ?: $domain->domain_name);

        $result = match ($action) {
            self::ACTION_HOLD => $this->pbx->hold($eslService, $local['uuid']),
            self::ACTION_RESUME => $this->pbx->resume($eslService, $local['uuid']),
            self::ACTION_MUTE_ON => $this->pbx->mute($eslService, $local['uuid'], true),
            self::ACTION_MUTE_OFF => $this->pbx->mute($eslService, $local['uuid'], false),
            self::ACTION_END_CALL => $this->pbx->endCall($eslService, $local['uuid']),
            self::ACTION_BLIND_TRANSFER => $this->pbx->blindTransfer(
                $eslService,
                $local['uuid'],
                (string) $destination,
                $context
            ),
            self::ACTION_ATTENDED_TRANSFER => $this->attendedTransfer(
                $eslService,
                $extension,
                $domain,
                $profile,
                $contact,
                $context,
                $local['uuid'],
                (string) $destination
            ),
            self::ACTION_COMPLETE_TRANSFER => $this->pbx->bridgeHeldAndActive($eslService, $channels),
            self::ACTION_CANCEL_TRANSFER => $this->pbx->endActiveConsultation($eslService, $channels),
            self::ACTION_CONFERENCE => $this->pbx->conferenceHeldAndActive(
                $eslService,
                $channels,
                'phonectl-' . $extension->extension
            ),
        };

        return $base + ['transport' => 'esl-pbx', 'body' => ''] + $result;
    }

    /**
     * The phone has no way to be told "hold and dial a consultation call", so
     * the PBX holds the existing call and originates a fresh auto-answered
     * leg to the destination — the same auto-answer mechanism click-to-dial
     * uses (see ClickToDialService::sendAutoAnswerOriginate), standing in for
     * the consultation call a vendor's own attended-transfer key would start.
     */
    private function attendedTransfer(
        FreeswitchEslService $eslService,
        Extensions $extension,
        Domain $domain,
        string $profile,
        string $contact,
        string $context,
        string $localUuid,
        string $destination
    ): array {
        if ($profile === '' || $contact === '') {
            return ['sent' => false, 'reason' => 'Registration profile or contact could not be resolved.'];
        }

        if (! preg_match('/^[\w*#+.@-]+$/', $destination)) {
            return ['sent' => false, 'reason' => 'Destination contains characters that cannot be passed to originate.'];
        }

        $hold = $this->pbx->hold($eslService, $localUuid);

        if (! $hold['sent']) {
            return $hold;
        }

        $callerName = trim(preg_replace('/[\'",{}\[\]]/', '', (string) $extension->effective_caller_id_name))
            ?: (string) $extension->extension;

        $command = sprintf(
            'originate {domain_uuid=%5$s,domain_name=%6$s,sip_invite_domain=%6$s,'
                . 'origination_caller_id_number=%7$s,origination_caller_id_name=%7$s,'
                . 'sip_auto_answer=true,ignore_early_media=true}sofia/%2$s/%3$s'
                . ' \'unset:sip_auto_answer,unset:ignore_early_media,unset:sip_h_Call-Info,'
                . 'set:effective_caller_id_number=%7$s,set:effective_caller_id_name=%8$s,'
                . 'set:caller_id_number=%7$s,set:caller_id_name=%8$s,'
                . 'set:presence_id=%7$s@%6$s,'
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

        $result = (string) $eslService->executeCommand($command, false);
        $sent = str_starts_with(trim($result), '+OK');

        return [
            'sent' => $sent,
            'reason' => $sent ? null : "Consultation call failed: {$result}",
            'command' => $command,
            'result' => $result,
        ];
    }
}
