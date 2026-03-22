<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Models\VmNotifyAttempt;
use App\Models\VmNotifyLog;
use App\Services\FreeswitchEslService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class StartVoicemailEscalationAttemptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = 15;

    public function __construct(public string $vmNotifyAttemptUuid)
    {
        $this->onQueue('voicemails');
    }

    public function handle(FreeswitchEslService $eslService): void
    {
        // Allow only 2 tasks every 1 second
        Redis::throttle('voicemail')->allow(2)->every(1)->then(function () use ($eslService) {
            $attempt = VmNotifyAttempt::query()
                ->with([
                    'notification.profile',
                    'recipient.extension',
                ])
                ->find($this->vmNotifyAttemptUuid);

            if (!$attempt) {
                return;
            }

            $notification = $attempt->notification;
            $profile = $notification?->profile;
            $recipient = $attempt->recipient;

            if (!$notification || !$profile || !$recipient) {
                $this->log($attempt, 'error', 'Attempt is missing notification, profile, or recipient.');
                $attempt->update([
                    'status' => 'failed',
                    'notes' => 'Missing related notification/profile/recipient.',
                    'ended_at' => now(),
                ]);
                return;
            }

            if (!in_array($notification->status, ['running', 'queued', 'pending'], true)) {
                return;
            }

            $domain = Domain::query()
                ->select('domain_uuid', 'domain_name')
                ->where('domain_uuid', $notification->domain_uuid)
                ->first();

            if (!$domain) {
                $this->log($attempt, 'error', 'Domain not found for attempt.');
                $attempt->update([
                    'status' => 'failed',
                    'notes' => 'Domain not found.',
                    'ended_at' => now(),
                ]);
                return;
            }

            $endpoint = $this->resolveEndpoint($recipient, $domain->domain_name);

            if (blank($endpoint)) {
                $this->log($attempt, 'warning', 'Could not resolve destination endpoint for attempt.', [
                    'recipient_type' => $recipient->recipient_type,
                    'destination' => $attempt->destination,
                ]);

                $attempt->update([
                    'status' => 'failed',
                    'notes' => 'Could not resolve destination endpoint.',
                    'ended_at' => now(),
                ]);
                return;
            }

            if (blank($attempt->call_uuid)) {
                $attempt->call_uuid = (string) Str::uuid();
            }

            $callerIdNumber = $profile->outbound_cid_mode === 'mailbox'
                ? ($notification->mailbox ?: $profile->caller_id_number)
                : ($profile->caller_id_number ?: $notification->mailbox);

            $callerIdName = $profile->caller_id_name ?: ('VMNFY-' . ($notification->mailbox ?: 'Mailbox'));

            $destinationNumber = $attempt->destination ?? $recipient->extension?->extension;

            $originationVars = [
                'sip_call_id' => $attempt->call_uuid, 
                'ignore_early_media' => 'true',
                'origination_caller_id_name' => $callerIdName,
                'origination_caller_id_number' => $callerIdNumber,
                // 'context' => $domain->domain_name,
                // 'destination_number' => $destinationNumber,
                // 'domain_name' => $domain->domain_name,
                'vm_notify_attempt_uuid' => $attempt->vm_notify_attempt_uuid,
                'vm_notify_notification_uuid' => $notification->vm_notify_notification_uuid,
                'vm_notify_profile_uuid' => $profile->vm_notify_profile_uuid,
                'vm_notify_voicemail_uuid' => $notification->voicemail_uuid,
                'vm_notify_voicemail_message_uuid' => $notification->voicemail_message_uuid,
                'vm_notify_domain_uuid' => $notification->domain_uuid,
                'vm_notify_domain_name' => $domain->domain_name,
                'vm_notify_mailbox' => $notification->mailbox,
                'vm_notify_caller_id_name' => $notification->caller_id_name,
                'vm_notify_caller_id_number' => $notification->caller_id_number,
                'vm_notify_message_path' => $notification->message_file_path,
            ];

            $origination = $this->buildOriginateCommand($originationVars, $endpoint);

            try {
                if (!$eslService->isConnected()) {
                    $eslService->reconnect();
                }

                // Replace this with your actual generic ESL bgapi / api method.
                $eslService->executeCommand($origination);

                $attempt->update([
                    'status' => 'dialing',
                    'notes' => 'Originate command sent to FreeSWITCH.',
                ]);

                $this->log($attempt, 'info', 'Originate command sent.', [
                    'call_uuid' => $attempt->call_uuid,
                    'endpoint' => $endpoint,
                ]);
            } catch (\Throwable $e) {
                $attempt->update([
                    'status' => 'failed',
                    'notes' => 'Failed to send originate command: ' . $e->getMessage(),
                    'ended_at' => now(),
                ]);

                $this->log($attempt, 'error', 'Failed to send originate command.', [
                    'error' => $e->getMessage(),
                    'endpoint' => $endpoint,
                ]);
            }
        }, function () {
            throw new \Exception('Could not obtain Redis lock for Voicemail throttling.');
        });
    }

    protected function resolveEndpoint($recipient, string $domainName): ?string
    {
        if ($recipient->recipient_type === 'extension') {
            $extension = $recipient->extension?->extension;

            if (blank($extension)) {
                return null;
            }

            return "loopback/{$extension}/{$domainName}/XML";;
        }

        if ($recipient->recipient_type === 'external_number') {
            if (blank($recipient->phone_number)) {
                return null;
            }

            // Sends the call back into the XML dialplan, using the domain context.
            // It will hit "user_exists = false" and fall into the gateway bridge.
            return "loopback/{$recipient->phone_number}/{$domainName}/XML";
        }

        return null;
    }

    protected function buildOriginateCommand(array $vars, string $endpoint): string
    {
        $encodedVars = collect($vars)
            ->map(function ($value, $key) {
                $value = str_replace(["\\", "'", ","], ["\\\\", "\\'", "\\,"], (string) $value);
                return "{$key}='{$value}'";
            })
            ->implode(',');

        // Hand the answered call off to your Lua handler.
        return "bgapi originate {{$encodedVars}}{$endpoint} &lua(lua/vm_escalation_notify.lua)";
    }

    protected function log(VmNotifyAttempt $attempt, string $level, string $message, array $context = []): void
    {
        $destination = $attempt->destination ?? 'unknown';
        $retry = $attempt->retry_number ?? 0;
        $priority = $attempt->priority ?? 0;

        VmNotifyLog::create([
            'domain_uuid' => $attempt->domain_uuid,
            'vm_notify_notification_uuid' => $attempt->vm_notify_notification_uuid,
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
