<?php

namespace App\Jobs;

use App\Mail\VoicemailEscalationCompletionMail;
use App\Models\VmNotifyLog;
use App\Models\VmNotifyNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class SendVoicemailEscalationCompletionEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = 15;

    public function __construct(public string $vmNotifyNotificationUuid)
    {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        logger('SendVoicemailEscalationCompletionEmailJob');
        // Allow only 2 tasks every 1 second
        Redis::throttle('emails')->allow(2)->every(1)->then(function () {
            $notification = VmNotifyNotification::query()
                ->with([
                    'profile',
                    'attempts.recipient.extension',
                    'logs' => function ($query) {
                        $query->orderBy('created_at', 'asc');
                    },
                ])
                ->find($this->vmNotifyNotificationUuid);

            if (!$notification || !$notification->profile) {
                return;
            }

            if (!in_array($notification->status, ['accepted', 'failed'], true)) {
                return;
            }

            $profile = $notification->profile;

            $isSuccess = $notification->status === 'accepted';

            if ($isSuccess && $notification->success_email_sent_at) {
                return;
            }

            if (!$isSuccess && $notification->failure_email_sent_at) {
                return;
            }

            $recipients = $isSuccess
                ? ($profile->email_success ?? [])
                : ($profile->email_fail ?? []);

            if (!is_array($recipients) || empty($recipients)) {
                return;
            }

            $recipients = collect($recipients)
                ->filter(fn($email) => !blank($email))
                ->map(fn($email) => trim(strtolower($email)))
                ->unique()
                ->values()
                ->all();

            if (empty($recipients)) {
                return;
            }

            $mailable = new VoicemailEscalationCompletionMail($notification);

            if (
                (bool) $profile->email_attach &&
                !blank($notification->message_file_path) &&
                file_exists($notification->message_file_path)
            ) {
                $attachmentName = 'voicemail_' . ($notification->mailbox ?: 'message') . '_' . $notification->voicemail_message_uuid . '.' . ($notification->message_ext ?: 'wav');

                $mailable->attach($notification->message_file_path, [
                    'as' => $attachmentName,
                ]);
            }

            Mail::to($recipients)->send($mailable);

            if ($isSuccess) {
                $notification->update([
                    'success_email_sent_at' => now(),
                ]);

                $this->log($notification, 'info', 'Sent escalation success email.', [
                    'recipients' => $recipients,
                ]);
            } else {
                $notification->update([
                    'failure_email_sent_at' => now(),
                ]);

                $this->log($notification, 'info', 'Sent escalation failure email.', [
                    'recipients' => $recipients,
                ]);
            }
        }, function () {
            throw new \Exception('Could not obtain Redis lock for Voicemail throttling.');
        });
    }

    protected function log(VmNotifyNotification $notification, string $level, string $message, array $context = []): void
    {
        VmNotifyLog::create([
            'domain_uuid' => $notification->domain_uuid,
            'vm_notify_notification_uuid' => $notification->vm_notify_notification_uuid,
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ]);
    }
}
