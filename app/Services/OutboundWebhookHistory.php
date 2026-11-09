<?php

namespace App\Services;

use App\Models\OutboundWebhookLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\WebhookServer\Events\DispatchingWebhookCallEvent;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;

class OutboundWebhookHistory
{
    public function record(DispatchingWebhookCallEvent|WebhookCallEvent $event): void
    {
        if (! Schema::hasTable('outbound_webhook_logs')) {
            return;
        }

        $attributes = $this->attributes(
            $event->uuid, $event->webhookUrl, $event->meta['domain_uuid'] ?? null,
            $event->meta['event_type'] ?? data_get($event->payload, 'data.event_type')
                ?? data_get($event->payload, 'type') ?? 'webhook',
            $event->meta['message_uuid'] ?? $event->meta['target_uuid'] ?? null,
            $event->httpVerb,
        );

        DB::transaction(function () use ($attributes, $event) {
            $row = OutboundWebhookLog::firstOrCreate(
                ['delivery_key' => $attributes['delivery_key']], $attributes
            );
            if ($event instanceof DispatchingWebhookCallEvent) {
                return;
            }

            $row = OutboundWebhookLog::whereKey($row->getKey())->lockForUpdate()->firstOrFail();
            $attempt = max(1, $event->attempt);
            $status = $event instanceof WebhookCallSucceededEvent ? 'delivered'
                : ($event instanceof FinalWebhookCallFailedEvent ? 'failed' : 'retrying');
            // Late failures or repeated dispatch events must not undo success.
            if ($row->status === 'delivered' || $attempt < $row->attempts
                || ($row->status === 'failed' && $attempt === $row->attempts && $status === 'retrying')) {
                return;
            }

            $httpStatus = $event->response?->getStatusCode();
            $error = $status === 'delivered' ? null : ($httpStatus
                ? "Endpoint returned HTTP {$httpStatus}."
                : 'No HTTP response received.');
            $history = collect($row->attempt_history)->keyBy('attempt');
            $history->put($attempt, [
                'attempt' => $attempt, 'status' => $status, 'http_status' => $httpStatus,
                'error' => $error, 'attempted_at' => now()->toIso8601String(),
            ]);
            $row->update([
                'status' => $status, 'attempts' => $attempt, 'http_status' => $httpStatus,
                'error' => $error, 'attempted_at' => now(),
                'attempt_history' => $history->sortKeys()->values()->all(),
            ]);
        });
    }

    private function attributes(string $id, string $url, ?string $domain, string $type, ?string $relatedId, string $method): array
    {
        return [
            'delivery_key' => hash('sha256', json_encode([$domain, $id, $url])),
            'domain_uuid' => Str::isUuid($domain ?? '') ? $domain : null,
            'event_id' => Str::limit($id, 255, ''), 'event_type' => Str::limit($type, 255, ''),
            'related_id' => $relatedId ? Str::limit($relatedId, 255, '') : null,
            'http_method' => Str::limit(strtoupper($method), 10, ''),
            // Do not persist signing headers, credentials, payloads, or endpoint query secrets.
            'endpoint_url' => $this->displayUrl($url), 'attempt_history' => [],
        ];
    }

    private function displayUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) return '';
        return ($parts['scheme'] ?? 'https').'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '')
            .(isset($parts['query']) ? '?[redacted]' : '');
    }
}
