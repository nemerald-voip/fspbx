<?php

namespace App\Services\CallFlow;

use App\Models\BusinessHour;
use App\Models\BusinessHourHoliday;
use App\Models\BusinessHourPeriod;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Decides whether a BusinessHour set is "in hours" at a given moment.
 *
 * Returns the specific period that is active (day, times) so the simulator can
 * label the `in_hours` branch with the matching schedule row, or null if the
 * time falls outside all periods (i.e. after-hours / holiday).
 */
class BusinessHoursEvaluator
{
    /**
     * @return array{
     *     period: ?BusinessHourPeriod,
     *     holiday: ?BusinessHourHoliday,
     *     is_in_hours: bool,
     *     timezone: string,
     *     warnings: array<int, string>
     * }
     */
    public function evaluate(BusinessHour $businessHour, DateTimeImmutable $at): array
    {
        $timezone = $businessHour->timezone ?: 'UTC';
        $local = $at->setTimezone(new DateTimeZone($timezone));

        $warnings = [];

        $holiday = $this->matchingHoliday($businessHour, $local, $warnings);
        if ($holiday !== null) {
            return [
                'period' => null,
                'holiday' => $holiday,
                'is_in_hours' => false,
                'timezone' => $timezone,
                'warnings' => $warnings,
            ];
        }

        $period = $this->matchingPeriod($businessHour, $local);

        return [
            'period' => $period,
            'holiday' => null,
            'is_in_hours' => $period !== null,
            'timezone' => $timezone,
            'warnings' => $warnings,
        ];
    }

    private function matchingPeriod(BusinessHour $businessHour, DateTimeImmutable $local): ?BusinessHourPeriod
    {
        // FreeSWITCH / BusinessHour convention: 1=Sun…7=Sat. PHP `w` is 0=Sun…6=Sat.
        $fsDow = ((int) $local->format('w')) + 1;
        $nowSec = $this->secondsSinceMidnight($local);

        foreach ($businessHour->periods as $period) {
            if ((int) $period->day_of_week !== $fsDow) {
                continue;
            }
            $start = $this->timeStringToSeconds((string) $period->start_time);
            $end = $this->timeStringToSeconds((string) $period->end_time);
            if ($start === null || $end === null) {
                continue;
            }
            if ($start <= $end) {
                if ($nowSec >= $start && $nowSec < $end) {
                    return $period;
                }
            } else {
                // Overnight period (e.g. 22:00–02:00)
                if ($nowSec >= $start || $nowSec < $end) {
                    return $period;
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $warnings
     */
    private function matchingHoliday(BusinessHour $businessHour, DateTimeImmutable $local, array &$warnings): ?BusinessHourHoliday
    {
        foreach ($businessHour->holidays as $holiday) {
            $type = (string) $holiday->holiday_type;

            if ($type === 'single_date' && $holiday->start_date !== null) {
                if ($holiday->start_date->format('Y-m-d') === $local->format('Y-m-d')
                    && $this->holidayTimeCovers($holiday, $local)) {
                    return $holiday;
                }
                continue;
            }

            if ($type === 'date_range' && $holiday->start_date !== null && $holiday->end_date !== null) {
                $today = $local->format('Y-m-d');
                if ($today >= $holiday->start_date->format('Y-m-d')
                    && $today <= $holiday->end_date->format('Y-m-d')
                    && $this->holidayTimeCovers($holiday, $local)) {
                    return $holiday;
                }
                continue;
            }

            // Recurring patterns (us_holiday, recurring_pattern) aren't evaluated
            // in v1 — flag once so the caller can surface it.
            $warnings[] = sprintf(
                'business-hours holiday "%s" (type=%s) uses a recurring pattern that is not evaluated by the simulator',
                (string) ($holiday->description ?: $holiday->uuid),
                $type,
            );
        }

        return null;
    }

    private function holidayTimeCovers(BusinessHourHoliday $holiday, DateTimeImmutable $local): bool
    {
        if (empty($holiday->start_time) || empty($holiday->end_time)) {
            return true; // whole-day holiday
        }
        $nowSec = $this->secondsSinceMidnight($local);
        $start = $this->timeStringToSeconds($holiday->start_time instanceof \DateTimeInterface
            ? $holiday->start_time->format('H:i:s')
            : (string) $holiday->start_time);
        $end = $this->timeStringToSeconds($holiday->end_time instanceof \DateTimeInterface
            ? $holiday->end_time->format('H:i:s')
            : (string) $holiday->end_time);
        if ($start === null || $end === null) {
            return true;
        }
        return $nowSec >= $start && $nowSec < $end;
    }

    private function secondsSinceMidnight(DateTimeImmutable $local): int
    {
        return ((int) $local->format('H')) * 3600
            + ((int) $local->format('i')) * 60
            + (int) $local->format('s');
    }

    private function timeStringToSeconds(string $time): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $m)) {
            return null;
        }
        return ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + ((int) ($m[3] ?? 0));
    }
}
