<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Extensions;
use Illuminate\Support\Collection;

/**
 * Vendor-agnostic call-control primitives built on native FreeSWITCH ESL
 * commands (uuid_hold, uuid_kill, uuid_transfer, uuid_bridge, uuid_audio).
 * Used by phone-control drivers for actions their vendor's own remote-control
 * mechanism can't do: Poly's complete-transfer/conference (no REST endpoint),
 * and the Generic driver's entire action set (no remote-control mechanism at all).
 */
class PbxCallControl
{
    public function channelsForExtension(FreeswitchEslService $eslService, Extensions $extension, Domain $domain): Collection
    {
        return $eslService->channelsForPresenceId($extension->extension . '@' . $domain->domain_name);
    }

    public function hold(FreeswitchEslService $eslService, string $uuid): array
    {
        return $this->runCommand($eslService, "uuid_hold {$uuid}");
    }

    public function resume(FreeswitchEslService $eslService, string $uuid): array
    {
        return $this->runCommand($eslService, "uuid_hold off {$uuid}");
    }

    public function mute(FreeswitchEslService $eslService, string $uuid, bool $muted): array
    {
        return $this->runCommand($eslService, 'uuid_audio ' . $uuid . ' start write mute ' . ($muted ? '1' : '0'));
    }

    public function endCall(FreeswitchEslService $eslService, string $uuid): array
    {
        return $this->runCommand($eslService, "uuid_kill {$uuid}");
    }

    /**
     * Blind-transfers the far party of $localUuid's bridge to $destination;
     * the local leg clears itself once its bridge partner leaves.
     */
    public function blindTransfer(
        FreeswitchEslService $eslService,
        string $localUuid,
        string $destination,
        string $context
    ): array {
        $farUuid = $this->bridgeUuid($eslService, $localUuid);

        if ($farUuid === null) {
            return ['sent' => false, 'reason' => 'Could not resolve the far leg to transfer.'];
        }

        return $this->runCommand($eslService, "uuid_transfer {$farUuid} {$destination} XML {$context}");
    }

    /**
     * Joins the far legs of a held call and an active consultation call —
     * completes an attended transfer without either local leg's cooperation.
     */
    public function bridgeHeldAndActive(FreeswitchEslService $eslService, Collection $channels): array
    {
        [$held, $active] = $this->splitHeldActive($channels);

        if (! $held || ! $active) {
            return ['sent' => false, 'reason' => $this->missingCallsReason($channels, 'complete-transfer')];
        }

        $farHeld = $this->bridgeUuid($eslService, $held['uuid']);
        $farActive = $this->bridgeUuid($eslService, $active['uuid']);

        if ($farHeld === null || $farActive === null) {
            return ['sent' => false, 'reason' => 'Could not resolve the far legs of both calls to bridge.'];
        }

        return $this->runCommand($eslService, "uuid_bridge {$farHeld} {$farActive}");
    }

    /**
     * Moves the far legs of a held call and an active consultation call into
     * a fresh dynamic mod_conference room — a local 3-way with no native
     * conference endpoint required. Uses FreeSWITCH's stock "default" profile;
     * each leg sets conference_silent_entry before joining so that whichever
     * one lands first (the two legs join via separate, non-atomic commands)
     * doesn't trigger "you are the only person in this conference" — that
     * announcement is gated entirely on a per-channel silent-entry flag, not
     * on any conference-level setting, so no dedicated profile is needed.
     * mintwo (member flag) tears the room down once it drops back below two
     * members instead of leaving a lone leg parked in it.
     */
    public function conferenceHeldAndActive(FreeswitchEslService $eslService, Collection $channels, string $roomPrefix): array
    {
        [$held, $active] = $this->splitHeldActive($channels);

        if (! $held || ! $active) {
            return ['sent' => false, 'reason' => $this->missingCallsReason($channels, 'conference')];
        }

        $farHeld = $this->bridgeUuid($eslService, $held['uuid']);

        if ($farHeld === null) {
            return ['sent' => false, 'reason' => 'Could not resolve the far leg of the held call.'];
        }

        $room = $roomPrefix . '-' . substr(md5(uniqid('', true)), 0, 8);
        $dest = "set:conference_silent_entry=true,conference:{$room}@default+flags{mintwo}";
        $commands = [
            "uuid_transfer {$active['uuid']} -both '{$dest}' inline",
            "uuid_transfer {$farHeld} '{$dest}' inline",
        ];

        foreach ($commands as $command) {
            $result = (string) $eslService->executeCommand($command, false);

            if (! str_starts_with($result, '+OK')) {
                return [
                    'sent' => false,
                    'reason' => "uuid_transfer failed: {$result}",
                    'command' => implode(' | ', $commands),
                ];
            }
        }

        return [
            'sent' => true,
            'reason' => null,
            'conference_room' => $room,
            'command' => implode(' | ', $commands),
            'result' => '+OK',
        ];
    }

    /**
     * Ends the active (non-held) call only, leaving a held call untouched —
     * cancels an attended-transfer consultation.
     */
    public function endActiveConsultation(FreeswitchEslService $eslService, Collection $channels): array
    {
        $active = $channels->first(fn (array $channel) => ($channel['callstate'] ?? '') !== 'HELD');

        if (! $active) {
            return ['sent' => false, 'reason' => 'No active consultation call was found to cancel.'];
        }

        return $this->endCall($eslService, $active['uuid']);
    }

    /**
     * @return array{0: ?array, 1: ?array} [held, active]
     */
    private function splitHeldActive(Collection $channels): array
    {
        return [
            $channels->first(fn (array $channel) => ($channel['callstate'] ?? '') === 'HELD'),
            $channels->first(fn (array $channel) => ($channel['callstate'] ?? '') !== 'HELD'),
        ];
    }

    private function missingCallsReason(Collection $channels, string $action): string
    {
        return "{$action} needs one held call and one active consultation call "
            . "(found {$channels->count()} calls). Use attended-transfer first.";
    }

    private function bridgeUuid(FreeswitchEslService $eslService, string $uuid): ?string
    {
        $farUuid = trim((string) $eslService->executeCommand("uuid_getvar {$uuid} bridge_uuid", false));

        return ($farUuid === '' || $farUuid === '_undef_' || str_starts_with($farUuid, '-ERR')) ? null : $farUuid;
    }

    private function runCommand(FreeswitchEslService $eslService, string $command): array
    {
        $result = (string) $eslService->executeCommand($command, false);
        $sent = str_starts_with(trim($result), '+OK');

        return [
            'sent' => $sent,
            'reason' => $sent ? null : "{$command} failed: {$result}",
            'command' => $command,
            'result' => $result,
        ];
    }
}
