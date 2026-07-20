<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Extensions;
use Illuminate\Support\Str;
use InvalidArgumentException;

class YealinkPhoneControlDriver implements PhoneControlDriver
{
    private const ACTIONS = [
        self::ACTION_HOLD,
        self::ACTION_RESUME,
        self::ACTION_BLIND_TRANSFER,
        self::ACTION_ATTENDED_TRANSFER,
        self::ACTION_COMPLETE_TRANSFER,
        self::ACTION_CANCEL_TRANSFER,
        self::ACTION_CONFERENCE,
        self::ACTION_MUTE_TOGGLE,
        self::ACTION_END_CALL,
        self::ACTION_DND_ON,
        self::ACTION_DND_OFF,
    ];

    public function vendor(): string
    {
        return 'yealink';
    }

    public function label(): string
    {
        return 'Yealink';
    }

    public function matchesAgent(string $agent): bool
    {
        $agent = Str::lower(trim($agent));

        return $agent !== ''
            && (str_contains($agent, 'yealink') || str_contains($agent, 'sip-t'));
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
        $registration = $group['registrations'][0] ?? [];
        $profile = (string) ($group['sip_profile_name'] ?? $registration['sip_profile_name'] ?? '');
        $registrationCallId = (string) ($registration['call_id'] ?? '');
        $body = $this->buildActionBody($action, $destination);
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

        $eventString = 'ACTION-URI';
        $contentType = 'message/sipfrag';
        $headers = [
            'profile' => $profile,
            'event-string' => $eventString,
            'user' => $extension->extension,
            'host' => $domain->domain_name,
            'call-id' => $registrationCallId,
            'content-type' => $contentType,
        ];
        $result = $dryRun
            ? 'dry-run'
            : $eslService->sendEvent('NOTIFY', $headers, $body, false);
        $sent = $dryRun || $result !== null;

        return $base + [
            'sent' => $sent,
            'reason' => $sent ? null : 'FreeSWITCH ESL did not accept the Yealink Action URI event.',
            'transport' => 'esl-notify',
            'event_string' => $eventString,
            'registration_call_id' => $registrationCallId,
            'command' => sprintf(
                'sendevent NOTIFY profile=%s event-string=%s user=%s host=%s call-id=%s content-type=%s',
                $profile,
                $eventString,
                $extension->extension,
                $domain->domain_name,
                $registrationCallId,
                $contentType
            ),
            'body' => $body,
            'result' => $result,
        ];
    }

    public function buildActionBody(
        string $action,
        ?string $destination = null
    ): string {
        $action = Str::lower(trim($action));
        $destination = $this->optionalSafeValue($destination, 'Destination');

        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException(
                "Action [{$action}] is not supported for Yealink. Supported actions: "
                . implode(', ', self::ACTIONS) . '.'
            );
        }

        if (in_array($action, [self::ACTION_BLIND_TRANSFER, self::ACTION_ATTENDED_TRANSFER], true)
            && $destination === null) {
            throw new InvalidArgumentException("A destination is required for {$action}.");
        }

        return match ($action) {
            // Yealink ACTION-URI is key-press simulation with no per-call
            // addressing (HOLD:<call-id> bodies are ignored; verified on a
            // T53W, firmware 96.86.0.70). F_HOLD toggles the selected call;
            // the caller is responsible for confirming the call state first.
            self::ACTION_HOLD => 'key=F_HOLD',
            self::ACTION_RESUME => 'key=F_HOLD',
            self::ACTION_BLIND_TRANSFER => "key=BTrans={$destination}",
            self::ACTION_ATTENDED_TRANSFER => "key=ATrans={$destination}",
            // Completes an in-progress attended transfer (Transfer key press);
            // while the consultation leg is still ringing this becomes a
            // semi-attended transfer.
            self::ACTION_COMPLETE_TRANSFER => 'key=F_TRANSFER',
            // Abandons the consultation leg of an attended transfer; the
            // original call stays on hold until a resume is sent.
            self::ACTION_CANCEL_TRANSFER => 'key=CANCEL',
            self::ACTION_CONFERENCE => 'key=F_CONFERENCE',
            self::ACTION_MUTE_TOGGLE => 'key=MUTE',
            self::ACTION_END_CALL => 'key=CALLEND',
            self::ACTION_DND_ON => 'key=DNDOn',
            self::ACTION_DND_OFF => 'key=DNDOff',
        };
    }

    public function actionIsToggle(string $action): bool
    {
        return Str::lower(trim($action)) === self::ACTION_MUTE_TOGGLE;
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
