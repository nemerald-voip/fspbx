<?php

namespace App\Jobs;

use App\Models\VmNotifyLog;
use App\Models\VmNotifyNotification;
use App\Models\VmNotifyProfile;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class CreateVoicemailEscalationNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = 15;

    public function __construct(public array $data)
    {
        $this->onQueue('voicemails');
    }

    public function handle(): void
    {
        logger('CreateVoicemailEscalationNotificationsJob');
        // Allow only 2 tasks every 1 second
        Redis::throttle('voicemail')->allow(2)->every(1)->then(function () {

            $domainUuid = $this->data['domain_uuid'] ?? null;
            $voicemailUuid = $this->data['voicemail_uuid'] ?? null;
            $voicemailMessageUuid = $this->data['voicemail_message_uuid'] ?? null;

            if (!$domainUuid || !$voicemailUuid || !$voicemailMessageUuid) {
                logger('CreateVoicemailEscalationNotificationsJob: missing required fields');
                return;
            }

            $profiles = VmNotifyProfile::query()
                ->where('domain_uuid', $domainUuid)
                ->where('voicemail_uuid', $voicemailUuid)
                ->where('enabled', true)
                ->with(['recipients' => function ($query) {
                    $query->where('enabled', true)
                        ->orderByRaw('COALESCE(priority, 0) asc')
                        ->orderByRaw('COALESCE(sort_order, 0) asc')
                        ->orderBy('created_at', 'asc');
                }])
                ->get();

            if ($profiles->isEmpty()) {
                return;
            }

            foreach ($profiles as $profile) {
                // Skip profile if it has no enabled recipients
                if ($profile->recipients->isEmpty()) {
                    continue;
                }

                // Idempotency: skip if already created for this profile + voicemail message
                $existing = VmNotifyNotification::query()
                    ->where('vm_notify_profile_uuid', $profile->vm_notify_profile_uuid)
                    ->where('voicemail_message_uuid', $voicemailMessageUuid)
                    ->first();

                if ($existing) {
                    continue;
                }

                $notification = VmNotifyNotification::create([
                    'domain_uuid' => $domainUuid,
                    'vm_notify_profile_uuid' => $profile->vm_notify_profile_uuid,
                    'voicemail_uuid' => $voicemailUuid,
                    'voicemail_message_uuid' => $voicemailMessageUuid,
                    'status' => 'pending',
                    'current_retry' => 0,
                    'current_priority' => $profile->recipients->min(fn($recipient) => (int) ($recipient->priority ?? 0)),
                    'max_retry_count' => $profile->retry_count,
                    'retry_delay_minutes' => $profile->retry_delay_minutes,
                    'priority_delay_minutes' => $profile->priority_delay_minutes,
                    'caller_id_name' => $this->data['caller_id_name'] ?? null,
                    'caller_id_number' => $this->data['caller_id_number'] ?? null,
                    'mailbox' => $this->data['voicemail_id'] ?? null,
                    'message_length_seconds' => isset($this->data['message_length'])
                        ? (int) $this->data['message_length']
                        : null,
                    'message_left_at' => isset($this->data['start_epoch'])
                        ? Carbon::createFromTimestamp((int) $this->data['start_epoch'])
                        : now(),
                    'message_file_path' => $this->data['file_path'] ?? null,
                    'message_ext' => $this->data['message_ext'] ?? null,
                ]);

                VmNotifyLog::create([
                    'domain_uuid' => $domainUuid,
                    'vm_notify_notification_uuid' => $notification->vm_notify_notification_uuid,
                    'level' => 'info',
                    'message' => 'Escalation notification created from voicemail webhook.',
                    'context' => [
                        'voicemail_uuid' => $voicemailUuid,
                        'voicemail_message_uuid' => $voicemailMessageUuid,
                        'voicemail_id' => $this->data['voicemail_id'] ?? null,
                        'caller_id_name' => $this->data['caller_id_name'] ?? null,
                        'caller_id_number' => $this->data['caller_id_number'] ?? null,
                        'message_length' => $this->data['message_length'] ?? null,
                        'start_epoch' => $this->data['start_epoch'] ?? null,
                        'file_path' => $this->data['file_path'] ?? null,
                    ],
                ]);

                ProcessVoicemailEscalationNotificationJob::dispatch($notification->vm_notify_notification_uuid);
            }
        }, function () {
            throw new \Exception('Could not obtain Redis lock for Voicemail throttling.');
        });
    }
}
