<?php

namespace App\Services\Messaging;

use App\Jobs\DeliverMessageToEmail;
use App\Jobs\DeliverMessageToRingotel;
use App\Services\Messaging\Data\DeliveryStatusEventData;
use App\Services\Messaging\Data\InboundMessageEventData;
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
        messaging_webhook_debug('InboundMessagePipeline handle()', [
            'event_class' => get_class($event),
        ]);

        match (true) {
            $event instanceof InboundMessageEventData => $this->handleInbound($event, $parser),
            $event instanceof DeliveryStatusEventData => $this->handleStatus($event),
            default => null,
        };
    }

    protected function handleInbound(InboundMessageEventData $event, MessagingWebhookParser $parser): void
    {
        messaging_webhook_debug('handleInbound started', [
            'provider' => $event->provider,
            'from' => $event->from,
            'to' => $event->to,
            'media_count' => count($event->mediaUrls ?? []),
            'provider_reference_id' => $event->providerReferenceId,
        ]);

        foreach (array_filter($event->to) as $destination) {
            messaging_webhook_debug('Resolving destination', [
                'destination' => $destination,
            ]);

            $route = $this->resolver->resolve($destination);

            messaging_webhook_debug('Destination resolved', [
                'domain_uuid' => $route->domainUuid,
                'extension_uuid' => $route->extensionUuid,
                'extension' => $route->extension,
                'has_mobile_app' => $route->hasMobileApp,
                'email' => $route->email,
                'org_id' => $route->orgId,
            ]);

            $storedMedia = $this->mediaIngestor->store(
                parser: $parser,
                provider: $event->provider,
                domainUuid: $route->domainUuid,
                mediaUrls: $event->mediaUrls,
            );

            messaging_webhook_debug('Media ingested', [
                'stored_media_count' => count($storedMedia),
            ]);

            $message = $this->messages->storeInbound(
                domainUuid: $route->domainUuid,
                extensionUuid: $route->extensionUuid,
                source: $event->from,
                destination: $route->destination,
                text: $event->text,
                type: !empty($event->mediaUrls) ? 'mms' : 'sms',
                providerName: $event->provider,
                providerReferenceId: $event->providerReferenceId,
                media: $storedMedia,
                providerEvent: $event->providerEvent,
            );

            messaging_webhook_debug('Message saved', [
                'message_uuid' => $message->message_uuid,
            ]);

            if ($route->hasMobileApp && $route->orgId && $route->extension) {
                DeliverMessageToRingotel::dispatch(
                    $message->message_uuid,
                    $route->orgId,
                    $route->extension,
                )->onQueue('messages');

                messaging_webhook_debug('Ringotel delivery queued', [
                    'message_uuid' => $message->message_uuid,
                ]);
            }

            if ($route->email) {
                DeliverMessageToEmail::dispatch(
                    $message->message_uuid,
                    $route->orgId,
                    $route->email,
                )->onQueue('emails');

                messaging_webhook_debug('Email delivery queued', [
                    'message_uuid' => $message->message_uuid,
                    'email' => $route->email,
                ]);
            }
        }
    }

    protected function handleStatus(DeliveryStatusEventData $event): void
    {
        messaging_webhook_debug('handleStatus started', [
            'provider' => $event->provider,
            'reference_id' => $event->referenceId,
            'status' => $event->status,
        ]);


        $this->messages->applyProviderStatus(
            provider: $event->provider,
            referenceId: $event->referenceId,
            status: $event->status,
            description: $event->description,
            providerEvent: $event->providerEvent,
        );
    }
}
