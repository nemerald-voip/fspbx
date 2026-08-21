<?php

namespace App\Jobs;

use App\Mail\AiAgentToolEmail;
use App\Models\AiToolInvocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class SendAiToolEmail implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $maxExceptions = 5;
    public int $timeout = 120;
    public array $backoff = [30, 60, 120, 300];
    public int $uniqueFor = 900;

    public function __construct(
        private readonly string $invocationUuid,
        private readonly string $domainUuid,
        private readonly string $recipient,
        private readonly string $subject,
        private readonly array $fields,
        private readonly ?string $notes,
    ) {
        $this->onQueue('emails');
    }

    public function uniqueId(): string
    {
        return 'ai-tool-email:' . $this->invocationUuid;
    }

    public function handle(): void
    {
        $invocation = AiToolInvocation::query()->find($this->invocationUuid);

        if (! $invocation || $invocation->status === 'sent') {
            return;
        }

        $invocation->forceFill(['status' => 'sending', 'last_error' => null])->save();

        Redis::throttle('emails')->allow(2)->every(1)->then(function () use ($invocation) {
            try {
                Mail::purge(config('mail.default'));
                Mail::to($this->recipient)->send(new AiAgentToolEmail([
                    'domain_uuid' => $this->domainUuid,
                    'email_subject' => $this->subject,
                    'fields' => $this->fields,
                    'notes' => $this->notes,
                ]));

                $invocation->forceFill([
                    'status' => 'sent',
                    'last_error' => null,
                    'sent_at' => now(),
                ])->save();
            } catch (Throwable $exception) {
                $invocation->forceFill([
                    'status' => 'failed',
                    'last_error' => Str::limit($exception->getMessage(), 2000),
                ])->save();

                throw $exception;
            }
        }, function () {
            $this->release(5);
        });
    }

    public function failed(Throwable $exception): void
    {
        AiToolInvocation::query()->whereKey($this->invocationUuid)->update([
            'status' => 'failed',
            'last_error' => Str::limit($exception->getMessage(), 2000),
            'updated_at' => now(),
        ]);
    }
}
