<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Traits\TraitUuid;
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
     * A human-friendly description of where calls go.
     */
    public function getTargetLabelAttribute(): string
    {
        if (! $this->target) {
            // built-in actions without a target
            switch ($this->action) {
                case 'hangup':
                    return 'Hang Up';
                case 'company_directory':
                    return 'Company Directory';
                case 'check_voicemail':
                    return 'Check Voicemail';
                    // add any other action-only cases here…
                default:
                    return '—';
            }
        }

        switch (class_basename($this->target_type)) {
            case 'Extensions':
                // e.g. “Extension: 100 — Alice Johnson”
                return sprintf(
                    'Extension: %s - %s',
                    $this->target->extension,
                    $this->target->directory_first_name . ' ' . $this->target->directory_last_name
                );

            case 'Voicemails':
                // “Voicemail: 100 - Reception”
                $ext = $this->target->extension;
                if ($ext) {
                    $label =  $ext->directory_first_name . ' ' . $ext->directory_last_name;
                } else {
                    $label = "Team Voicemail";
                }
                return sprintf(
                    'Voicemail: %s - %s',
                    $this->target->voicemail_id,
                    $label
                );

            case 'RingGroups':
                // “Ring Group 0600 — Sales Team”
                return sprintf(
                    'Ring Group: %s - %s',
                    $this->target->ring_group_extension,
                    $this->target->ring_group_name
                );

            case 'IvrMenus':
                // “Virtual Receptionist: 5000 - Main Menu”
                return sprintf(
                    'Virtual Receptionist: %s - %s',
                    $this->target->ivr_menu_extension,
                    $this->target->ivr_menu_name
                );

            case 'Recordings':
                // “Recording: promo.mp3”
                logger($this->target);
                return 'Play Greeting: ' . $this->target->recording_name;

            case 'Dialplans':
                // “Schedule 9300 — Main Business Hours””
                return sprintf(
                    'Schedule: %s - %s',
                    $this->target->dialplan_number,
                    $this->target->dialplan_name
                );

            case 'CallCenterQueues':
                // Contact Center: 9600 — Sales Queue””
                return sprintf(
                    'Contact Center: %s - %s',
                    $this->target->queue_extension,
                    $this->target->queue_name
                );

            case 'Faxes':
                // Fax: 50000 — Main Fax””
                return sprintf(
                    'Fax: %s - %s',
                    $this->target->fax_extension,
                    $this->target->fax_name
                );

            case 'CallFlows':
                // Call Flow: 300 — Night Mode””
                return sprintf(
                    'Call Flow: %s - %s',
                    $this->target->call_flow_extension,
                    $this->target->call_flow_name
                );

            default:
                // fallback 
                return 'invalid action';
        }
    }

    /**
     * Get a human-readable “date / recurrence” string.
     */
    public function getHumanDateAttribute(): string
    {
        switch ($this->holiday_type) {
            case 'us_holiday':
                $next = $this->getNextUsHolidayDate()->format('F j, Y');
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
