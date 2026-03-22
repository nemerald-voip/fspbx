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

class HandleVoicemailEscalationAttemptEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $data)
    {
        $this->onQueue('voicemails');
    }

    public function handle(): void
    {
        // Allow only 2 tasks every 1 second
        Redis::throttle('voicemail')->allow(2)->every(1)->then(function () {
            $attemptUuid = $this->data['vm_notify_attempt_uuid'] ?? null;
            $action = $this->data['action'] ?? null;

            if (!$attemptUuid || !$action) {
                return;
            }

            $attempt = VmNotifyAttempt::with('notification')->find($attemptUuid);

            if (!$attempt || !$attempt->notification) {
                return;
            }

            $notification = $attempt->notification;

            switch ($action) {
                case 'answered':
                    $attempt->update([
                        'status' => 'answered',
                        'answered_at' => now(),
                    ]);
                    $this->log($notification, 'info', 'Recipient answered escalation call.', $attempt);
                    break;

                case 'declined':
                    $attempt->update([
                        'status' => 'completed',
                        'claim_result' => 'declined',
                        'claim_attempted_at' => now(),
                        'dtmf_sequence' => $this->data['dtmf_sequence'] ?? null,
                        'ended_at' => now(),
                    ]);
                    $this->log($notification, 'info', 'Recipient declined responsibility.', $attempt);
                    break;

                case 'caller_id_requested':
                    $this->log($notification, 'info', 'Recipient requested caller ID playback.', $attempt);
                    break;

                case 'playback_started':
                    $this->log($notification, 'info', 'Voicemail playback started.', $attempt);
                    break;

                case 'playback_completed':
                    $this->log($notification, 'info', 'Voicemail playback completed.', $attempt);
                    break;

                case 'hungup':
                    $attempt->update([
                        'status' => 'completed',
                        'claim_result' => $attempt->claim_result === 'accepted' ? 'accepted' : 'no_response',
                        'dtmf_sequence' => $this->data['dtmf_sequence'] ?? $attempt->dtmf_sequence,
                        'ended_at' => now(),
                    ]);
                    $this->log($notification, 'info', 'Escalation call ended.', $attempt);
                    break;

                case 'failed':
                    $attempt->update([
                        'status' => 'failed',
                        'notes' => $this->data['reason'] ?? null,
                        'ended_at' => now(),
                    ]);
                    $this->log($notification, 'warning', 'Escalation attempt failed.', $attempt, [
                        'reason' => $this->data['reason'] ?? null,
                    ]);
                    break;

                case 'accepted':
                    $attempt->update([
                        'status' => 'completed',
                        'claim_result' => 'accepted',
                        'claim_attempted_at' => now(),
                        'dtmf_sequence' => $this->data['dtmf_sequence'] ?? null,
                        'ended_at' => now(),
                    ]);
                    $this->log($notification, 'success', 'Recipient accepted responsibility.', $attempt);
                    break;

                case 'claim_failed':
                    $this->log($notification, 'warning', 'Recipient claim attempt failed.', $attempt, [
                        'reason' => $this->data['reason'] ?? null,
                    ]);
                    break;
            }
        }, function () {
            throw new \Exception('Could not obtain Redis lock for Voicemail throttling.');
        });
    }

    protected function log(VmNotifyNotification $notification, string $level, string $message, VmNotifyAttempt $attempt, array $context = []): void
    {
        $destination = $attempt->destination ?? 'unknown';
        $retry = $attempt->retry_number ?? 0;
        $priority = $attempt->priority ?? 0;

        VmNotifyLog::create([
            'domain_uuid' => $notification->domain_uuid,
            'vm_notify_notification_uuid' => $notification->vm_notify_notification_uuid,
            'level' => $level,
            'message' => "{$message} Destination: {$destination}. Retry: {$retry}. Priority: {$priority}.",
            'context' => array_merge($context, [
                'vm_notify_attempt_uuid' => $attempt->vm_notify_attempt_uuid,
                'destination' => $destination,
                'retry_number' => $retry,
                'priority' => $priority,
            ]),
        ]);
    }
}
