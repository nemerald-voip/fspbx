<?php

namespace App\Services\Fax;

/**
 * Decisions for a txfax channel that would otherwise outlive the fax.
 *
 * txfax is SAF_NO_LOOPBACK. FreeSWITCH loopback bowout copies the remaining
 * inline apps and every channel variable onto the gateway, then hangs up the
 * loopback that owns origination_uuid. A hangup scheduled on answer therefore
 * targets a channel that is already gone, and the gateway stays in txfax after
 * fax_success=1. The row's call_uuid is that origination uuid (or a later
 * attempt), not the channel still executing txfax.
 */
final class FaxChannelGuard
{
    public const SECONDS_PER_PAGE = 90;

    public const MIN_SECONDS = 900;

    public const UNKNOWN_PAGES_SECONDS = 21600;

    public const SUCCESS_HANGUP_SECONDS = 2;

    public const OVER_BUDGET_GRACE_SECONDS = 900;

    public const CONDITION = 'Fax channel still up after the page budget';

    public const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public static function budgetSeconds(?int $totalPages): int
    {
        if ($totalPages === null || $totalPages < 1) {
            return self::UNKNOWN_PAGES_SECONDS;
        }

        return max(self::MIN_SECONDS, $totalPages * self::SECONDS_PER_PAGE);
    }

    /**
     * Inline extension that rides the masquerade onto the gateway.
     * A comma or quote in the path would split the extension, so that send
     * stays a single txfax app and the result hooks still hang it up.
     */
    public static function inlineExtension(string $filePath, int $budgetSeconds): string
    {
        if (str_contains($filePath, ',') || str_contains($filePath, "'")) {
            return 'txfax:'.$filePath;
        }

        $budget = max(1, $budgetSeconds);

        return 'sched_hangup:+'.$budget.',txfax:'.$filePath.',hangup:NORMAL_CLEARING';
    }

    public static function resultHangupApplication(): string
    {
        return 'sched_hangup +'.self::SUCCESS_HANGUP_SECONDS;
    }

    public static function isUuid(mixed $value): bool
    {
        return preg_match(self::UUID_PATTERN, self::norm($value)) === 1;
    }

    public static function isTxfax(mixed $application): bool
    {
        return strtolower(self::norm($application)) === 'txfax';
    }

    /**
     * @param  array<string, mixed>  $channel
     */
    public static function matches(array $channel, string $outboundFaxUuid, ?string $callUuid): bool
    {
        $fax = self::norm($channel['outbound_fax_uuid'] ?? '');
        if ($fax !== '' && $fax === self::norm($outboundFaxUuid)) {
            return true;
        }

        $wanted = self::norm($callUuid ?? '');
        if ($wanted === '') {
            return false;
        }

        foreach (['uuid', 'origination_uuid', 'originating_leg_uuid', 'call_uuid'] as $key) {
            if (self::norm($channel[$key] ?? '') === $wanted) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $channels
     */
    public static function anySucceeded(array $channels): bool
    {
        foreach ($channels as $channel) {
            if (self::norm($channel['fax_success'] ?? '') === '1') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $matched
     * @return list<string>
     */
    public static function killUuids(array $matched, ?string $callUuid): array
    {
        $uuids = [];
        foreach ($matched as $channel) {
            $uuid = self::norm($channel['uuid'] ?? '');
            if (self::isUuid($uuid)) {
                $uuids[] = $uuid;
            }
        }

        $call = self::norm($callUuid ?? '');
        if (self::isUuid($call)) {
            $uuids[] = $call;
        }

        return array_values(array_unique($uuids));
    }

    /**
     * What phase A should do once it has looked at live txfax channels.
     *
     * An unknown channel list never redispatches. uuid_exists on the row
     * call_uuid is false after loopback bowout while the gateway is still in
     * txfax, so a failed show channels must not look like "the call is gone".
     * A channel that still matches this fax is never redispatched. Success on
     * that channel is sent.
     *
     * @param  list<array<string, mixed>>  $matched
     * @return 'sent'|'leave'|'redispatch'
     */
    public static function phaseADecision(bool $channelListKnown, array $matched, ?bool $callExists): string
    {
        if (! $channelListKnown) {
            return 'leave';
        }

        if ($matched !== []) {
            return self::anySucceeded($matched) ? 'sent' : 'leave';
        }

        if ($callExists === false) {
            return 'redispatch';
        }

        return 'leave';
    }

    public static function terminalStatus(bool $faxSucceeded, bool $retriesLeft): string
    {
        if ($faxSucceeded) {
            return 'sent';
        }

        return $retriesLeft ? 'trying' : 'failed';
    }

    /**
     * uuid_exists reply, after ESL JSON decoding.
     * true = uuid is up, false = gone, null = the answer was unusable.
     */
    public static function uuidExists(mixed $reply): ?bool
    {
        if ($reply === null) {
            return null;
        }

        if ($reply === true || $reply === 1 || $reply === 1.0) {
            return true;
        }

        if ($reply === false || $reply === 0 || $reply === 0.0) {
            return false;
        }

        $text = strtolower(self::norm($reply));
        if ($text === '') {
            return null;
        }

        if ($text === 'true' || $text === '1') {
            return true;
        }

        if ($text === 'false' || $text === '0' || str_contains($text, 'false')) {
            return false;
        }

        return null;
    }

    /**
     * A txfax channel is over budget when its age is past the channel's own
     * fax_channel_budget plus the grace, or past the unknown-page ceiling
     * when that variable is missing. Age equal to the limit is still inside it.
     *
     * @param  array<string, mixed>  $channel
     */
    public static function overBudget(array $channel, int $nowEpoch): bool
    {
        if (! self::isTxfax($channel['application'] ?? '')) {
            return false;
        }

        $created = (int) ($channel['created_epoch'] ?? 0);
        if ($created <= 0 || $nowEpoch <= $created) {
            return false;
        }

        $age = $nowEpoch - $created;
        $budget = (int) ($channel['budget_seconds'] ?? 0);
        if ($budget > 0) {
            return $age > ($budget + self::OVER_BUDGET_GRACE_SECONDS);
        }

        return $age > self::UNKNOWN_PAGES_SECONDS;
    }

    /**
     * @param  array<string, mixed>  $channel
     */
    public static function alertBody(array $channel, string $node, string $status): string
    {
        $lines = [
            'Source: fspbx',
            'Node: '.self::oneLine($node),
            'Channel: '.self::norm($channel['uuid'] ?? ''),
            'Status: '.self::oneLine($status),
            'Condition: '.self::CONDITION,
            'Destination: '.self::oneLine($channel['destination'] ?? ''),
            'Age-seconds: '.(string) (int) ($channel['age_seconds'] ?? 0),
            'Fax-success: '.self::oneLine($channel['fax_success'] ?? ''),
            'Outbound-fax-uuid: '.self::norm($channel['outbound_fax_uuid'] ?? ''),
        ];

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<string>  $previous
     * @param  list<string>  $current
     * @return array{trigger: list<string>, resolve: list<string>}
     */
    public static function alertTransitions(array $previous, array $current): array
    {
        $previousIds = self::idList($previous);
        $currentIds = self::idList($current);

        return [
            'trigger' => array_values(array_diff($currentIds, $previousIds)),
            'resolve' => array_values(array_diff($previousIds, $currentIds)),
        ];
    }

    public static function norm(mixed $value): string
    {
        if ($value === null || is_array($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (floor($value) == $value) {
                return (string) (int) $value;
            }

            return trim((string) $value);
        }

        $text = trim((string) $value);
        if ($text === '' || $text === '_undef_' || str_starts_with($text, '-ERR')) {
            return '';
        }

        return $text;
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private static function idList(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            $norm = self::norm($id);
            if ($norm !== '') {
                $clean[] = $norm;
            }
        }

        return array_values(array_unique($clean));
    }

    private static function oneLine(mixed $value): string
    {
        return str_replace(["\r", "\n"], ' ', self::norm($value));
    }
}
