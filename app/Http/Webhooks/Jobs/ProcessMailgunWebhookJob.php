<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\Faxes;
use App\Services\FaxSendService;
use Illuminate\Support\Facades\Redis;
use Spatie\WebhookClient\Models\WebhookCall;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessMailgunWebhookJob extends SpatieProcessWebhookJob
{

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 15;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [(new RateLimitedWithRedis('fax'))];
    }

    public function __construct(WebhookCall $webhookCall)
    {
        $this->queue = 'faxes';
        $this->webhookCall = $webhookCall;
    }


    public function handle()
    {
        Redis::throttle('fax')->allow(2)->every(1)->then(function () {

            $payload = $this->webhookCall->payload;

            // logger($this->webhookCall->payload);

            // 1. Extract sender and recipient
            $sender = $this->extractEmail($payload['from'] ?? $payload['From'] ?? null);

            // 2. Extract subject and message body
            $subject = $payload['subject'] ?? $payload['Subject'] ?? '';
            $body = $payload['body-plain'] ?? $payload['stripped-text'] ?? $payload['body-html'] ?? '';

            // Example payload for fax service
            $faxPayload = [
                'fax_destination' => $payload['fax_destination'],        // e.g. "6313182913@fax.fspbx.com"
                'from' => $sender,         // e.g. "dexter@stellarvoip.com"
                'subject' => $subject,
                'body' => $body,
                'attachments' => $this->webhookCall->payload['fax_attachments'] ?? null,
                'fax_uuid' => $payload['fax_uuid'],
            ];

            // logger($faxPayload);
            FaxSendService::send($faxPayload);


            return "ok";
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(5);
        });
    }

    function extractEmail($raw)
    {
        if (!$raw || !is_string($raw)) return null;
        if (preg_match('/<([^>]+)>/', $raw, $matches)) {
            return strtolower(trim($matches[1]));
        }
        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return strtolower(trim($raw));
        }
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $raw, $matches)) {
            return strtolower(trim($matches[0]));
        }
        return null;
    }
}
