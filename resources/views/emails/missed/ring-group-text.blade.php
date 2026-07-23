{{-- email-template
format: text
layout: none
--}}
Missed call{{ $attributes['caller_id_number'] ? ' from ' . $attributes['caller_id_number'] : '' }}.

A call to {{ $attributes['ring_group_display'] ?: 'your ring group' }} was not answered.

From: {{ $attributes['caller_display'] ?: 'Unknown caller' }}
To: {{ $attributes['ring_group_display'] ?: 'Ring group' }}
@if (!empty($attributes['destination_number']))
Dialed: {{ $attributes['destination_number'] }}
@endif

Thanks,
{{ config('app.name', 'FS PBX') }} Team
