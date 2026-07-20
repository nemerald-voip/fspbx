<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Extensions;

interface PhoneControlDriver
{
    public const ACTION_HOLD = 'hold';
    public const ACTION_RESUME = 'resume';
    public const ACTION_BLIND_TRANSFER = 'blind-transfer';
    public const ACTION_ATTENDED_TRANSFER = 'attended-transfer';
    public const ACTION_COMPLETE_TRANSFER = 'complete-transfer';
    public const ACTION_CANCEL_TRANSFER = 'cancel-transfer';
    public const ACTION_CONFERENCE = 'conference';
    public const ACTION_MUTE_TOGGLE = 'mute-toggle';
    public const ACTION_MUTE_ON = 'mute-on';
    public const ACTION_MUTE_OFF = 'mute-off';
    public const ACTION_END_CALL = 'end-call';
    public const ACTION_ANSWER_CALL = 'answer-call';
    public const ACTION_DND_ON = 'dnd-on';
    public const ACTION_DND_OFF = 'dnd-off';
    public const ACTION_DND_TOGGLE = 'dnd-toggle';

    public function vendor(): string;

    public function label(): string;

    public function matchesAgent(string $agent): bool;

    public function supportedActions(): array;

    /**
     * $activeCallId is the SIP call-id of the extension's single guarded call,
     * resolved by PhoneControlService; null under --force or --dry-run.
     * Key-simulation drivers ignore it; API drivers use it as the call reference.
     */
    public function send(
        FreeswitchEslService $eslService,
        Extensions $extension,
        Domain $domain,
        array $group,
        string $action,
        ?string $destination = null,
        ?string $activeCallId = null,
        bool $dryRun = false
    ): array;

    public function actionIsToggle(string $action): bool;
}
