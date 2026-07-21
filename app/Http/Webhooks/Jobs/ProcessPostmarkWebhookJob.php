<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\EmailLog;
use App\Services\FaxSendService;
use Illuminate\Support\Carbon;
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
        $this->queue = $this->isOutboundEmailEvent($webhookCall->payload ?? []) ? 'emails' : 'faxes';
        $this->webhookCall = $webhookCall;
    }

    public function handle()
    {
        if ($this->isOutboundEmailEvent($this->webhookCall->payload ?? [])) {
            $this->processOutboundEmailEvent($this->webhookCall->payload ?? []);

            return;
        }

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

    private function processOutboundEmailEvent(array $payload): void
    {
        $messageId = (string) ($payload['MessageID'] ?? '');
        $metadataLogId = (string) data_get($payload, 'Metadata.email_log_uuid', '');
        $recipient = (string) ($payload['Recipient'] ?? $payload['Email'] ?? '');
        $eventAt = $this->eventTimestamp($payload);

        $log = $this->findEmailLogForOutboundEvent($metadataLogId, $messageId, $recipient, $eventAt);

        if (! $log) {
            logger('ProcessPostmarkWebhookJob: email log not found for outbound event', [
                'webhook_call_id' => $this->webhookCall->id ?? null,
                'message_id' => $messageId,
                'metadata_email_log_uuid' => $metadataLogId ?: null,
                'recipient' => $recipient ?: null,
                'record_type' => $payload['RecordType'] ?? null,
                'type' => $payload['Type'] ?? null,
                'event_at' => $eventAt?->toDateTimeString(),
            ]);

            return;
        }

        $updates = [
            'provider' => 'postmark',
            'provider_message_id' => $messageId ?: $log->provider_message_id,
            'provider_message_stream' => $payload['MessageStream'] ?? $log->provider_message_stream,
            'sent_debug_info' => $this->eventSummary($payload),
        ];

        $status = $this->statusForOutboundEvent($payload);

        if ($status !== null) {
            $updates['status'] = $status;
        }

        if ($status === 'sent' && in_array($log->status, ['failed', 'permanent_failed'], true)) {
            unset($updates['status']);
        }

        $log->forceFill($updates)->save();
    }

    private function findEmailLogForOutboundEvent(
        string $metadataLogId,
        string $messageId,
        string $recipient,
        ?Carbon $eventAt = null
    ): ?EmailLog
    {
        if ($metadataLogId !== '') {
            $log = EmailLog::query()->where('uuid', $metadataLogId)->first();

            if ($log) {
                return $log;
            }
        }

        if ($messageId !== '') {
            $log = EmailLog::query()->where('provider_message_id', $messageId)->first();

            if ($log) {
                return $log;
            }
        }

        if ($recipient === '') {
            return null;
        }

        $query = EmailLog::query()
            ->where('provider', 'postmark')
            ->where('to', 'ILIKE', '%' . $recipient . '%')
            ->where(function ($query) use ($messageId) {
                $query->whereNull('provider_message_id')
                    ->orWhere('provider_message_id', '');

                if ($messageId !== '') {
                    $query->orWhere('provider_message_id', $messageId);
                }
            });

        if ($eventAt) {
            $query
                ->where('created_at', '>=', $eventAt->copy()->subHours(6))
                ->where('created_at', '<=', $eventAt->copy()->addMinutes(10));
        } else {
            $query->where('created_at', '>=', Carbon::now()->subHours(6));
        }

        return $query->latest('created_at')->first();
    }

    private function eventTimestamp(array $payload): ?Carbon
    {
        foreach (['DeliveredAt', 'BouncedAt', 'ReceivedAt', 'OpenedAt', 'ClickedAt'] as $field) {
            if (empty($payload[$field])) {
                continue;
            }

            try {
                return Carbon::parse($payload[$field]);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function statusForOutboundEvent(array $payload): ?string
    {
        $recordType = $payload['RecordType'] ?? null;

        if ($recordType === 'Delivery') {
            return 'sent';
        }

        if ($recordType === 'Transient') {
            return 'failed';
        }

        if ($recordType === 'SpamComplaint') {
            return 'permanent_failed';
        }

        if ($recordType === 'Bounce') {
            $bounceType = strtolower((string) ($payload['Type'] ?? ''));

            return in_array($bounceType, ['softbounce', 'transient', 'smtpapierror', 'undetermined'], true)
                ? 'failed'
                : 'permanent_failed';
        }

        return null;
    }

    private function eventSummary(array $payload): string
    {
        $parts = array_filter([
            'Postmark ' . ($payload['RecordType'] ?? 'event'),
            $payload['Type'] ?? null,
            $payload['Name'] ?? null,
            $payload['Description'] ?? null,
            $payload['Details'] ?? null,
        ]);

        return implode(': ', $parts);
    }

    private function isOutboundEmailEvent(array $payload): bool
    {
        return in_array($payload['RecordType'] ?? null, [
            'Delivery',
            'Transient',
            'Bounce',
            'SpamComplaint',
        ], true) && !empty($payload['MessageID']);
    }
}
