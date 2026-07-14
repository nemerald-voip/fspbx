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

        return $eslService->getAllChannels(false)
            ->filter(fn (array $channel) => (string) ($channel['presence_id'] ?? '') === $presenceId)
            ->values()
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

        if (in_array($action, [
                PhoneControlDriver::ACTION_HOLD,
                PhoneControlDriver::ACTION_RESUME,
                PhoneControlDriver::ACTION_END_CALL,
            ], true)
            && ! (bool) ($options['force'] ?? false)
            && ! (bool) ($options['dry_run'] ?? false)) {
            $this->guardCallState($eslService, $extension, $domain, $action);
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
                (bool) ($options['dry_run'] ?? false)
            );
        }

        $eslService->disconnect();

        return [
            'domain' => $domain,
            'extension' => $extension,
            'vendor' => $driver->vendor(),
            'action' => $action,
            'destination' => $destination,
            'state_is_toggle' => $driver->actionIsToggle($action),
            'group' => $selectedGroup,
            'groups' => $selectedGroups->values()->all(),
            'skipped_groups' => $selection['skipped']->values()->all(),
            'results' => $results,
        ];
    }

    /**
     * Call-state key actions are applied by the phone to whichever call is
     * selected on its screen, so refuse to send one unless FreeSWITCH confirms
     * exactly one call in a state the action makes sense for.
     */
    private function guardCallState(
        FreeswitchEslService $eslService,
        Extensions $extension,
        Domain $domain,
        string $action
    ): void {
        $calls = $this->activeCalls($eslService, $extension, $domain);
        $target = "{$extension->extension}@{$domain->domain_name}";

        if ($calls->isEmpty()) {
            throw new RuntimeException("Extension {$target} has no active calls to {$action}.");
        }

        if ($calls->count() > 1) {
            throw new RuntimeException(
                "Extension {$target} has {$calls->count()} active calls; {$action} acts on the call "
                . "selected on the phone, so this is ambiguous. Finish or select the right call on the phone, "
                . "or pass --force to bypass this check:\n" . $this->formatCalls($calls)
            );
        }

        $call = $calls->first();

        if ($action === PhoneControlDriver::ACTION_HOLD && $call['callstate'] === 'HELD') {
            throw new RuntimeException("The call on {$target} is already on hold.");
        }

        if ($action === PhoneControlDriver::ACTION_RESUME && $call['callstate'] !== 'HELD') {
            throw new RuntimeException(
                "The call on {$target} is not on hold (state: {$call['callstate']})."
            );
        }
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
