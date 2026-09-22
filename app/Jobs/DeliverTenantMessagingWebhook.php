<?php

namespace App\Jobs;

use App\Models\Messages;
use App\Services\Messaging\MessagingWebhookSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DeliverTenantMessagingWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Initial attempt plus retries at 1m, 5m, 15m, 30m, and 2h. */
    public int $tries = 6;
    public array $backoff = [60, 300, 900, 1800, 7200];
    public int $timeout = 30;

    public function __construct(
        public string $messageUuid,
        public string $event,
        public string $eventUuid = '',
    ) {
        $this->eventUuid = $eventUuid ?: (string) Str::uuid();
    }

    public function handle(MessagingWebhookSettings $settings): void
    {
        $message = Messages::find($this->messageUuid);
        if (! $message) {
            return;
        }

        $config = $settings->get($message->domain_uuid);
        if (! $config['webhook_enabled'] || blank($config['webhook_url'])) {
            return;
        }

        $payload = [
            'event' => $this->event,
            'event_uuid' => $this->eventUuid,
            'domain_uuid' => $message->domain_uuid,
            'message_uuid' => $message->message_uuid,
            'message_group_uuid' => $message->message_group_uuid,
            'direction' => $message->direction,
            'from' => $message->source,
            'to' => $message->destination,
            'text' => $message->message,
            'type' => $message->type,
            'status' => $message->status,
            'media' => collect($message->media ?? [])->map(function (array $media, int $index) use ($message) {
                return [
                    'name' => $media['original_name'] ?? $media['stored_name'] ?? 'attachment',
                    'content_type' => $media['mime_type'] ?? null,
                    'url' => route('messages.media.show', [
                        'message_uuid' => $message->message_uuid,
                        'index' => $index,
                        'file_name' => $media['stored_name'] ?? ('file_'.$index),
                    ]),
                ];
            })->values()->all(),
            'timestamp' => $message->updated_at?->toIso8601String(),
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $secret = $settings->signingSecret($message->domain_uuid) ?? '';

        Http::timeout(15)->withHeaders([
            'Content-Type' => 'application/json',
            'X-FSPBX-Event' => $this->event,
            'X-FSPBX-Event-Id' => $this->eventUuid,
            'X-FSPBX-Timestamp' => $timestamp,
            'X-FSPBX-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret),
        ])->withBody($body, 'application/json')->post($config['webhook_url'])->throw();
    }
}
