<?php

namespace App\Http\Webhooks\Jobs;

use App\Services\FaxSendService;
use Illuminate\Support\Facades\Redis;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessMailgunWebhookJob extends SpatieProcessWebhookJob
{
    public $tries = 10;
    public $maxExceptions = 5;
    public $timeout = 120;
    public $failOnTimeout = true;
    public $backoff = 15;
    public $deleteWhenMissingModels = true;

    public function __construct(WebhookCall $webhookCall)
    {
        $this->queue = 'faxes';
        $this->webhookCall = $webhookCall;
    }

    public function handle()
    {
        // Allow at most 2 fax dispatches per second across the cluster.
        Redis::throttle('fax')->allow(2)->every(1)->then(function () {
            $payload = $this->webhookCall->payload;

            FaxSendService::send([
                'fax_destination' => $payload['fax_destination'],
                'from'            => $payload['from'],
                'subject'         => $payload['subject'] ?? '',
                'body'            => $payload['body'] ?? '',
                'attachments'     => $payload['fax_attachments'] ?? [],
                'fax_uuid'        => $payload['fax_uuid'],
            ]);
        }, function () {
            return $this->release(5);
        });
    }
}
