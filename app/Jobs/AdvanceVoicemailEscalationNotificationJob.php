<?php

namespace App\Jobs;

use App\Models\VmNotifyLog;
use App\Models\VmNotifyNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class AdvanceVoicemailEscalationNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = 15;

    public function __construct(
        public string $vmNotifyNotificationUuid,
        public int $expectedRetry,
        public int $expectedPriority
    ) {
        $this->onQueue('voicemails');
    }

    public function handle(): void
    {
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
                ])
                ->find($this->vmNotifyNotificationUuid);

            if (!$notification) {
                return;
            }

            if (in_array($notification->status, ['accepted', 'completed', 'failed', 'cancelled'], true)) {
                return;
            }

            // Ignore stale delayed jobs if the notification has already moved on.
            if (
                (int) ($notification->current_retry ?? 0) !== $this->expectedRetry ||
                (int) ($notification->current_priority ?? 0) !== $this->expectedPriority
            ) {
                return;
            }

            $profile = $notification->profile;

            if (!$profile || $profile->recipients->isEmpty()) {
                $notification->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                ]);

                $this->log($notification, 'warning', 'Escalation failed because no enabled recipients were available.');
                return;
            }

            $priorities = $profile->recipients
                ->map(fn($recipient) => (int) ($recipient->priority ?? 0))
                ->unique()
                ->sort()
                ->values();

            $nextPriority = $priorities->first(fn($priority) => $priority > $this->expectedPriority);

            if ($nextPriority !== null) {
                $notification->update([
                    'status' => 'queued',
                    'current_priority' => $nextPriority,
                ]);

                $this->log($notification, 'info', 'Advancing to next priority group.', [
                    'from_priority' => $this->expectedPriority,
                    'to_priority' => $nextPriority,
                    'current_retry' => $this->expectedRetry,
                ]);

                ProcessVoicemailEscalationNotificationJob::dispatch(
                    $notification->vm_notify_notification_uuid
                )->onQueue('voicemails');

                return;
            }

            $maxRetryCount = (int) ($notification->max_retry_count ?? 0);
            $nextRetry = $this->expectedRetry + 1;

            if ($nextRetry <= $maxRetryCount) {
                $lowestPriority = (int) $priorities->min();
                $retryDelayMinutes = max(0, (int) ($notification->retry_delay_minutes ?? 0));

                $notification->update([
                    'status' => 'queued',
                    'current_retry' => $nextRetry,
                    'current_priority' => $lowestPriority,
                ]);

                $this->log($notification, 'info', 'Scheduling next retry cycle.', [
                    'next_retry' => $nextRetry,
                    'priority' => $lowestPriority,
                    'retry_delay_minutes' => $retryDelayMinutes,
                ]);

                ProcessVoicemailEscalationNotificationJob::dispatch(
                    $notification->vm_notify_notification_uuid
                )
                    ->delay(now()->addMinutes($retryDelayMinutes));

                return;
            }

            $notification->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            $this->log($notification, 'warning', 'Escalation failed after all priorities and retries were exhausted.', [
                'final_retry' => $this->expectedRetry,
                'final_priority' => $this->expectedPriority,
            ]);

            SendVoicemailEscalationCompletionEmailJob::dispatch(
                $notification->vm_notify_notification_uuid
            );
        }, function () {
            throw new \Exception('Could not obtain Redis lock for Voicemail throttling.');
        });
    }

    protected function log(VmNotifyNotification $notification, string $level, string $message, array $context = []): void
    {
        $retry = (int) ($context['current_retry'] ?? $notification->current_retry ?? 0);
        $priority = $context['priority'] ?? $context['final_priority'] ?? $notification->current_priority ?? null;

        $messageWithState = $message . " Retry: {$retry}.";
        if ($priority !== null) {
            $messageWithState .= " Priority: {$priority}.";
        }

        VmNotifyLog::create([
            'domain_uuid' => $notification->domain_uuid,
            'vm_notify_notification_uuid' => $notification->vm_notify_notification_uuid,
            'level' => $level,
            'message' => $messageWithState,
            'context' => array_merge($context, [
                'retry_number' => $retry,
                'priority' => $priority,
            ]),
        ]);
    }
}
