<?php

namespace App\Mail;

use App\Models\VmNotifyNotification;
use Illuminate\Mail\Mailables\Content;

class VoicemailEscalationCompletionMail extends BaseMailable
{
    public function __construct(public VmNotifyNotification $notification)
    {
        $statusLabel = $notification->status === 'accepted' ? 'SUCCESSFUL' : 'FAILED';
        $subject = $notification->status === 'accepted'
            ? 'Voicemail escalation accepted for mailbox '.($notification->mailbox ?? 'Unknown')
            : 'Voicemail escalation failed for mailbox '.($notification->mailbox ?? 'Unknown');
        $timezone = get_local_time_zone($notification->domain_uuid) ?? 'UTC';
        $dateFormat = local_datetime_format($notification->domain_uuid) . ' T';

        $attributes = [
            'domain_uuid' => $notification->domain_uuid,
            'email_subject' => $subject,
            'status_label' => $statusLabel,
            'mailbox' => $notification->mailbox ?? 'Unknown',
            'caller_id_name' => $notification->caller_id_name ?? '—',
            'caller_id_number' => $notification->caller_id_number ?? '—',
            'message_length_seconds' => $notification->message_length_seconds ?? '—',
            'message_left_at' => $notification->message_left_at?->copy()->timezone($timezone)->format($dateFormat) ?? '—',
            'accepted_by_number' => $notification->accepted_by_number ?? '—',
            'current_retry' => $notification->current_retry ?? 0,
            'current_priority' => $notification->current_priority ?? '—',
            'notification_uuid' => $notification->vm_notify_notification_uuid,
            'notification' => $notification,
            'tenantTimeZone' => $timezone,
            'subjectLine' => $subject,
            'statusLabel' => $statusLabel,
            'template_logs' => $notification->logs->map(function ($log) use ($timezone, $dateFormat) {
                $context = is_array($log->context) ? $log->context : [];

                return [
                    'time' => $log->created_at?->copy()->timezone($timezone)->format($dateFormat) ?? '—',
                    'level' => strtoupper($log->level ?? 'info'),
                    'message' => $log->message,
                    'destination' => $context['destination'] ?? '—',
                    'retry_number' => $context['retry_number'] ?? '—',
                    'priority' => $context['priority'] ?? '—',
                ];
            }),
        ];

        parent::__construct($attributes);
        $this->useEmailTemplate('voicemail', 'escalation-completion');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.en-us.voicemail.escalation-completion',
            text: 'emails.en-us.voicemail.escalation-completion-text',
        ));
    }
}
