<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Traits\TraitUuid;
use App\Services\CallRoutingOptionsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
        'start_time' => 'datetime:H:i',
        'end_time'   => 'datetime:H:i',
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
     * The polymorphic target (Extension, Voicemail, IvrMenu, etc).
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Use the same destination lookup and summary formatting as Phone Numbers.
     */
    public function routingSummary(): array
    {
        $domainUuid = $this->businessHour?->domain_uuid;
        if (! $domainUuid) {
            return ['name' => null, 'type' => null, 'invalid' => true];
        }

        return (new CallRoutingOptionsService($domainUuid))->summarizeTarget($this->action, $this->target_id);
    }

    /**
     * Get a human-readable “date / recurrence” string.
     */
    public function getHumanDateAttribute(): string
    {
        switch ($this->holiday_type) {
            case 'us_holiday':
            case 'ca_holiday':
            case 'uk_holiday':
                $next = $this->getNextPresetHolidayDate()->format('F j, Y');
                return "Every year (next: {$next})";

            case 'single_date':
                $date = $this->start_date->format('F j, Y');
                if ($this->start_time && $this->end_time) {
                    $from = $this->start_time instanceof Carbon
                        ? $this->start_time->format('H:i')
                        : $this->start_time;
                    $to   = $this->end_time   instanceof Carbon
                        ? $this->end_time->format('H:i')
                        : $this->end_time;
                    $date .= " {$from}–{$to}";
                }
                return $date;

            case 'date_range':
                // If both times are set, show the full span
                if ($this->start_time && $this->end_time) {
                    $fromDate = $this->start_date->format('F j, Y');
                    $toDate   = $this->end_date->format('F j, Y');
                    $fromTime = $this->start_time instanceof Carbon
                        ? $this->start_time->format('H:i')
                        : $this->start_time;
                    $toTime   = $this->end_time   instanceof Carbon
                        ? $this->end_time->format('H:i')
                        : $this->end_time;

                    return "{$fromDate} {$fromTime} – {$toDate} {$toTime}";
                }

                // Otherwise just dates
                return "{$this->start_date->format('F j, Y')} – {$this->end_date->format('F j, Y')}";


            case 'recurring_pattern':

                // helper maps
                $days = [
                    1 => 'Sunday',
                    2 => 'Monday',
                    3 => 'Tuesday',
                    4 => 'Wednesday',
                    5 => 'Thursday',
                    6 => 'Friday',
                    7 => 'Saturday',
                ];
                $monthName = $this->mon
                    ? Carbon::create()->month($this->mon)->format('F')
                    : null;

                // 1) Week-of-year (1–53)
                if ($this->week !== null) {
                    $recurrence = $this->ordinal($this->week) . ' week of every year';
                }
                // 2) Day-of-month + weekday **every** month
                elseif ($this->mday !== null && $this->wday !== null && $this->mon === null) {
                    $recurrence = $this->ordinal($this->mday)
                        . ' day of every month on '
                        . $days[$this->wday];
                }
                // 3) Exact date each year (month + day, with optional weekday)
                elseif ($this->mon !== null && $this->mday !== null) {
                    $recurrence = "{$this->ordinal($this->mday)} day of {$monthName} of every year";
                    if (isset($days[$this->wday])) {
                        $recurrence .= " on {$days[$this->wday]}";
                    }
                }
                // 4) Day-of-month every month
                elseif ($this->mday !== null) {
                    $recurrence = $this->ordinal($this->mday) . ' day of every month';
                }
                // 5) Nth (or Last) weekday [in month or in a specific month]
                elseif ($this->mweek !== null && isset($days[$this->wday])) {
                    $prefix  = (int) $this->mweek === 6
                        ? 'Last'
                        : $this->ordinal((int) $this->mweek);
                    $weekday = $days[$this->wday];
                    if ($this->mon !== null) {
                        $recurrence = "{$prefix} {$weekday} in {$monthName} of every year";
                    } else {
                        $recurrence = "{$prefix} {$weekday} of every month";
                    }
                }
                // 6) Every <weekday> [in month or every month]
                elseif (isset($days[$this->wday])) {
                    $weekday = $days[$this->wday];
                    if ($this->mon !== null) {
                        $recurrence = "{$weekday} in {$monthName} of every year";
                    } else {
                        $recurrence = "{$weekday} of every month";
                    }
                }
                // 7) Month only (e.g. “Every May”)
                elseif ($this->mon !== null) {
                    $recurrence = "Every {$monthName}";
                }
                // 8) Fallback to description
                else {
                    $recurrence = (string) $this->description;
                }

                // append time span if both times are set
                if ($this->start_time && $this->end_time) {
                    $from = $this->start_time instanceof Carbon
                        ? $this->start_time->format('H:i')
                        : $this->start_time;
                    $to   = $this->end_time   instanceof Carbon
                        ? $this->end_time->format('H:i')
                        : $this->end_time;
                    $recurrence .= " ({$from}–{$to})";
                }

                return $recurrence;



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
     * Compute the next occurrence of a preset holiday, handling:
     *  - fixed dates (mday numeric)
     *  - weekday-in-range patterns ("15-21" + wday)
     *  - Nth/last weekday (mweek)
     */
    protected function getNextPresetHolidayDate(int $year = null): Carbon
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
            $wday = (int) $this->wday;
            $targetIso = $wday === 1
                ? 7
                : $wday - 1;

            for ($day = $min; $day <= $max; $day++) {
                // skip invalid days (e.g. April 31)
                try {
                    $d = Carbon::create($year, $this->mon, $day);
                } catch (\Exception $e) {
                    continue;
                }
                if ($d->dayOfWeekIso === $targetIso) {
                    return $d->lt($today)
                        ? $this->getNextPresetHolidayDate($year + 1)
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
        $wdayFs  = (int) $this->wday;   // 1=Sun…7=Sat
        $targetW = $wdayFs - 1;   // Carbon::create’s dayOfWeek (0=Sun…6=Sat)
        $ordinal = (int) $this->mweek;  // 1–5 or 6=last
        $base    = Carbon::create($year, $month, 1);

        if ($ordinal === 6) {
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
