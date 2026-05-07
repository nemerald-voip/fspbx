<?php

namespace App\Http\Webhooks\Jobs;

use App\Services\FaxSendService;
use Illuminate\Support\Facades\Redis;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessPostmarkWebhookJob extends SpatieProcessWebhookJob
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

            fax_webhook_debug('ProcessPostmarkWebhookJob: processing email-to-fax webhook', [
                'webhook_call_id'   => $this->webhookCall->id ?? null,
                'from'              => $payload['from'] ?? null,
                'fax_uuid'          => $payload['fax_uuid'] ?? null,
                'fax_destination'   => $payload['fax_destination'] ?? null,
                'attachment_count'  => count($payload['fax_attachments'] ?? []),
            ]);

            $result = FaxSendService::send([
                'fax_destination' => $payload['fax_destination'],
                'from'            => $payload['from'],
                'subject'         => $payload['subject'] ?? '',
                'body'            => $payload['body'] ?? '',
                'attachments'     => $payload['fax_attachments'] ?? [],
                'fax_uuid'        => $payload['fax_uuid'],
            ]);

            fax_webhook_debug('ProcessPostmarkWebhookJob: FaxSendService completed', [
                'webhook_call_id'   => $this->webhookCall->id ?? null,
                'result'            => $result,
                'fax_destination'   => $payload['fax_destination'] ?? null,
            ]);
        }, function () {
            fax_webhook_debug('ProcessPostmarkWebhookJob: fax throttle busy, releasing', [
                'webhook_call_id' => $this->webhookCall->id ?? null,
                'queue_attempt'   => $this->attempts(),
                'release_seconds' => 5,
            ]);

            return $this->release(5);
        });
    }
}
