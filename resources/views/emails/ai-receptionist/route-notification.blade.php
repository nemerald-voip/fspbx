<p>{{ $attributes['intro'] ?? 'An AI Receptionist handoff needs attention.' }}</p>

<table cellpadding="6" cellspacing="0" border="0">
    @if(!empty($attributes['route_name']))
        <tr>
            <td><strong>Route</strong></td>
            <td>{{ $attributes['route_name'] }}</td>
        </tr>
    @endif
    @if(!empty($attributes['caller_name']))
        <tr>
            <td><strong>Caller</strong></td>
            <td>{{ $attributes['caller_name'] }}</td>
        </tr>
    @endif
    @if(!empty($attributes['caller_number']))
        <tr>
            <td><strong>Callback Number</strong></td>
            <td>{{ $attributes['caller_number'] }}</td>
        </tr>
    @endif
    @if(!empty($attributes['urgency']))
        <tr>
            <td><strong>Urgency</strong></td>
            <td>{{ $attributes['urgency'] }}</td>
        </tr>
    @endif
    @if(!empty($attributes['failure_status']))
        <tr>
            <td><strong>Transfer Status</strong></td>
            <td>{{ $attributes['failure_status'] }}</td>
        </tr>
    @endif
</table>

@if(!empty($attributes['message']))
    <h3>Message</h3>
    <p>{!! nl2br(e($attributes['message'])) !!}</p>
@endif

@if(!empty($attributes['handoff_summary']))
    <h3>AI Summary</h3>
    <p>{!! nl2br(e($attributes['handoff_summary'])) !!}</p>
@endif

@if(!empty($attributes['transcript']))
    <h3>Call Transcript</h3>
    <p style="white-space: pre-wrap;">{{ $attributes['transcript'] }}</p>
@endif
