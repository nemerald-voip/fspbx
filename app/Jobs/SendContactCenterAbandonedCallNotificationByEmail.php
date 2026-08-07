<?php

namespace App\Jobs;

use App\Mail\ContactCenterAbandonedCallNotification;
use App\Models\CallCenterQueues;
use App\Models\EmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class SendContactCenterAbandonedCallNotificationByEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 10;
    public $maxExceptions = 10;
    public $timeout = 120;
    public $failOnTimeout = true;
    public $backoff = [30, 60, 120, 300];

    private array $params;
    private string $logId;

    public function __construct(array $params)
    {
        $this->params = $params;
        $this->logId = (string) Str::uuid();
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        if (strtoupper((string) ($this->params['cancel_reason'] ?? '')) === 'EXIT_WITH_KEY') {
            return;
        }

        Redis::throttle('email')->allow(2)->every(1)->then(function () {
            $queue = CallCenterQueues::query()
                ->select([
                    'call_center_queue_uuid',
                    'domain_uuid',
                    'queue_name',
                    'queue_extension',
                    'queue_email_address',
                ])
                ->whereKey($this->params['queue_uuid'] ?? null)
                ->first();

            if (! $queue || ! filter_var($queue->queue_email_address, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            $callUuid = (string) ($this->params['call_uuid'] ?? '');
            if ($callUuid === '') {
                return;
            }

            $sentKey = "contact-center-abandoned-call:sent:{$queue->call_center_queue_uuid}:{$callUuid}";
            if (Cache::has($sentKey)) {
                return;
            }

            $callerIdName = (string) ($this->params['caller_id_name'] ?? '');
            $callerIdNumber = (string) ($this->params['caller_id_number'] ?? '');
            $callerLabel = trim($callerIdName.($callerIdNumber !== '' ? ' <'.$callerIdNumber.'>' : ''));
            $joinedEpoch = (int) ($this->params['joined_epoch'] ?? 0);
            $leavingEpoch = (int) ($this->params['leaving_epoch'] ?? 0);
            $waitSeconds = $joinedEpoch > 0 && $leavingEpoch >= $joinedEpoch
                ? $leavingEpoch - $joinedEpoch
                : null;

            $attributes = [
                'email_subject' => 'Abandoned call'.($callerLabel !== '' ? ' from '.$callerLabel : ''),
                'domain_uuid' => $queue->domain_uuid,
                'queue_name' => (string) $queue->queue_name,
                'queue_extension' => (string) $queue->queue_extension,
                'caller_id_name' => $callerIdName,
                'caller_id_number' => $callerIdNumber,
                'call_uuid' => $callUuid,
                'wait_duration' => $this->formatDuration($waitSeconds),
                'departure_reason' => $this->departureReason((string) ($this->params['cancel_reason'] ?? '')),
                'logId' => $this->logId,
            ];

            try {
                Mail::purge(config('mail.default'));
                Mail::to($queue->queue_email_address)
                    ->send(new ContactCenterAbandonedCallNotification($attributes));
                Cache::put($sentKey, 1, now()->addDay());
            } catch (\Throwable $exception) {
                logger()->error('Contact Center abandoned call email send failed', [
                    'queue_uuid' => $queue->call_center_queue_uuid,
                    'call_uuid' => $callUuid,
                    'attempt' => $this->attempts(),
                    'max_tries' => $this->tries,
                    'error' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        }, function () {
            return $this->release(15);
        });
    }

    public function failed(\Throwable $exception): void
    {
        $log = EmailLog::query()->find($this->logId);

        if (! $log) {
            return;
        }

        $log->update([
            'status' => 'failed',
            'sent_debug_info' => trim(
                ($log->sent_debug_info ? $log->sent_debug_info."\n" : '').$exception->getMessage()
            ),
        ]);
    }

    private function departureReason(string $reason): string
    {
        return match (strtoupper($reason)) {
            'BREAK_OUT' => 'Caller hung up',
            'TIMEOUT' => 'Queue wait time expired',
            'NO_AGENT_TIMEOUT' => 'No agents were available',
            default => 'Caller left before an agent answered',
        };
    }

    private function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        if ($seconds < 60) {
            return $seconds.' '.Str::plural('second', $seconds);
        }

        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        return $minutes.' '.Str::plural('minute', $minutes)
            .($remainingSeconds > 0
                ? ' '.$remainingSeconds.' '.Str::plural('second', $remainingSeconds)
                : '');
    }
}
