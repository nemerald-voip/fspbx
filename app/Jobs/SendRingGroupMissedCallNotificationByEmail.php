<?php

namespace App\Jobs;

use App\Mail\MissedCallNotification;
use App\Models\EmailLog;
use App\Models\RingGroups;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class SendRingGroupMissedCallNotificationByEmail implements ShouldQueue
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
        Redis::throttle('email')->allow(2)->every(1)->then(function () {
            $ringGroup = RingGroups::query()
                ->select([
                    'ring_group_uuid',
                    'domain_uuid',
                    'ring_group_name',
                    'ring_group_extension',
                    'ring_group_missed_call_app',
                    'ring_group_missed_call_data',
                ])
                ->whereKey($this->params['ring_group_uuid'] ?? null)
                ->first();

            if (! $ringGroup || $ringGroup->ring_group_missed_call_app !== 'email') {
                return;
            }

            $to = $this->recipients($ringGroup->ring_group_missed_call_data);
            if (empty($to)) {
                return;
            }

            $callerIdName = (string) ($this->params['caller_id_name'] ?? '');
            $callerIdNumber = (string) ($this->params['caller_id_number'] ?? '');
            $callerLabel = trim($callerIdName . ($callerIdNumber !== '' ? ' <' . $callerIdNumber . '>' : ''));

            $attributes = [
                'email_subject' => 'Missed Call' . ($callerLabel !== '' ? ' from ' . $callerLabel : ''),
                'domain_uuid' => $ringGroup->domain_uuid,
                'domain_name' => (string) ($this->params['domain_name'] ?? ''),
                'ring_group_name' => (string) $ringGroup->ring_group_name,
                'ring_group_extension' => (string) $ringGroup->ring_group_extension,
                'caller_id_name' => $callerIdName,
                'caller_id_number' => $callerIdNumber,
                'destination_number' => (string) ($this->params['destination_number'] ?? ''),
                'call_uuid' => (string) ($this->params['call_uuid'] ?? ''),
                'logId' => $this->logId,
            ];

            $sentKey = 'ring-group-missed-call:sent:' . ($this->params['call_uuid'] ?? Str::uuid());
            if (Cache::has($sentKey)) {
                return;
            }

            try {
                Mail::purge(config('mail.default'));
                Mail::to($to)->send(new MissedCallNotification($attributes));
                Cache::put($sentKey, 1, now()->addDay());
            } catch (\Throwable $e) {
                logger()->error('Ring group missed call email send failed', [
                    'ring_group_uuid' => $ringGroup->ring_group_uuid,
                    'call_uuid' => $this->params['call_uuid'] ?? null,
                    'attempt' => $this->attempts(),
                    'max_tries' => $this->tries,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }, function () {
            return $this->release(15);
        });
    }

    public function failed(\Throwable $e): void
    {
        $log = EmailLog::query()->find($this->logId);

        if (! $log) {
            return;
        }

        $log->update([
            'status' => 'failed',
            'sent_debug_info' => trim(($log->sent_debug_info ? $log->sent_debug_info . "\n" : '') . $e->getMessage()),
        ]);
    }

    private function recipients(?string $value): array
    {
        return collect(preg_split('/[;,]+/', (string) $value) ?: [])
            ->map(fn ($recipient) => trim($recipient))
            ->filter(fn ($recipient) => filter_var($recipient, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();
    }

}
