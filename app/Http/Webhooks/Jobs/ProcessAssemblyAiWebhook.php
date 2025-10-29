<?php
namespace App\Http\Webhooks\Jobs;

use App\Models\CallTranscription;
use App\Services\CallTranscription\CallTranscriptionService;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessAssemblyAiWebhook extends SpatieProcessWebhookJob
{

    public $tries   = 10;
    public $backoff = [10, 30, 60, 120, 300];

    public function __construct(public WebhookCall $webhookCall) {}

    public function handle(CallTranscriptionService $service): void
    {
        logger('ProcessAssemblyAiWebhook');

        $payload = $this->webhookCall->payload;

        $transcriptId = data_get($payload, 'transcript_id');
        $status       = data_get($payload, 'status'); // completed|error

        logger($status);

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

            $row->update([
                'status'          => 'completed',
                'result_payload'  => $full ?: null,
                'completed_at'    => now(),
                'error_message'   => null,
            ]);
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

    private function webhookTranscriptId(): string
    {
        return (string) data_get($this->webhookCall->payload, 'transcript_id', '');
    }
}
