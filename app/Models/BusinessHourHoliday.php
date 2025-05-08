<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessHourHoliday extends Model
{
    use HasFactory, TraitUuid;

    protected $table = 'business_hour_holidays';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'business_hour_uuid',
        'holiday_type',
        'description',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'mon',
        'wday',
        'mweek',
        'week',
        'mday',
        'action',
        'target_type',
        'target_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'start_time' => 'string',
        'end_time'   => 'string',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    // automatically include this in toArray()/toJson()
    protected $appends = [
        'human_date',
    ];

    /**
     * The parent business hour definition.
     */
    public function businessHour(): BelongsTo
    {
        return $this->belongsTo(BusinessHour::class, 'business_hour_uuid', 'uuid');
    }


    /**
     * Get a human-readable “date / recurrence” string.
     */
    public function getHumanDateAttribute(): string
    {
        switch ($this->holiday_type) {
            case 'us_holiday':
                // Compute the next date
                $next = $this->getNextUsHolidayDate()->format('F j, Y');
                // Always note it repeats annually, then show “next”
                return "Every year (next: {$next})";

            case 'single_date':
                $d = $this->start_date->format('F j, Y');
                if ($this->start_time && $this->end_time) {
                    $d .= ' ' . substr($this->start_time, 0, 5)
                        . '–' . substr($this->end_time, 0, 5);
                }
                return $d;

            case 'date_range':
                // If both times are set, show full datetime span.
                if ($this->start_time && $this->end_time) {
                    $from = $this->start_date->format('F j, Y') . ' ' . substr($this->start_time, 0, 5);
                    $to   = $this->end_date->format('F j, Y') . ' ' . substr($this->end_time,   0, 5);
                    return "{$from} – {$to}";
                }

                // Fallback: just dates
                $fromDate = $this->start_date->format('F j, Y');
                $toDate   = $this->end_date->format('F j, Y');
                return "{$fromDate} – {$toDate}";

            case 'recurring_pattern':
                // 0) Week-of-year (1–53)
                if ($this->week !== null) {
                    return $this->ordinal($this->week) . ' week of every year';
                }

                // 1) “Xth day of every month”
                if ($this->mday !== null) {
                    return $this->ordinal($this->mday) . ' day of every month';
                }

                // 2) Nth (or last) weekday
                $parts = [];

                // Nth vs Last
                if ($this->mweek !== null) {
                    $parts[] = $this->mweek === 5
                        ? 'Last'
                        : $this->ordinal($this->mweek);
                }

                // Day name
                $days = [
                    1 => 'Sunday',
                    2 => 'Monday',
                    3 => 'Tuesday',
                    4 => 'Wednesday',
                    5 => 'Thursday',
                    6 => 'Friday',
                    7 => 'Saturday'
                ];
                if ($this->wday !== null && isset($days[$this->wday])) {
                    $parts[] = $days[$this->wday];
                }

                // Frequency
                if ($this->mon !== null) {
                    $monthName = Carbon::create()->month($this->mon)->format('F');
                    $freq = "in {$monthName} of every year";
                } else {
                    $freq = 'of every month';
                }

                return implode(' ', $parts) . " {$freq}";

            default:
                return (string) $this->description;
        }
    }

    /**
     * Turn any integer N into “Nth” with correct English suffix.
     */
    protected function ordinal(int $n): string
    {
        $suffix = 'th';
        if ($n % 100 < 11 || $n % 100 > 13) {
            switch ($n % 10) {
                case 1:
                    $suffix = 'st';
                    break;
                case 2:
                    $suffix = 'nd';
                    break;
                case 3:
                    $suffix = 'rd';
                    break;
            }
        }
        return "{$n}{$suffix}";
    }

    /**
     * Compute the next occurrence of a US holiday, handling:
     *  - fixed dates (mday numeric)
     *  - weekday-in-range patterns ("15-21" + wday)
     *  - Nth/last weekday (mweek)
     */
    protected function getNextUsHolidayDate(int $year = null): Carbon
    {
        $today = Carbon::today();
        $year  = $year ?: $today->year;

        // 1) FIXED-DATE (only when mday is a simple integer)
        if ($this->mday !== null && preg_match('/^\d+$/', $this->mday)) {
            $cand = Carbon::create($year, $this->mon, (int) $this->mday);
            return $cand->lt($today)
                ? $cand->addYear()
                : $cand;
        }

        // 2) RANGE + WEEKDAY PATTERN (e.g. “15-21” + monday)
        if ($this->wday && preg_match('/^(\d+)-(\d+)$/', $this->mday, $m)) {
            [$min, $max] = [(int)$m[1], (int)$m[2]];

            // map FS wday (1=Sun…7=Sat) → ISO day (Mon=1…Sun=7)
            $targetIso = $this->wday === 1
                ? 7
                : $this->wday - 1;

            for ($day = $min; $day <= $max; $day++) {
                // skip invalid days (e.g. April 31)
                try {
                    $d = Carbon::create($year, $this->mon, $day);
                } catch (\Exception $e) {
                    continue;
                }
                if ($d->dayOfWeekIso === $targetIso) {
                    return $d->lt($today)
                        ? $this->getNextUsHolidayDate($year + 1)
                        : $d;
                }
            }
        }

        // 3) Nth or LAST weekday (mweek set)
        return $this->computeNthWeekdayInMonth($year, $today);
    }

    /**
     * Your existing nth/last-weekday helper.
     */
    private function computeNthWeekdayInMonth(int $year, Carbon $today): Carbon
    {
        $month   = $this->mon;
        $wdayFs  = $this->wday;   // 1=Sun…7=Sat
        $targetW = $wdayFs - 1;   // Carbon::create’s dayOfWeek (0=Sun…6=Sat)
        $ordinal = $this->mweek;  // 1–4 or 5=last
        $base    = Carbon::create($year, $month, 1);

        if ($ordinal === 5) {
            // last weekday of the month
            $d     = $base->copy()->endOfMonth();
            $delta = ($d->dayOfWeek - $targetW + 7) % 7;
            $d->subDays($delta);
        } else {
            // Nth weekday: find first, then add (N-1) weeks
            $firstDelta = ($targetW - $base->dayOfWeek + 7) % 7;
            $d = $base->addDays($firstDelta)->addWeeks($ordinal - 1);
        }

        return $d->lt($today)
            ? $this->computeNthWeekdayInMonth($year + 1, $today)
            : $d;
    }
}
