<?php

namespace App\Jobs;

use App\Mail\ExtensionWelcome;
use App\Models\EmailLog;
use App\Services\ExtensionWelcomeEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class SendExtensionWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $maxExceptions = 5;
    public int $timeout = 120;
    public bool $failOnTimeout = true;
    public array $backoff = [30, 60, 120, 300];

    private string $logId;

    public function __construct(
        private readonly string $extensionUuid,
        private readonly string $domainUuid,
        private readonly string $recipient,
    ) {
        $this->logId = (string) Str::uuid();
        $this->onQueue('emails');
    }

    public function handle(ExtensionWelcomeEmailService $service): void
    {
        $attributes = $service->attributesForSend(
            $this->extensionUuid,
            $this->domainUuid,
            $this->recipient
        );

        if (! $attributes) {
            return;
        }

        $attributes['logId'] = $this->logId;

        Redis::throttle('emails')->allow(2)->every(1)->then(function () use ($attributes) {
            Mail::purge(config('mail.default'));
            Mail::to($this->recipient)->send(new ExtensionWelcome($attributes));
        }, function () {
            $this->release(5);
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
}
