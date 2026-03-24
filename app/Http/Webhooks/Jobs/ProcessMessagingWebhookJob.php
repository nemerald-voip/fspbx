<?php

namespace App\Http\Webhooks\Jobs;

use App\Services\Messaging\InboundMessagePipeline;
use App\Services\Messaging\Providers\MessagingWebhookParser;
use Illuminate\Support\Facades\Redis;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

abstract class ProcessMessagingWebhookJob extends SpatieProcessWebhookJob
{
    public $tries = 10;
    public $maxExceptions = 5;
    public $timeout = 120;
    public $failOnTimeout = true;
    public $backoff = 15;
    public $deleteWhenMissingModels = true;

    abstract protected function parser(): MessagingWebhookParser;

    public function handle(InboundMessagePipeline $pipeline): void
    {
        messaging_webhook_debug('Webhook job started', [
            'job' => static::class,
            'webhook_call_id' => $this->webhookCall->id ?? null,
            'webhook_name' => $this->webhookCall->name ?? null,
        ]);

        Redis::throttle('messages')->allow(2)->every(1)->then(function () use ($pipeline) {
            $parser = $this->parser();

            messaging_webhook_debug('Webhook parser resolved', [
                'parser' => get_class($parser),
            ]);

            $events = iterator_to_array($parser->parse($this->webhookCall), false);

            messaging_webhook_debug('Webhook parser completed', [
                'event_count' => count($events),
                'event_types' => collect($events)->map(fn($event) => get_class($event))->all(),
            ]);

            foreach ($events as $event) {
                try {
                    messaging_webhook_debug('Sending event to pipeline', [
                        'event_class' => get_class($event),
                    ]);

                    $pipeline->handle($event, $parser);

                    messaging_webhook_debug('Pipeline handled event', [
                        'event_class' => get_class($event),
                    ]);
                } catch (\Throwable $e) {
                    logger('ProcessMessagingWebhookJob@handle Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                    throw $e;
                }
            }
        }, function () {
            messaging_webhook_debug('Webhook job throttled, releasing job');
            $this->release(5);
        });
    }
}
