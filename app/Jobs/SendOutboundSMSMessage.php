<?php

namespace App\Jobs;

use App\Models\Messages;
use App\Services\Messaging\MessageRepository;
use App\Services\Messaging\RingotelSyncDispatcher;
use App\Services\Messaging\Outbound\OutboundProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use RuntimeException;

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
        return [
            new RateLimitedWithRedis('messages'),
            (new WithoutOverlapping('outbound-message:'.$this->messageUuid))->releaseAfter(5)->expireAfter(180),
        ];
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

            // A replay of this job after carrier acceptance may only retry synchronization.
            if (RingotelSyncDispatcher::carrierAccepted($message)) {
                app(RingotelSyncDispatcher::class)->dispatch($message);
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
                $messages->markOutboundFailure(
                    $message,
                    'unknown',
                    'No outbound provider stored on the message'
                );

                return;
            }

            try {
                app(\App\Services\Messaging\OutboundPhotoService::class)->prepare($message);
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
                if ($result->success) {
                    app(RingotelSyncDispatcher::class)->dispatch($message);
                }
            } catch (\App\Services\Messaging\PhotoCompressionBusy $e) {
                if ($this->attempts() >= $this->tries) {
                    $messages->markOutboundFailure($message, $carrier, __('Photo compression is busy. Please retry the message.'));
                } else {
                    $this->release(10);
                }
            } catch (RuntimeException $e) {
                logger('Outbound provider resolution failed: ' . $e->getMessage());

                $messages->markOutboundFailure(
                    $message,
                    $carrier,
                    $e->getMessage()
                );
            } catch (\Throwable $e) {
                logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

                $messages->markOutboundFailure(
                    $message,
                    $carrier,
                    $e->getMessage()
                );

                throw $e;
            }
        }, function () {
            messaging_webhook_debug('SendOutboundMessage throttled', [
                'message_uuid' => $this->messageUuid,
            ]);

            $this->release(5);
        });
    }
}
