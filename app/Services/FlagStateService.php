<?php

namespace App\Services;

use App\Models\Flag;
use App\Models\FlagRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Decides whether a flag is active.
 *
 * FreeSWITCH makes the call-time decision in lua/flag_check.lua. This class
 * mirrors that logic for the UI, so keep the two in step.
 */
class FlagStateService
{
    public function isActive(Flag $flag, ?CarbonInterface $now = null): bool
    {
        if (!$flag->enabled) {
            return false;
        }

        if ($flag->isManual()) {
            return (bool) $flag->active;
        }

        $timezone = $flag->timezone ?: get_local_time_zone($flag->domain_uuid);
        $now = Carbon::instance($now ?? now())->setTimezone($timezone);
        $rules = $flag->relationLoaded('rules') ? $flag->rules : $flag->rules()->get();

        return $this->rulesMatch($rules->all(), $now);
    }

    /**
     * @param  array<int, FlagRule|array>  $rules
     */
    public function rulesMatch(array $rules, CarbonInterface $localNow): bool
    {
        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $localNow)) {
                return true;
            }
        }

        return false;
    }

    public function ruleMatches(FlagRule|array $rule, CarbonInterface $localNow): bool
    {
        $rule = $rule instanceof FlagRule ? $rule->getAttributes() : $rule;

        return match ($rule['rule_type'] ?? null) {
            FlagRule::TYPE_WEEKLY => $this->weeklyMatches($rule, $localNow),
            FlagRule::TYPE_DATE_RANGE => $this->dateRangeMatches($rule, $localNow),
            default => false,
        };
    }

    /**
     * Active from start_time up to (not including) end_time on each selected
     * weekday. An end time earlier than the start time runs past midnight.
     */
    private function weeklyMatches(array $rule, CarbonInterface $localNow): bool
    {
        $days = self::parseWeekdays($rule['weekdays'] ?? '');
        $start = self::hhmm($rule['start_time'] ?? null);
        $end = self::hhmm($rule['end_time'] ?? null);

        if (empty($days) || $start === null || $end === null || $start === $end) {
            return false;
        }

        $now = $localNow->format('H:i');
        $today = self::freeswitchWeekday($localNow);
        $yesterday = $today === 1 ? 7 : $today - 1;

        if ($start < $end) {
            return in_array($today, $days, true) && $now >= $start && $now < $end;
        }

        return (in_array($today, $days, true) && $now >= $start)
            || (in_array($yesterday, $days, true) && $now < $end);
    }

    /**
     * Active continuously from start_date start_time (or midnight) through
     * end_date end_time (or the end of that day).
     */
    private function dateRangeMatches(array $rule, CarbonInterface $localNow): bool
    {
        $startDate = self::ymd($rule['start_date'] ?? null);
        $endDate = self::ymd($rule['end_date'] ?? null) ?? $startDate;

        if ($startDate === null) {
            return false;
        }

        $from = $startDate . ' ' . (self::hhmm($rule['start_time'] ?? null) ?? '00:00');
        $now = $localNow->format('Y-m-d H:i');

        $endTime = self::hhmm($rule['end_time'] ?? null);
        if ($endTime === null) {
            return $now >= $from && $now <= $endDate . ' 23:59';
        }

        return $now >= $from && $now < $endDate . ' ' . $endTime;
    }

    /**
     * FreeSWITCH wday numbering: 1=Sunday ... 7=Saturday.
     */
    public static function freeswitchWeekday(CarbonInterface $date): int
    {
        return $date->dayOfWeek + 1;
    }

    public static function parseWeekdays(?string $weekdays): array
    {
        return collect(explode(',', (string) $weekdays))
            ->map(fn ($day) => (int) trim($day))
            ->filter(fn ($day) => $day >= 1 && $day <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private static function hhmm(mixed $time): ?string
    {
        if (blank($time)) {
            return null;
        }

        return substr((string) $time, 0, 5);
    }

    private static function ymd(mixed $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        return $date instanceof CarbonInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);
    }
}
