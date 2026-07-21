<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Extensions;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class PhoneControlService
{
    public function __construct(
        private PhoneControlDriverRegistry $drivers,
        private PhoneRegistrationTargetService $registrationTargets
    ) {
    }

    public function supportedVendors(): array
    {
        return $this->drivers->vendors();
    }

    public function supportedActions(string $vendor): array
    {
        return $this->drivers->forVendor($vendor)->supportedActions();
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

    public function hold(
        FreeswitchEslService $eslService,
        string $extension,
        string $domain,
        array $options = []
    ): array {
        return $this->execute(
            $eslService,
            $extension,
            $domain,
            PhoneControlDriver::ACTION_HOLD,
            null,
            $options
        );
    }

    public function resume(
        FreeswitchEslService $eslService,
        string $extension,
        string $domain,
        array $options = []
    ): array {
        return $this->execute(
            $eslService,
            $extension,
            $domain,
            PhoneControlDriver::ACTION_RESUME,
            null,
            $options
        );
    }

    public function blindTransfer(
        FreeswitchEslService $eslService,
        string $extension,
        string $domain,
        string $destination,
        array $options = []
    ): array {
        return $this->execute(
            $eslService,
            $extension,
            $domain,
            PhoneControlDriver::ACTION_BLIND_TRANSFER,
            $destination,
            $options
        );
    }

    public function attendedTransfer(
        FreeswitchEslService $eslService,
        string $extension,
        string $domain,
        string $destination,
        array $options = []
    ): array {
        return $this->execute(
            $eslService,
            $extension,
            $domain,
            PhoneControlDriver::ACTION_ATTENDED_TRANSFER,
            $destination,
            $options
        );
    }

    public function conference(
        FreeswitchEslService $eslService,
        string $extension,
        string $domain,
        array $options = []
    ): array {
        return $this->execute(
            $eslService,
            $extension,
            $domain,
            PhoneControlDriver::ACTION_CONFERENCE,
            null,
            $options
        );
    }

    public function toggleMute(
        FreeswitchEslService $eslService,
        string $extension,
        string $domain,
        array $options = []
    ): array {
        return $this->execute(
            $eslService,
            $extension,
            $domain,
            PhoneControlDriver::ACTION_MUTE_TOGGLE,
            null,
            $options
        );
    }

    public function setDnd(
        FreeswitchEslService $eslService,
        string $extension,
        string $domain,
        bool $enabled,
        array $options = []
    ): array {
        return $this->execute(
            $eslService,
            $extension,
            $domain,
            $enabled ? PhoneControlDriver::ACTION_DND_ON : PhoneControlDriver::ACTION_DND_OFF,
            null,
            $options
        );
    }

    public function activeCallsFor(
        FreeswitchEslService $eslService,
        string $extensionNumber,
        string $domainIdentifier
    ): Collection {
        $target = $this->registrationTargets->resolveTarget($extensionNumber, $domainIdentifier);

        return $this->activeCalls($eslService, $target['extension'], $target['domain']);
    }

    public function activeCalls(
        FreeswitchEslService $eslService,
        Extensions $extension,
        Domain $domain
    ): Collection {
        $presenceId = $extension->extension . '@' . $domain->domain_name;

        return $eslService->channelsForPresenceId($presenceId)
            ->map(function (array $channel) use ($eslService) {
                $sipCallId = (string) $eslService->executeCommand(
                    'uuid_getvar ' . $channel['uuid'] . ' sip_call_id',
                    false
                );

                if ($sipCallId === '_undef_' || str_starts_with($sipCallId, '-ERR')) {
                    $sipCallId = '';
                }

                return [
                    'uuid' => $channel['uuid'],
                    'sip_call_id' => $sipCallId,
                    'direction' => $channel['direction'] ?? '',
                    'callstate' => $channel['callstate'] ?? '',
                    // The phone placed inbound legs (other party = dest) and
                    // receives outbound legs (other party = caller id).
                    'other_party' => ($channel['direction'] ?? '') === 'inbound'
                        ? (string) ($channel['dest'] ?? '')
                        : (string) ($channel['cid_num'] ?? ''),
                ];
            });
    }

    public function execute(
        FreeswitchEslService $eslService,
        string $extensionNumber,
        string $domainIdentifier,
        string $action,
        ?string $destination = null,
        array $options = []
    ): array {
        $requestedVendor = Str::lower(trim((string) ($options['vendor'] ?? '')));

        if ($requestedVendor !== '') {
            $this->drivers->forVendor($requestedVendor);
        }

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
            throw new RuntimeException(
                "No supported phone-control registrations found for {$extensionNumber}@{$domain->domain_name}. "
                . 'Supported vendors: ' . implode(', ', $this->supportedVendors()) . '.'
            );
        }

        $selection = $this->registrationTargets->selectGroups(
            $groups,
            $options,
            $this->supportedVendors(),
            'supported phone-control registrations'
        );
        $selectedGroups = $selection['selected'];
        $selectedGroup = $selectedGroups->first();
        $driver = $this->drivers->forVendor((string) $selectedGroup['vendor']);
        $action = Str::lower(trim($action));

        if (! in_array($action, $driver->supportedActions(), true)) {
            throw new RuntimeException(
                "Action [{$action}] is not supported for {$driver->label()}. Supported actions: "
                . implode(', ', $driver->supportedActions()) . '.'
            );
        }

        $activeCallId = null;

        if (in_array($action, [
                PhoneControlDriver::ACTION_HOLD,
                PhoneControlDriver::ACTION_RESUME,
                PhoneControlDriver::ACTION_END_CALL,
                PhoneControlDriver::ACTION_BLIND_TRANSFER,
                PhoneControlDriver::ACTION_ATTENDED_TRANSFER,
                PhoneControlDriver::ACTION_ANSWER_CALL,
            ], true)
            && ! (bool) ($options['force'] ?? false)
            && ! (bool) ($options['dry_run'] ?? false)) {
            $activeCallId = $this->guardCallState($eslService, $extension, $domain, $action);
        }

        $results = [];

        foreach ($selectedGroups as $group) {
            $results[] = $driver->send(
                $eslService,
                $extension,
                $domain,
                $group,
                $action,
                $destination,
                $activeCallId,
                (bool) ($options['dry_run'] ?? false)
            );
        }

        $autoResume = null;

        if ($action === PhoneControlDriver::ACTION_CANCEL_TRANSFER
            && ! (bool) ($options['dry_run'] ?? false)
            && ! (bool) ($options['no_resume'] ?? false)
            && collect($results)->every(fn (array $result) => $result['sent'])) {
            $autoResume = $this->autoResumeAfterCancel($eslService, $extension, $domain, $driver, $selectedGroup);
        }

        $eslService->disconnect();

        return [
            'domain' => $domain,
            'extension' => $extension,
            'vendor' => $driver->vendor(),
            'action' => $action,
            'destination' => $destination,
            'active_call_id' => $activeCallId,
            'state_is_toggle' => $driver->actionIsToggle($action),
            'group' => $selectedGroup,
            'groups' => $selectedGroups->values()->all(),
            'skipped_groups' => $selection['skipped']->values()->all(),
            'results' => $results,
            'auto_resume' => $autoResume,
        ];
    }

    /**
     * After cancel-transfer drops the consultation call, the original caller
     * is left on hold (matching what a physical Cancel key press would do).
     * If exactly one call remains and it's confirmed HELD, resume it
     * automatically so cancel-transfer reads as a single complete action
     * instead of requiring a separate resume command. Silently skipped (not
     * an error) if the state isn't clean enough to resume safely.
     *
     * "sent" from the cancel-transfer driver call only means FreeSWITCH
     * accepted the request — vendor-NOTIFY drivers (Poly, Yealink, Snom) still
     * need a real SIP round trip to the phone before it actually hangs up the
     * consultation leg, so the channel list briefly still shows both calls.
     * Poll a few times for it to settle before giving up; a PBX-side driver
     * (Generic/Grandstream) resolves on the first check since uuid_kill is
     * synchronous, so this adds no delay there.
     */
    private function autoResumeAfterCancel(
        FreeswitchEslService $eslService,
        Extensions $extension,
        Domain $domain,
        PhoneControlDriver $driver,
        array $group
    ): ?array {
        $maxAttempts = 5;
        $delayMicroseconds = 400_000;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $calls = $this->activeCalls($eslService, $extension, $domain);

            if ($calls->count() === 1 && $calls->first()['callstate'] === 'HELD') {
                return $driver->send(
                    $eslService,
                    $extension,
                    $domain,
                    $group,
                    PhoneControlDriver::ACTION_RESUME,
                    null,
                    (string) $calls->first()['sip_call_id'],
                    false
                );
            }

            // Only keep waiting while it looks like the consultation leg just
            // hasn't cleared yet (still two calls); any other count means
            // there's nothing to usefully wait for.
            if ($calls->count() !== 2 || $attempt === $maxAttempts) {
                return null;
            }

            usleep($delayMicroseconds);
        }

        return null;
    }

    /**
     * Call-state actions target one specific call, so refuse to send one
     * unless FreeSWITCH confirms exactly one call in a state the action makes
     * sense for. Returns that call's SIP call-id — API drivers (Poly) use it
     * as the call reference; key-simulation drivers ignore it.
     */
    private function guardCallState(
        FreeswitchEslService $eslService,
        Extensions $extension,
        Domain $domain,
        string $action
    ): string {
        $calls = $this->activeCalls($eslService, $extension, $domain);
        $target = "{$extension->extension}@{$domain->domain_name}";

        if ($calls->isEmpty()) {
            throw new RuntimeException("Extension {$target} has no active calls to {$action}.");
        }

        if ($calls->count() > 1) {
            throw new RuntimeException(
                "Extension {$target} has {$calls->count()} active calls; {$action} targets a single call, "
                . "so this is ambiguous. Finish or select the right call on the phone, "
                . "or pass --force to bypass this check:\n" . $this->formatCalls($calls)
            );
        }

        $call = $calls->first();
        $state = (string) $call['callstate'];

        if ($action === PhoneControlDriver::ACTION_HOLD && $state === 'HELD') {
            throw new RuntimeException("The call on {$target} is already on hold.");
        }

        if ($action === PhoneControlDriver::ACTION_RESUME && $state !== 'HELD') {
            throw new RuntimeException("The call on {$target} is not on hold (state: {$state}).");
        }

        if (in_array($action, [
            PhoneControlDriver::ACTION_BLIND_TRANSFER,
            PhoneControlDriver::ACTION_ATTENDED_TRANSFER,
        ], true) && $state !== 'ACTIVE') {
            throw new RuntimeException(
                "The call on {$target} is not answered yet (state: {$state}); transfers need an active call."
            );
        }

        if ($action === PhoneControlDriver::ACTION_ANSWER_CALL && $state !== 'RINGING') {
            throw new RuntimeException(
                "Extension {$target} has no ringing call to answer (state: {$state})."
            );
        }

        return (string) $call['sip_call_id'];
    }

    private function formatCalls(Collection $calls): string
    {
        return $calls->map(fn (array $call, int $index) => sprintf(
            '[%d] call-id=%s state=%s other-party=%s',
            $index + 1,
            $call['sip_call_id'] ?: '(unknown)',
            $call['callstate'] ?: '(unknown)',
            $call['other_party'] ?: '(unknown)'
        ))->implode("\n");
    }

    private function registrationIdentity(string $agent): ?array
    {
        $driver = $this->drivers->forAgent($agent);

        return $driver
            ? ['vendor' => $driver->vendor(), 'label' => $driver->label()]
            : null;
    }
}
