<?php

namespace App\Jobs;

use App\Models\VmNotifyAttempt;
use App\Models\VmNotifyLog;
use App\Models\VmNotifyNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use App\Jobs\StartVoicemailEscalationAttemptJob;
use App\Jobs\AdvanceVoicemailEscalationNotificationJob;

class ProcessVoicemailEscalationNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = 15;

    public function __construct(public string $vmNotifyNotificationUuid)
    {
        $this->onQueue('voicemails');
    }

    public function handle(): void
    {
        logger('ProcessVoicemailEscalationNotificationJob');
        // Allow only 2 tasks every 1 second
        Redis::throttle('voicemail')->allow(2)->every(1)->then(function () {

            $notification = VmNotifyNotification::query()
                ->with([
                    'profile.recipients' => function ($query) {
                        $query->where('enabled', true)
                            ->orderByRaw('COALESCE(priority, 0) asc')
                            ->orderByRaw('COALESCE(sort_order, 0) asc')
                            ->orderBy('created_at', 'asc');
                    },
                    'profile.recipients.extension' => function ($query) {
                        $query->select('extension_uuid', 'extension', 'effective_caller_id_name');
                    },
                ])
                ->find($this->vmNotifyNotificationUuid);

            if (!$notification) {
                return;
            }

            if (!in_array($notification->status, ['pending', 'queued', 'running'], true)) {
                return;
            }

            $profile = $notification->profile;

            if (!$profile) {
                $this->log(
                    $notification,
                    'error',
                    'Escalation profile not found for notification.'
                );

                $notification->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                ]);

                return;
            }

            $recipients = $profile->recipients;

            if ($recipients->isEmpty()) {
                $this->log(
                    $notification,
                    'warning',
                    'No enabled recipients found for escalation profile.'
                );

                $notification->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                ]);

                return;
            }

            $retryNumber = (int) ($notification->current_retry ?? 0);
            $priority = $notification->current_priority;

            if ($priority === null) {
                $priority = (int) $recipients->min(fn($recipient) => (int) ($recipient->priority ?? 0));
            } else {
                $priority = (int) $priority;
            }

            $priorityRecipients = $recipients
                ->filter(fn($recipient) => (int) ($recipient->priority ?? 0) === $priority)
                ->values();

            if ($priorityRecipients->isEmpty()) {
                $this->log(
                    $notification,
                    'warning',
                    'No recipients found for the current priority group.',
                    [
                        'current_priority' => $priority,
                        'current_retry' => $retryNumber,
                    ]
                );

                $notification->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                ]);

                return;
            }

            $notification->update([
                'status' => 'running',
                'started_at' => $notification->started_at ?? now(),
                'current_retry' => $retryNumber,
                'current_priority' => $priority,
            ]);

            $this->log(
                $notification,
                'info',
                'Processing escalation notification priority group.',
                [
                    'current_priority' => $priority,
                    'current_retry' => $retryNumber,
                    'recipient_count' => $priorityRecipients->count(),
                ]
            );

            foreach ($priorityRecipients as $recipient) {
                $destination = $this->resolveDestination($recipient);

                if (blank($destination)) {
                    $this->log(
                        $notification,
                        'warning',
                        'Skipping recipient because destination could not be resolved.',
                        [
                            'vm_notify_profile_recipient_uuid' => $recipient->vm_notify_profile_recipient_uuid,
                            'recipient_type' => $recipient->recipient_type,
                        ]
                    );

                    continue;
                }

                $attemptExists = VmNotifyAttempt::query()
                    ->where('vm_notify_notification_uuid', $notification->vm_notify_notification_uuid)
                    ->where('vm_notify_profile_recipient_uuid', $recipient->vm_notify_profile_recipient_uuid)
                    ->where('retry_number', $retryNumber)
                    ->where('priority', $priority)
                    ->exists();

                if ($attemptExists) {
                    continue;
                }

                $attempt = VmNotifyAttempt::create([
                    'domain_uuid' => $notification->domain_uuid,
                    'vm_notify_notification_uuid' => $notification->vm_notify_notification_uuid,
                    'vm_notify_profile_recipient_uuid' => $recipient->vm_notify_profile_recipient_uuid,
                    'retry_number' => $retryNumber,
                    'priority' => $priority,
                    'destination' => $destination,
                    'status' => 'queued',
                    'claim_result' => 'none',
                ]);

                $this->log(
                    $notification,
                    'info',
                    'Created escalation attempt.',
                    [
                        'vm_notify_attempt_uuid' => $attempt->vm_notify_attempt_uuid,
                        'destination' => $destination,
                        'recipient_type' => $recipient->recipient_type,
                        'retry_number' => $retryNumber,
                        'priority' => $priority,
                    ]
                );

                // Next step:
                StartVoicemailEscalationAttemptJob::dispatch($attempt->vm_notify_attempt_uuid);

                // Schedule next priority

                $priorityDelayMinutes = max(0, (int) ($notification->priority_delay_minutes ?? 0));
                AdvanceVoicemailEscalationNotificationJob::dispatch(
                    $notification->vm_notify_notification_uuid,
                    $retryNumber,
                    $priority
                )
                    ->delay(now()->addMinutes($priorityDelayMinutes));
            }
        }, function () {
            throw new \Exception('Could not obtain Redis lock for Voicemail throttling.');
        });
    }

    private function resolveDestination($recipient): ?string
    {
        if (($recipient->recipient_type ?? null) === 'extension') {
            return $recipient->extension?->extension;
        }

        if (($recipient->recipient_type ?? null) === 'external_number') {
            return $recipient->phone_number;
        }

        return null;
    }

    private function log(
        VmNotifyNotification $notification,
        string $level,
        string $message,
        array $context = []
    ): void {
        VmNotifyLog::create([
            'domain_uuid' => $notification->domain_uuid,
            'vm_notify_notification_uuid' => $notification->vm_notify_notification_uuid,
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ]);
    }
}
