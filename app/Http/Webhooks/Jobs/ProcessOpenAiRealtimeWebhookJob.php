<?php

namespace App\Http\Webhooks\Jobs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;
use Spatie\WebhookClient\Models\WebhookCall;

class ProcessOpenAiRealtimeWebhookJob extends SpatieProcessWebhookJob
{
    public $connection = 'sync';
    public $tries = 1;
    public $timeout = 10;

    public function __construct(public WebhookCall $webhookCall) {}

    public function handle(): void
    {
        $event = $this->webhookCall->payload;

        if (! is_array($event) || ($event['type'] ?? null) !== 'realtime.call.incoming') {
            return;
        }

        $eventId = (string) ($event['id'] ?? '');
        if ($eventId !== '' && ! Cache::add('openai-realtime-event:' . $eventId, true, now()->addHour())) {
            return;
        }

        $callId = data_get($event, 'data.call_id');
        if (blank($callId)) {
            throw new RuntimeException('OpenAI Realtime webhook is missing data.call_id.');
        }

        $response = Http::withToken((string) config('services.ai_receptionist.agent_token'))
            ->acceptJson()
            ->timeout(5)
            ->post((string) config('services.ai_receptionist.controller_url'), [
                'event_id' => $eventId ?: null,
                'call_id' => $callId,
                'sip_headers' => data_get($event, 'data.sip_headers', []),
                'event' => $event,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'AI Receptionist controller did not accept OpenAI Realtime call. Status: %s Body: %s',
                $response->status(),
                $response->body()
            ));
        }
    }
}
