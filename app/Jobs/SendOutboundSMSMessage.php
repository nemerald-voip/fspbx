<?php

namespace App\Jobs;

use App\Models\Messages;
use App\Services\Messaging\MessageRepository;
use App\Services\Messaging\Outbound\OutboundProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;

class SendOutboundSMSMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 10;
    public $maxExceptions = 5;
    public $timeout = 120;
    public $failOnTimeout = true;
    public $backoff = 30;
    public $deleteWhenMissingModels = true;

    public function __construct(public string $messageUuid) {}

    public function middleware(): array
    {
        return [new RateLimitedWithRedis('messages')];
    }

    public function handle(
        OutboundProviderFactory $factory,
        MessageRepository $messages
    ): void {
        messaging_webhook_debug('SendOutboundMessage started', [
            'message_uuid' => $this->messageUuid,
        ]);

        Redis::throttle('messages')->allow(2)->every(1)->then(function () use ($factory, $messages) {
            $message = Messages::find($this->messageUuid);

            if (!$message) {
                messaging_webhook_debug('SendOutboundMessage message not found', [
                    'message_uuid' => $this->messageUuid,
                ]);
                return;
            }

            $carrier = data_get($message->delivery_meta, 'outbound.provider.name');

            messaging_webhook_debug('SendOutboundMessage resolved carrier', [
                'message_uuid' => $this->messageUuid,
                'carrier' => $carrier,
                'direction' => $message->direction,
                'type' => $message->type,
            ]);

            if (!$carrier) {
                throw new \RuntimeException("Outbound provider not stored on message {$this->messageUuid}");
            }

            $provider = $factory->make($carrier);

            messaging_webhook_debug('SendOutboundMessage resolved provider instance', [
                'message_uuid' => $this->messageUuid,
                'provider_class' => get_class($provider),
            ]);

            $result = $provider->send($message);

            messaging_webhook_debug('SendOutboundMessage provider result', [
                'message_uuid' => $this->messageUuid,
                'success' => $result->success,
                'status' => $result->status,
                'provider_reference_id' => $result->providerReferenceId,
                'error' => $result->error,
            ]);

            $messages->applyOutboundSendResult($message, $carrier, $result);
        }, function () {
            messaging_webhook_debug('SendOutboundMessage throttled', [
                'message_uuid' => $this->messageUuid,
            ]);

            $this->release(5);
        });
    }
}
