<?php

namespace App\Mail;

use App\Models\VmNotifyNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VoicemailEscalationCompletionMail extends Mailable
{
    use Queueable, SerializesModels;

    public VmNotifyNotification $notification;
    public string $statusLabel;
    public string $subjectLine;

    public function __construct(VmNotifyNotification $notification)
    {
        $this->notification = $notification;
        $this->statusLabel = $notification->status === 'accepted' ? 'SUCCESSFUL' : 'FAILED';
        $this->subjectLine = $notification->status === 'accepted'
            ? 'Voicemail escalation accepted for mailbox ' . ($notification->mailbox ?? 'Unknown')
            : 'Voicemail escalation failed for mailbox ' . ($notification->mailbox ?? 'Unknown');
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('emails.voicemail-escalation.voicemail-escalation-completion');
    }
}