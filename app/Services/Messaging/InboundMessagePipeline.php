<?php

namespace App\Services\Messaging;

use App\Jobs\DeliverMessageToEmail;
use App\Jobs\DeliverMessageToRingotel;
use App\Services\Messaging\DTO\DeliveryStatusEvent;
use App\Services\Messaging\DTO\InboundMessageEvent;
use App\Services\Messaging\Providers\MessagingWebhookParser;

class InboundMessagePipeline
{
    public function __construct(
        protected MessageDestinationResolver $resolver,
        protected MessageMediaIngestor $mediaIngestor,
        protected MessageRepository $messages,
    ) {}

    public function handle(object $event, MessagingWebhookParser $parser): void
    {
        match (true) {
            $event instanceof InboundMessageEvent => $this->handleInbound($event, $parser),
            $event instanceof DeliveryStatusEvent => $this->handleStatus($event),
            default => null,
        };
    }

    protected function handleInbound(InboundMessageEvent $event, MessagingWebhookParser $parser): void
    {
        foreach ($event->to as $destination) {
            $route = $this->resolver->resolve($destination);

            $media = $this->mediaIngestor->store(
                parser: $parser,
                provider: $event->provider,
                domainUuid: $route->domainUuid,
                mediaUrls: $event->mediaUrls,
            );

            $message = $this->messages->storeInbound(
                domainUuid: $route->domainUuid,
                extensionUuid: $route->extensionUuid,
                source: $event->from,
                destination: $route->destination,
                text: $event->text,
                type: !empty($event->mediaUrls) ? 'mms' : 'sms',
                providerName: $event->provider,
                providerReferenceId: $event->providerReferenceId,
                media: $media,
                providerEvent: $event->providerEvent,
            );

            if ($route->hasMobileApp && $route->orgId && $route->extension) {
                DeliverMessageToRingotel::dispatch(
                    $message->message_uuid,
                    $route->orgId,
                    $route->extension
                )->onQueue('messages');
            }

            if ($route->email) {
                DeliverMessageToEmail::dispatch(
                    $message->message_uuid,
                    $route->orgId,
                    $route->email
                )->onQueue('emails');
            }
        }
    }

    protected function handleStatus(DeliveryStatusEvent $event): void
    {
        $this->messages->applyProviderStatus(
            provider: $event->provider,
            referenceId: $event->referenceId,
            status: $event->status,
            description: $event->description,
            providerEvent: $event->providerEvent,
        );
    }
}