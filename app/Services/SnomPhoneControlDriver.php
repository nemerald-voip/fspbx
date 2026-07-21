<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Extensions;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SnomPhoneControlDriver implements PhoneControlDriver
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
        self::ACTION_DND_TOGGLE,
        self::ACTION_ANSWER_CALL,
    ];

    public function __construct(private int $stepDelayMicroseconds = 2_000_000)
    {
    }

    public function vendor(): string
    {
        return 'snom';
    }

    public function label(): string
    {
        return 'Snom';
    }

    public function matchesAgent(string $agent): bool
    {
        return str_contains(Str::lower(trim($agent)), 'snom');
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
        $fragments = $this->buildActionFragments($action, $destination);
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

        $headers = [
            'profile' => $profile,
            'event-string' => 'xml',
            'user' => $extension->extension,
            'host' => $domain->domain_name,
            'call-id' => $registrationCallId,
            'content-type' => 'application/snomxml',
        ];
        $result = 'dry-run';

        foreach ($fragments as $index => $fragment) {
            if (! $dryRun) {
                if ($index > 0 && $this->stepDelayMicroseconds > 0) {
                    // Multi-key actions need the phone to finish rendering the
                    // previous step (e.g. the transfer screen) before the next key.
                    usleep($this->stepDelayMicroseconds);
                }

                $result = $eslService->sendEvent('NOTIFY', $headers, $this->wrapFragment($fragment), false);

                if ($result === null) {
                    return $base + [
                        'sent' => false,
                        'reason' => sprintf(
                            'FreeSWITCH ESL did not accept the Snom minibrowser NOTIFY (step %d of %d: %s).',
                            $index + 1,
                            count($fragments),
                            $fragment
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
            'event_string' => 'xml',
            'registration_call_id' => $registrationCallId,
            'command' => sprintf(
                'sendevent NOTIFY profile=%s event-string=xml user=%s host=%s call-id=%s content-type=application/snomxml',
                $profile,
                $extension->extension,
                $domain->domain_name,
                $registrationCallId
            ),
            'body' => implode(' | ', $fragments),
            'result' => $result,
        ];
    }

    /**
     * @return string[] snom:// fragments, sent as one NOTIFY each in order
     */
    public function buildActionFragments(string $action, ?string $destination = null): array
    {
        $action = Str::lower(trim($action));
        $destination = $this->optionalSafeValue($destination, 'Destination');

        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException(
                "Action [{$action}] is not supported for Snom. Supported actions: "
                . implode(', ', self::ACTIONS) . '.'
            );
        }

        if (in_array($action, [self::ACTION_BLIND_TRANSFER, self::ACTION_ATTENDED_TRANSFER], true)
            && $destination === null) {
            throw new InvalidArgumentException("A destination is required for {$action}.");
        }

        return match ($action) {
            // Snom minibrowser key fragments are key-press simulation (state
            // "relevant" required to act outside the minibrowser). F_HOLD is a
            // toggle acting on the selected call; the caller confirms state first.
            self::ACTION_HOLD => ['key=F_HOLD'],
            self::ACTION_RESUME => ['key=F_HOLD'],
            // Transfer key opens the transfer screen; numberdial completes it.
            self::ACTION_BLIND_TRANSFER => ['key=F_TRANSFER', "numberdial={$destination}"],
            // Hold + a second call = consultation; complete-transfer joins them.
            self::ACTION_ATTENDED_TRANSFER => ['key=F_HOLD', "numberdial={$destination}"],
            // Transfer with two calls opens a "transfer to" selection; OK confirms.
            self::ACTION_COMPLETE_TRANSFER => ['key=F_TRANSFER', 'key=F_OK'],
            self::ACTION_CANCEL_TRANSFER => ['key=F_CANCEL'],
            // Merges the held and active calls in one press (unlike Yealink).
            self::ACTION_CONFERENCE => ['key=F_CONFERENCE'],
            self::ACTION_MUTE_TOGGLE => ['key=F_MUTE'],
            self::ACTION_END_CALL => ['key=F_CANCEL'],
            // The phone ignores set:dnd_mode even from a trusted host, so DND
            // is only available as a toggle, not deterministic on/off.
            self::ACTION_DND_TOGGLE => ['key=F_DND'],
            // Answers a ringing call. Verified empirically on a D862: OFFHOOK,
            // ANSWER, F_ANSWER, and OK all did nothing; ENTER is what actually
            // answers (undocumented — the public Snom keyevent list doesn't
            // call this out as the answer key).
            self::ACTION_ANSWER_CALL => ['key=ENTER'],
        };
    }

    public function actionIsToggle(string $action): bool
    {
        return in_array(
            Str::lower(trim($action)),
            [self::ACTION_MUTE_TOGGLE, self::ACTION_DND_TOGGLE],
            true
        );
    }

    private function wrapFragment(string $fragment): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<SnomIPPhoneSilent state="relevant" document_id="phone_control">'
            . '<fetch mil="10">snom://mb_nop#' . $fragment . '</fetch>'
            . '</SnomIPPhoneSilent>';
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
