{{-- email-template
format: text
layout: none
--}}
Abandoned call{{ $attributes['caller_id_number'] ? ' from '.$attributes['caller_id_number'] : '' }}.

A caller left {{ $attributes['queue_display'] }} before an agent answered.

From: {{ $attributes['caller_display'] }}
Contact Center: {{ $attributes['queue_display'] }}
Reason: {{ $attributes['departure_reason'] }}
@if (!empty($attributes['wait_duration']))
Time waiting: {{ $attributes['wait_duration'] }}
@endif
Call ID: {{ $attributes['call_uuid'] }}

Thanks,
{{ config('app.name', 'FS PBX') }} Team
