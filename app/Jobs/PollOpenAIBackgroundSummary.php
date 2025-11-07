<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\CallTranscription;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\CallTranscription\CallTranscriptionService;

class PollOpenAIBackgroundSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 60;            // up to 60 minutes of polling (1/min)
    public $backoff = 60;          // 60s between attempts
    public $timeout = 60;
    public $maxExceptions = 2;

    public function __construct(
        public string $uuid,
        public string $responseId
    ) {}

    public function handle(): void
    {
        logger('SummarizeCallTranscription');
        // Allow only 2 tasks every 1 second
        Redis::throttle('summaries')->allow(2)->every(1)->then(function () {

            logger('transcription uuid: ' . $this->uuid);

            $row = CallTranscription::find($this->uuid);
            if (!$row) return;

            // guard against mismatched/cleared ids
            if (!$row->summary_external_id || $row->summary_external_id !== $this->responseId) {
                return;
            }

            $openAiService = app(\App\Services\OpenAIService::class);
            $retrieved  = $openAiService->retrieveResponseById($this->responseId);

            logger($retrieved);

            $status     = $retrieved['status'] ?? 'unknown';
            $raw        = $retrieved['raw'] ?? [];
            // Prefer output_text when present
            $outputText = (string) data_get($raw, 'output_text', '');

            // Fallback: try first text item in output[]
            if ($outputText === '') {
                $outputText = (string) data_get($raw, 'output.0.content.0.text', '');
            }

            if (in_array($status, ['queued', 'in_progress'])) {
                // not ready yet; try again in ~1 min
                $this->release(60);
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
                // Your prompt says: "Return ONLY valid JSON"
                // Safely decode:
                $decoded = json_decode($outputText, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
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

                $row->update([
                    'summary_status'       => 'completed',
                    'summary_error'        => null,
                    'summary_payload'      => $decoded,      // store the structured JSON
                    'summary_completed_at' => now(),
                ]);

                return;
            }

            // Unknown state => retry a bit later
            $this->release(60);
        }, function () {
            return $this->release(60); // If locked, retry in 30 seconds
        });
    }
}
