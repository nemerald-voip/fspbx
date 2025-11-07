<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\CallTranscription;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\PollOpenAIBackgroundSummary;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SummarizeCallTranscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 2;
    public $backoff = [10, 30, 60, 120];
    public $timeout = 300;
    public $maxExceptions = 2;

    public function __construct(public string $uuid) {}

    public function handle(): void
    {
        logger('SummarizeCallTranscription');
        // Allow only 2 tasks every 1 second
        Redis::throttle('summaries')->allow(2)->every(1)->then(function () {

            $row = CallTranscription::find($this->uuid);
            if (!$row) return;

            $full = (array) $row->result_payload;

            // Build compact utterance lines (trim long calls if needed)
            $utterances = (array) data_get($full, 'utterances', []);
            $lines = [];
            foreach ($utterances as $u) {
                $speaker = data_get($u, 'speaker');
                $text    = trim((string) data_get($u, 'text', ''));
                if ($speaker && $text !== '') {
                    $lines[] = "{$speaker}: {$text}";
                }
            }

            if (!$lines) {
                // nothing to summarize
                $row->update([
                    'summary_status' => 'failed',
                    'summary_error'  => 'No utterances available for summarization.',
                ]);
                return;
            }

            // try {

            // Kick off OpenAI background task
            $openAiService = app(\App\Services\OpenAIService::class);
            $start  = $openAiService->createBackgroundSummary($lines, 'gpt-5-nano');

            $responseId = $start['id'] ?? null;
            $status     = $start['status'] ?? 'queued';

            if (!$responseId) {
                $row->update([
                    'summary_status' => 'failed',
                    'summary_error'  => 'OpenAI did not return response id.',
                ]);
                return;
            }

            logger($start);

            $row->update([
                'summary_provider'   => 'openai',
                'summary_external_id'=> $responseId,
                'summary_status'     => in_array($status, ['queued','in_progress']) ? $status : 'queued',
                'summary_error'      => null,
            ]);


            // Schedule the polling job
            PollOpenAIBackgroundSummary::dispatch($row->uuid, $responseId)->delay(now()->addMinutes(1))->onQueue('transcriptions');;

            // } catch (\Throwable $e) {
            //     report($e);
            //     // Let retries/backoff handle transient issues
            //     throw $e;
            // }
        }, function () {
            return $this->release(30); // If locked, retry in 30 seconds
        });
    }


    // public function failed(\Throwable $e): void
    // {
    //     try {
    //         $row = CallTranscription::find($this->uuid);
    //         if (!$row) {
    //             // Nothing to update; still log for visibility
    //             report($e);
    //             return;
    //         }

    //         // Compose a concise error (avoid gigantic traces in DB)
    //         $message = trim($e->getMessage());
    //         if ($message === '' || $message === null) {
    //             $message = class_basename($e) . ' thrown with empty message';
    //         }
    //         // Optionally include code + short file:line
    //         $short = sprintf('[%s] %s at %s:%d',
    //             $e->getCode() ?: 0,
    //             $message,
    //             basename($e->getFile()),
    //             $e->getLine()
    //         );

    //         $row->update([
    //             'summary_status' => 'failed',
    //             'summary_error'  => Str::limit($short, 1000), // cap length for DB
    //         ]);

    //     } catch (\Throwable $inner) {
    //         // Never let failed() crash; log both
    //         report($inner);
    //         report($e);
    //     }
    // }
}
