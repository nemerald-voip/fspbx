{{ $attributes['intro'] ?? 'An AI Receptionist handoff needs attention.' }}

@if(!empty($attributes['route_name']))
Route: {{ $attributes['route_name'] }}
@endif
@if(!empty($attributes['caller_name']))
Caller: {{ $attributes['caller_name'] }}
@endif
@if(!empty($attributes['caller_number']))
Callback Number: {{ $attributes['caller_number'] }}
@endif
@if(!empty($attributes['urgency']))
Urgency: {{ $attributes['urgency'] }}
@endif
@if(!empty($attributes['failure_status']))
Transfer Status: {{ $attributes['failure_status'] }}
@endif

@if(!empty($attributes['message']))
Message:
{{ $attributes['message'] }}
@endif

@if(!empty($attributes['handoff_summary']))
AI Summary:
{{ $attributes['handoff_summary'] }}
@endif

@if(!empty($attributes['transcript']))
Call Transcript:
{{ $attributes['transcript'] }}
@endif
