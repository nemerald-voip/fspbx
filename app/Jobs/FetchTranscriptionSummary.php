<?php

namespace App\Jobs;

use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Models\CallTranscription;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\CallTranscription\CallTranscriptionService;

class FetchTranscriptionSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const FAST_POLL_SECONDS = 10 * 60;
    private const POLLING_SECONDS = (10 + 120) * 60;
    private const POLLING_TIMEOUT = 'OpenAI summary did not finish within 2 hours and 10 minutes.';

    public $tries = 0;             // Elapsed time bounds polling; exceptions remain limited below.
    public $backoff = 60;
    public $timeout = 60;
    public $maxExceptions = 3;

    public ?int $pollingStartedAt = null;

    public function __construct(
        public string $uuid,
        public string $responseId
    ) {
        $this->pollingStartedAt = now()->getTimestamp();
    }

    public function handle(): void
    {
        $row = CallTranscription::find($this->uuid);
        if (!$row) return;

        // Ignore polls for replaced requests or an already saved summary.
        if (!$row->summary_external_id || $row->summary_external_id !== $this->responseId || $row->summary_status === 'completed') {
            return;
        }

        // Jobs queued before this change do not contain the serialized start time.
        $this->pollingStartedAt ??= (int) ($this->job?->payload()['pushedAt']
            ?? $row->summary_requested_at?->getTimestamp()
            ?? $row->created_at?->getTimestamp()
            ?? now()->getTimestamp());

        // Allow the last scheduled check at the deadline, but never resume an expired poll.
        if (now()->getTimestamp() > $this->pollingStartedAt + self::POLLING_SECONDS) {
            $this->fail(new \RuntimeException(self::POLLING_TIMEOUT));
            return;
        }

        // Allow only 2 tasks every 1 second
        Redis::throttle('summaries')->allow(2)->every(1)->then(function () use ($row) {

            $openAiService = app(\App\Services\OpenAIService::class);
            $retrieved  = $openAiService->retrieveResponseById($this->responseId);
            $status     = $retrieved['status'] ?? 'unknown';
            $raw        = $retrieved['raw'] ?? [];
            $outputText = (string) ($retrieved['text'] ?? ''); // normalized

            if (in_array($status, ['queued', 'in_progress'])) {
                $this->releaseForNextPoll();
                return;
            }

            if ($status === 'failed') {
                $row->update([
                    'summary_status' => 'failed',
                    'summary_error'  => (string) data_get($raw, 'error.message') ?: 'OpenAI reported failure.',
                ]);
                return;
            }

            if ($status === 'completed') {
                // Sometimes models return a JSON string with leading/trailing whitespace or newlines
                $json = trim($outputText);

                // guard against accidental Markdown fences
                if (str_starts_with($json, '```')) {
                    $json = trim(preg_replace('/^```(?:json)?|```$/m', '', $json));
                }

                $decoded = json_decode($json, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                    $row->update([
                        'summary_status' => 'failed',
                        'summary_error'  => 'Invalid JSON from LLM: ' . json_last_error_msg(),
                        'summary_payload' => [
                            'raw_output_text' => $outputText,
                            'raw_response'    => $raw,
                        ],
                    ]);
                    return;
                }

                // Persist the structured JSON
                $row->update([
                    'summary_status'       => 'completed',
                    'summary_error'        => null,
                    'summary_payload'      => $decoded,
                    'summary_completed_at' => now(),
                ]);

                // Check if transcript should be emailed
                $transcriptionService = app(CallTranscriptionService::class);
                $cfg = $transcriptionService->emailDeliveryConfig($row->domain_uuid ?? null);

                if ($cfg['enabled'] && !empty($cfg['email'])) {
                    SendTranscriptionEmail::dispatch($row->uuid, $cfg['email']);
                }

                return;
            }

            // Unknown states use the same bounded polling schedule.
            $this->releaseForNextPoll();
        }, function () {
            $this->releaseForNextPoll();
        });
    }

    private function releaseForNextPoll(): void
    {
        $elapsed = now()->getTimestamp() - $this->pollingStartedAt;
        $remaining = self::POLLING_SECONDS - $elapsed;

        if ($remaining <= 0) {
            $this->fail(new \RuntimeException(self::POLLING_TIMEOUT));
            return;
        }

        $interval = $elapsed < self::FAST_POLL_SECONDS ? 60 : 300;
        $this->release(min($interval, $remaining));
    }

    /**
     * Called by Laravel when the job has failed permanently
     * (polling expires or exceptions reach $maxExceptions).
     */
    public function failed(\Throwable $e): void
    {
        try {
            $row = CallTranscription::find($this->uuid);
            if (!$row) {
                // Nothing to update; still log for visibility
                report($e);
                return;
            }

            // An expired poll must not mark a newer summary request as failed.
            if ($row->summary_external_id !== $this->responseId) {
                return;
            }

            // Build concise error (avoid giant traces in DB)
            $message = trim($e->getMessage() ?? '');
            if ($message === '') {
                $message = class_basename($e) . ' thrown with empty message';
            }
            $short = sprintf(
                '[%s] %s at %s:%d',
                $e->getCode() ?: 0,
                $message,
                basename($e->getFile()),
                $e->getLine()
            );

            // Only overwrite if we’re not already completed
            if ($row->summary_status !== 'completed') {
                $row->update([
                    'summary_status' => 'failed',
                    'summary_error'  => Str::limit($short, 1000),
                ]);
            }
        } catch (\Throwable $inner) {
            // Never let failed() crash; log both
            report($inner);
            report($e);
        }
    }
}
