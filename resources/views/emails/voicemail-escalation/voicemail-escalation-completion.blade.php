<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin-bottom: 16px;">Voicemail Escalation {{ $statusLabel }}</h2>

    <p>
        A voicemail escalation for mailbox <strong>{{ $notification->mailbox ?? 'Unknown' }}</strong>
        has completed with status <strong>{{ $statusLabel }}</strong>.
    </p>

    <h3 style="margin-top: 24px;">Message Details</h3>
    <table cellpadding="6" cellspacing="0" border="0">
        <tr>
            <td><strong>Mailbox:</strong></td>
            <td>{{ $notification->mailbox ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Status:</strong></td>
            <td>{{ $statusLabel }}</td>
        </tr>
        <tr>
            <td><strong>Caller ID Name:</strong></td>
            <td>{{ $notification->caller_id_name ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Caller ID Number:</strong></td>
            <td>{{ $notification->caller_id_number ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Message Length:</strong></td>
            <td>{{ $notification->message_length_seconds ?? '—' }} seconds</td>
        </tr>
        <tr>
            <td><strong>Left At:</strong></td>
            <td>{{ $notification->message_left_at ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Accepted By:</strong></td>
            <td>{{ $notification->accepted_by_number ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Retry Number:</strong></td>
            <td>{{ $notification->current_retry ?? 0 }}</td>
        </tr>
        <tr>
            <td><strong>Final Priority:</strong></td>
            <td>{{ $notification->current_priority ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Notification ID:</strong></td>
            <td>{{ $notification->vm_notify_notification_uuid }}</td>
        </tr>
    </table>

    @if($notification->attempts->count())
        <h3 style="margin-top: 24px;">Attempts</h3>
        <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th align="left">Destination</th>
                    <th align="left">Status</th>
                    <th align="left">Retry</th>
                    <th align="left">Priority</th>
                    <th align="left">Claim Result</th>
                </tr>
            </thead>
            <tbody>
                @foreach($notification->attempts as $attempt)
                    <tr>
                        <td>{{ $attempt->destination ?? '—' }}</td>
                        <td>{{ $attempt->status ?? '—' }}</td>
                        <td>{{ $attempt->retry_number ?? '—' }}</td>
                        <td>{{ $attempt->priority ?? '—' }}</td>
                        <td>{{ $attempt->claim_result ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($notification->logs->count())
        <h3 style="margin-top: 24px;">Notification Log</h3>
        <ul style="padding-left: 20px;">
            @foreach($notification->logs as $log)
                <li>
                    [{{ $log->created_at }}] {{ strtoupper($log->level ?? 'info') }} — {{ $log->message }}
                </li>
            @endforeach
        </ul>
    @endif

    <p style="margin-top: 24px; color: #6B7280; font-size: 12px;">
        This email was generated automatically by FS PBX Voicemail Escalation.
    </p>
</body>
</html>