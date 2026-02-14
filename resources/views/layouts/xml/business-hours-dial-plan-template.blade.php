@php
    use Carbon\Carbon;

    // 1) Group periods by identical start/end/action/target
    $groups = $businessHour->periods->groupBy(
        fn($p) => implode('|', [$p->start_time, $p->end_time, $p->action, $p->target_type, $p->target_id]),
    );

    // 2) Domain for transfer strings
    $domain = session('domain_name');

    // 3) Map each action type to the correct extension field on the morph target
    $fieldMap = [
        'extensions' => 'extension',
        'ring_groups' => 'ring_group_extension',
        'ivrs' => 'ivr_menu_extension',
        'time_conditions' => 'dialplan_number',
        'business_hours' => 'extension',
        'contact_centers' => 'queue_extension',
        'faxes' => 'fax_extension',
        'call_flows' => 'call_flow_extension',
        'recordings' => 'recording_filename',
        'voicemails' => 'voicemail_id',
    ];

/**
 * Build regexes for any time range (e.g., 2215–0110).
 * Returns an array of regexes for use in <condition> blocks.
 *
 * IMPORTANT: Zero-pad minutes so they match %H%M (e.g., '1005', not '105').
 */
$makeTimeRangeRegex = function ($start, $end) {
    $start = str_pad($start, 4, '0', STR_PAD_LEFT);
    $end   = str_pad($end,   4, '0', STR_PAD_LEFT);

    $startHour = intval(substr($start, 0, 2));
    $startMin  = intval(substr($start, 2, 2));
    $endHour   = intval(substr($end,   0, 2));
    $endMin    = intval(substr($end,   2, 2));

    $pad = fn($m) => sprintf('%02d', $m);
    $regexes = [];

    // Not overnight
    if ($start < $end) {
        for ($h = $startHour; $h <= $endHour; $h++) {
            if ($h == $startHour && $h == $endHour) {
                // Single-hour window
                $mins = implode('|', array_map($pad, range($startMin, $endMin)));
                $regexes[] = '^' . sprintf('%02d', $h) . '(' . $mins . ')$';
            } elseif ($h == $startHour) {
                $mins = implode('|', array_map($pad, range($startMin, 59)));
                $regexes[] = '^' . sprintf('%02d', $h) . '(' . $mins . ')$';
            } elseif ($h == $endHour) {
                $mins = implode('|', array_map($pad, range(0, $endMin)));
                $regexes[] = '^' . sprintf('%02d', $h) . '(' . $mins . ')$';
            } else {
                $regexes[] = '^' . sprintf('%02d', $h) . '([0-5][0-9])$';
            }
        }
    } else {
        // Overnight (spans midnight)
        // Late segment (startHour:startMin → 23:59)
        for ($h = $startHour; $h <= 23; $h++) {
            if ($h == $startHour) {
                $mins = implode('|', array_map($pad, range($startMin, 59)));
                $regexes[] = '^' . sprintf('%02d', $h) . '(' . $mins . ')$';
            } else {
                $regexes[] = '^' . sprintf('%02d', $h) . '([0-5][0-9])$';
            }
        }
        // Early segment (00:00 → endHour:endMin)
        for ($h = 0; $h <= $endHour; $h++) {
            if ($h == $endHour) {
                $mins = implode('|', array_map($pad, range(0, $endMin)));
                $regexes[] = '^' . sprintf('%02d', $h) . '(' . $mins . ')$';
            } else {
                $regexes[] = '^' . sprintf('%02d', $h) . '([0-5][0-9])$';
            }
        }
    }

    return $regexes;
};

@endphp

<extension name="{{ $businessHour->name }}" continue="false" uuid="{{ $businessHour->dialplan_uuid }}">

    {{-- only handle calls to this BH extension --}}
    <condition field="destination_number" expression="^{{ $businessHour->extension }}$" break="never"
        require-nested="true">
        {{-- tag the call with this BH UUID --}}
        <action application="set" data="business_hours={{ $businessHour->uuid }}" />
        {{-- set the correct timezone for all subsequent time-of-day checks --}}
        <action application="set" data="timezone={{ $businessHour->timezone }}" inline="true" />
        {{-- reset our “matched slot” flag --}}
        <action application="set" data="slot_matched=" inline="true" />



        {{-- 1) Holiday exceptions --}}
        @foreach ($businessHour->holidays as $h)
            @php
                // prepare destination
                $extField = $fieldMap[$h->action] ?? null;
                $extValue = $extField
                    ? ($h->target?->{$extField} ?? $businessHour->extension)
                    : $businessHour->extension;                
                $dest = buildDestinationAction([
                    'type' => $h->action,
                    'extension' => $extValue,
                ]);

                // time-of-day attr from cast Carbon instances
                $timeAttr = '';
                if ($h->start_time && $h->end_time) {
                    $tf = $h->start_time->format('H:i');
                    $tt = $h->end_time->format('H:i');
                    $timeAttr = " time-of-day=\"{$tf}-{$tt}\"";
                }

                // decide matching attributes
                $useAttrs = [];

                $tz = $businessHour->timezone;

                $strftimeConditions = [];

                switch ($h->holiday_type) {
                    case 'single_date':
                        // exact single-day match with year, month, and day
                        if ($h->start_date) {
                            // start_date is cast to Carbon
                            $dt = $h->start_date;
                            $useAttrs = [
                                'year' => $dt->format('Y'),
                                'mon' => $dt->format('n'),
                                'mday' => $dt->format('j'),
                            ];
                        }
                        break;

                    case 'date_range':
                        // If dates exist but times are missing, treat each day as a full-day holiday
                        if ($h->start_date && $h->end_date && (!$h->start_time || !$h->end_time)) {
                            $sd = $h->start_date->copy()->startOfDay();
                            $ed = $h->end_date->copy()->startOfDay();

                            $curr = $sd->copy();
                            while ($curr->lte($ed)) {
                                $strftimeConditions[] = [
                                    'date'       => $curr->format('Y-m-d'),
                                    // Match all times HHMM for the day
                                    'time_regex' => '^([0-1][0-9]|2[0-3])[0-5][0-9]$',
                                ];
                                $curr->addDay();
                            }
                        }
                        // If both dates and times exist, keep the precise range logic
                        elseif ($h->start_date && $h->end_date && $h->start_time && $h->end_time) {
                            $sd = $h->start_date;
                            $ed = $h->end_date;
                            $st = $h->start_time->format('Hi');
                            $et = $h->end_time->format('Hi');

                            if ($sd->isSameDay($ed)) {
                                // Single day (could be overnight, but dates match)
                                $regexes = $makeTimeRangeRegex($st, $et);
                                foreach ($regexes as $regex) {
                                    $strftimeConditions[] = [
                                        'date'       => $sd->format('Y-m-d'),
                                        'time_regex' => $regex,
                                    ];
                                }
                            } else {
                                // Multi-day or overnight across days
                                // First day (start_time → 23:59)
                                $regexes = $makeTimeRangeRegex($st, '2359');
                                foreach ($regexes as $regex) {
                                    $strftimeConditions[] = [
                                        'date'       => $sd->format('Y-m-d'),
                                        'time_regex' => $regex,
                                    ];
                                }

                                // Middle days (full 24 hours)
                                $curr = $sd->copy()->addDay();
                                while ($curr->lt($ed)) {
                                    $strftimeConditions[] = [
                                        'date'       => $curr->format('Y-m-d'),
                                        'time_regex' => '^([0-1][0-9]|2[0-3])[0-5][0-9]$',
                                    ];
                                    $curr->addDay();
                                }

                                // Last day (00:00 → end_time)
                                $regexes = $makeTimeRangeRegex('0000', $et);
                                foreach ($regexes as $regex) {
                                    $strftimeConditions[] = [
                                        'date'       => $ed->format('Y-m-d'),
                                        'time_regex' => $regex,
                                    ];
                                }
                            }
                        }
                        break;

                    case 'us_holiday':
                    case 'ca_holiday':
                        // 1) a fixed calendar date (e.g. January 1)
                        if (!empty($h->mon) && !empty($h->mday) && empty($h->wday) && empty($h->mweek)) {
                            $useAttrs = [
                                'mon' => $h->mon,
                                'mday' => $h->mday,
                            ];
                        }
                        // 2) a day-of-month range with an optional weekday (e.g. 15-21st, any Monday within that)
                        elseif (!empty($h->mon) && !empty($h->mday) && !empty($h->wday)) {
                            $useAttrs = [
                                'mon' => $h->mon,
                                'mday' => $h->mday,
                                'wday' => $h->wday,
                            ];
                        }
                        // 3) the “nth weekday of the month” pattern (e.g. 3rd Monday)
                        elseif (!empty($h->mon) && !empty($h->wday) && !empty($h->mweek)) {
                            $useAttrs = [
                                'mon' => $h->mon,
                                'wday' => $h->wday,
                                'mweek' => $h->mweek,
                            ];
                        }
                        break;

                    case 'recurring_pattern':
                        // any combination of year, mon, week, wday, mweek, mday for recurring patterns
                        foreach (['year', 'mon', 'week', 'wday', 'mweek', 'mday'] as $f) {
                            if (!empty($h->{$f})) {
                                $useAttrs[$f] = $h->{$f};
                            }
                        }
                        break;
                }
            @endphp


            @if (count($strftimeConditions))
                @foreach ($strftimeConditions as $c)
                    <condition field="${strftime_tz({{ $tz }} %Y-%m-%d)}" expression="^{{ $c['date'] }}$"
                        break="never">
                        <condition field="${strftime_tz({{ $tz }} %H%M)}" expression="{{ $c['time_regex'] }}"
                            break="on-true">
                            <action application="set" data="slot_matched=1" inline="true"/>
                            <action application="{{ $dest['destination_app'] }}"
                                data="{{ $dest['destination_data'] }}" />
                        </condition>
                    </condition>
                @endforeach
            @elseif (count($useAttrs ?? []))
                {{-- attribute-based condition --}}
                <condition
                    @foreach ($useAttrs as $attr => $val)
                {{ $attr }}="{{ $val }}" @endforeach
                    {!! $timeAttr !!} break="on-true">
                    <action application="set" data="slot_matched=1" inline="true"/>
                    <action application="{{ $dest['destination_app'] }}" data="{{ $dest['destination_data'] }}" />
                </condition>
            @endif
        @endforeach




        {{-- business-hours time slots --}}
        @foreach ($groups as $group)
            @php
                $first = $group->first();
                $tz = $businessHour->timezone;

                // collect weekdays (1=Mon … 7=Sun)
                $days = $group->pluck('day_of_week')->unique()->sort()->implode(',');

                // parse DB times (already local) in the BH timezone
                $start = Carbon::createFromFormat('H:i:s', $first->start_time, $tz);
                $end = Carbon::createFromFormat('H:i:s', $first->end_time, $tz);

                // pick the correct extension off the morph target
                $extField = $fieldMap[$first->action] ?? null;
                $extValue = $extField ? $first->target->{$extField} : null;

                // build the FS action via your helper
                $dest = buildDestinationAction([
                    'type' => $first->action,
                    'extension' => $extValue,
                ]);
                $destApp = $dest['destination_app'];
                $destData = $dest['destination_data'];
            @endphp

            {{-- on matching weekday & local time, break after first true --}}
            <condition wday="{{ $days }}" time-of-day="{{ $start->format('H:i') }}-{{ $end->format('H:i') }}"
                break="on-true">
                {{-- mark that a slot matched --}}
                <action application="set" data="slot_matched=1" inline="true"/>
                <action application="{{ $destApp }}" data="{{ $destData }}" />
            </condition>
        @endforeach

        {{-- after-hours fallback only if no slot matched --}}
        <condition field="${slot_matched}" expression="^$">
            @php
                $afterAction = $businessHour->after_hours_action;
                $afterTarget = $businessHour->after_hours_target;
            @endphp

            @if ($afterAction === 'hangup')
                <action application="hangup" data="" />
            @elseif ($afterAction === 'check_voicemail')
                <action application="transfer" data="*98 XML {{ $domain }}" />
            @elseif ($afterAction === 'company_directory')
                <action application="transfer" data="*411 XML {{ $domain }}" />
            @elseif ($afterAction && $afterTarget)
                @php
                    $field = $fieldMap[$afterAction] ?? null;
                    $ext = $field ? $afterTarget->{$field} : $businessHour->extension;
                    $dest = buildDestinationAction([
                        'type' => $afterAction,
                        'extension' => $ext,
                    ]);
                @endphp
                <action application="{{ $dest['destination_app'] }}" data="{{ $dest['destination_data'] }}" />
            @endif
        </condition>

    </condition>
</extension>
