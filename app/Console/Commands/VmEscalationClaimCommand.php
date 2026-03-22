<?php

namespace App\Console\Commands;

use App\Jobs\SendVoicemailEscalationCompletionEmailJob;
use App\Models\VmNotifyAttempt;
use App\Models\VmNotifyLog;
use App\Models\VmNotifyNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VmEscalationClaimCommand extends Command
{
    protected $signature = 'vm-notify:claim
                            {attempt_uuid : VM notify attempt UUID}
                            {notification_uuid : VM notify notification UUID}
                            {--dtmf= : DTMF sequence entered by recipient}';

    protected $description = 'Attempt to claim a voicemail escalation notification';

    public function handle(): int
    {
        $attemptUuid = $this->argument('attempt_uuid');
        $notificationUuid = $this->argument('notification_uuid');
        $dtmf = $this->option('dtmf');

        try {
            $result = DB::transaction(function () use ($attemptUuid, $notificationUuid, $dtmf) {
                $attempt = VmNotifyAttempt::query()
                    ->where('vm_notify_attempt_uuid', $attemptUuid)
                    ->lockForUpdate()
                    ->first();

                if (!$attempt) {
                    return ['status' => 'attempt_not_found', 'code' => self::FAILURE];
                }

                $notification = VmNotifyNotification::query()
                    ->where('vm_notify_notification_uuid', $notificationUuid)
                    ->lockForUpdate()
                    ->first();

                if (!$notification) {
                    return ['status' => 'notification_not_found', 'code' => self::FAILURE];
                }

                if ($attempt->vm_notify_notification_uuid !== $notification->vm_notify_notification_uuid) {
                    return ['status' => 'mismatch', 'code' => self::FAILURE];
                }

                if (in_array($notification->status, ['accepted', 'completed'], true)) {
                    $attempt->update([
                        'status' => 'completed',
                        'claim_result' => 'already_claimed',
                        'claim_attempted_at' => now(),
                        'dtmf_sequence' => $dtmf,
                        'ended_at' => now(),
                    ]);

                    VmNotifyLog::create([
                        'domain_uuid' => $notification->domain_uuid,
                        'vm_notify_notification_uuid' => $notification->vm_notify_notification_uuid,
                        'level' => 'info',
                        'message' => 'Recipient attempted to accept, but message was already claimed.',
                        'context' => [
                            'vm_notify_attempt_uuid' => $attempt->vm_notify_attempt_uuid,
                        ],
                    ]);

                    return ['status' => 'already_claimed', 'code' => self::SUCCESS];
                }

                $notification->update([
                    'status' => 'accepted',
                    'accepted_by_recipient_uuid' => $attempt->vm_notify_profile_recipient_uuid,
                    'accepted_by_number' => $attempt->destination,
                    'accepted_at' => now(),
                    'completed_at' => now(),
                ]);

                SendVoicemailEscalationCompletionEmailJob::dispatch(
                    $notification->vm_notify_notification_uuid
                );

                $attempt->update([
                    'status' => 'completed',
                    'claim_result' => 'accepted',
                    'claim_attempted_at' => now(),
                    'dtmf_sequence' => $dtmf,
                    'ended_at' => now(),
                ]);

                VmNotifyAttempt::query()
                    ->where('vm_notify_notification_uuid', $notification->vm_notify_notification_uuid)
                    ->where('vm_notify_attempt_uuid', '!=', $attempt->vm_notify_attempt_uuid)
                    ->whereIn('status', ['queued', 'dialing', 'answered'])
                    ->update([
                        'status' => 'cancelled',
                        'claim_result' => 'lost_race',
                        'ended_at' => now(),
                    ]);

                VmNotifyLog::create([
                    'domain_uuid' => $notification->domain_uuid,
                    'vm_notify_notification_uuid' => $notification->vm_notify_notification_uuid,
                    'level' => 'success',
                    'message' => 'Voicemail escalation accepted.',
                    'context' => [
                        'vm_notify_attempt_uuid' => $attempt->vm_notify_attempt_uuid,
                        'accepted_by_number' => $attempt->destination,
                    ],
                ]);

                return ['status' => 'accepted', 'code' => self::SUCCESS];
            });

            $this->line($result['status']);
            return $result['code'];
        } catch (\Throwable $e) {
            logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            $this->line('error');
            return self::FAILURE;
        }
    }
}
