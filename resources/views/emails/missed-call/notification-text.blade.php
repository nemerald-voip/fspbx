@php
    $callerName = $attributes['caller_id_name'] ?? '';
    $callerNumber = $attributes['caller_id_number'] ?? '';
    $caller = trim($callerName . ($callerNumber ? ' <' . $callerNumber . '>' : ''));
    $ringGroup = trim(($attributes['ring_group_name'] ?? '') . (($attributes['ring_group_extension'] ?? '') ? ' ext ' . $attributes['ring_group_extension'] : ''));
@endphp

Missed call{{ $callerNumber ? ' from ' . $callerNumber : '' }}.

A call to {{ $ringGroup ?: 'your ring group' }} was not answered.

From: {{ $caller ?: 'Unknown caller' }}
To: {{ $ringGroup ?: 'Ring group' }}
@if (!empty($attributes['destination_number']))
Dialed: {{ $attributes['destination_number'] }}
@endif

Thanks,
{{ config('app.name', 'FS PBX') }} Team
