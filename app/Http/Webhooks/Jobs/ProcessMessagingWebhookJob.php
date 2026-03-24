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
        Redis::throttle('messages')->allow(2)->every(1)->then(function () use ($pipeline) {
            foreach ($this->parser()->parse($this->webhookCall) as $event) {
                $pipeline->handle($event, $this->parser());
            }
        }, function () {
            $this->release(5);
        });
    }
}