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
        'contact_centers' => 'queue_extension',
        'faxes' => 'fax_extension',
        'call_flows' => 'call_flow_extension',
        'recordings' => 'recording_filename',
        'voicemails' => 'voicemail_id',
    ];
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
        <action application="set" data="slot_matched=" />



        {{-- 1) Holiday exceptions --}}
        @foreach ($businessHour->holidays as $h)
            @php
                // prepare destination
                $extField = $fieldMap[$h->action] ?? null;
                $extValue = $extField ? $h->target->{$extField} : $businessHour->extension;
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
                        // date-range can be timed or full-day
                        if ($h->start_date && $h->end_date) {
                            if ($h->start_time && $h->end_time) {
                                // use FreeSWITCH date-time range, combine date + time from separate fields
                                $startDT = $h->start_date->format('Y-m-d') . ' ' . $h->start_time->format('H:i');
                                $endDT = $h->end_date->format('Y-m-d') . ' ' . $h->end_time->format('H:i');
                                $useAttrs = ['date-time' => "$startDT~$endDT"];
                                $timeAttr = '';
                            } else {
                                // full-day range via year, mon, mday-range
                                $sd = $h->start_date;
                                $ed = $h->end_date;
                                $useAttrs = [
                                    'year' => $sd->format('Y'),
                                    'mon' => $sd->format('n'),
                                    'mday' => $sd->format('j') . '-' . $ed->format('j'),
                                ];
                            }
                        }
                        break;

                    case 'us_holiday':
                        // month + weekday + day-range (e.g. 15-21) & optional weekday
                        if ($h->mon && $h->mday && $h->wday) {
                            $useAttrs = [
                                'mon' => $h->mon,
                                'wday' => $h->wday,
                                'mday' => $h->mday,
                            ];
                        }
                        // or nth-weekday of month (e.g. 3rd Monday)
                        elseif ($h->mon && $h->wday && $h->mweek) {
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

            @if (count($useAttrs))
                {{-- attribute-based condition --}}
                <condition
                    @foreach ($useAttrs as $attr => $val)
                {{ $attr }}="{{ $val }}" @endforeach
                    {!! $timeAttr !!} break="on-true">
                    <action application="set" data="slot_matched=1" />
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
                $extValue = $extField ? $first->target->{$extField} : $businessHour->extension;

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
                <action application="set" data="slot_matched=1" />
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
