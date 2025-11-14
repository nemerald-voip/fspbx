<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\CallTranscription;
use App\Services\CallTranscription\CallTranscriptionService;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessAssemblyAiWebhookJob extends SpatieProcessWebhookJob
{

    public $tries   = 10;
    public $backoff = [10, 30, 60, 120, 300];
    public $timeout = 300;
    public $maxExceptions = 5;

    public function __construct(public WebhookCall $webhookCall) {}

    public function handle(CallTranscriptionService $service): void
    {

        $payload = $this->webhookCall->payload;

        $transcriptId = data_get($payload, 'transcript_id');
        $status       = data_get($payload, 'status'); // completed|error

        if (!$transcriptId) {
            // nothing to do
            return;
        }

        $row = CallTranscription::query()->where('external_id', $transcriptId)->first();
        if (!$row) {
            return;
        }

        if ($status === 'completed') {
            // fetch final transcript JSON from provider
            $provider = $service->providerForScope($row->domain_uuid);
            $full     = $provider->fetchTranscript($transcriptId);

            // Remove heavy fields like "words" everywhere in the structure
            $sanitized = $full ? $this->deepUnsetKeys($full, ['words']) : null;

            $row->update([
                'status'          => 'completed',
                'result_payload'  => $sanitized ?: null,
                'completed_at'    => now(),
                'error_message'   => null,
                'summary_status'       => 'pending',
                'summary_error'        => null,
                'summary_requested_at' => now(),
            ]);

            // Dispatch the job for summaries
            dispatch(new \App\Jobs\SummarizeCallTranscription($row->uuid))->onQueue('transcriptions');
        } elseif ($status === 'error') {
            // Pull the current transcript for error details if useful
            $provider = $service->providerForScope($row->domain_uuid);
            $current  = $provider->fetchTranscript($transcriptId);

            $row->update([
                'status'         => 'failed',
                'error_message'  => $current['error'] ?? 'Transcription failed.',
                'result_payload' => $current ?: null,
                'completed_at'   => now(),
            ]);
        }

        // You can emit events/notifications here if you’d like.
    }

    /**
     * Recursively remove keys from arrays/objects (e.g., "words", "tokens", etc.).
     */
    function deepUnsetKeys(mixed $data, array $keysToRemove = ['words']): mixed
    {
        // Normalize to array for processing; preserve objects on return
        $isObject = is_object($data);
        $arr = json_decode(json_encode($data, JSON_UNESCAPED_UNICODE), true);

        $walker = function (&$value) use (&$walker, $keysToRemove) {
            if (is_array($value)) {
                // remove target keys at this level
                foreach ($keysToRemove as $k) {
                    if (array_key_exists($k, $value)) {
                        unset($value[$k]);
                    }
                }
                // descend
                foreach ($value as &$v) {
                    $walker($v);
                }
            }
        };
        $walker($arr);

        // Return same type we received (array or object)
        return $isObject ? json_decode(json_encode($arr)) : $arr;
    }
}
