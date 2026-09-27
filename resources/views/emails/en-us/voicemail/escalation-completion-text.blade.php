{{-- email-template
format: text
layout: none
--}}
Voicemail Escalation {{ $statusLabel }}

A voicemail escalation for mailbox {{ $notification->mailbox ?? 'Unknown' }} has completed with status {{ $statusLabel }}.

Mailbox: {{ $notification->mailbox ?? '—' }}
Status: {{ $statusLabel }}
Caller ID Name: {{ $notification->caller_id_name ?? '—' }}
Caller ID Number: {{ $notification->caller_id_number ?? '—' }}
Message Length: {{ $notification->message_length_seconds ?? '—' }} seconds
Left At: {{ optional($notification->message_left_at)?->copy()->timezone($tenantTimeZone)->format('Y-m-d g:i:s A T') ?? '—' }}
Accepted By: {{ $notification->accepted_by_number ?? '—' }}
Retry Number: {{ $notification->current_retry ?? 0 }}
Final Priority: {{ $notification->current_priority ?? '—' }}
Notification ID: {{ $notification->vm_notify_notification_uuid }}

@if($notification->attempts->count())
Attempts:
@foreach($notification->attempts as $attempt)
{{ $attempt->destination ?? '—' }} | {{ $attempt->status ?? '—' }} | Retry {{ $attempt->retry_number ?? '—' }} | Priority {{ $attempt->priority ?? '—' }} | {{ $attempt->claim_result ?? '—' }}
@endforeach
@endif

@if($template_logs->count())
Notification Log:
@foreach($template_logs as $log)
{{ $log['time'] }} | {{ $log['level'] }} | {{ $log['message'] }} | {{ $log['destination'] }} | Retry {{ $log['retry_number'] }} | Priority {{ $log['priority'] }}
@endforeach
@endif

This email was generated automatically by {{ config('app.name', 'FS PBX') }} Voicemail Escalation.
