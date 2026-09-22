<?php

namespace App\Services\Messaging;

use App\Jobs\DeliverMessageToEmail;
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
        if ($event->provider !== 'sinch' && $event->providerReferenceId
            && $this->messages->inboundReferenceExists($event->provider, $event->providerReferenceId)) {
            messaging_webhook_debug('Inbound message event already processed', [
                'provider' => $event->provider,
                'provider_reference_id' => $event->providerReferenceId,
            ]);

            return;
        }

        $this->handleResolvedInbound(
            event: $event,
            storeMedia: function ($route) use ($event, $parser): array {
                if ($event->storedMedia !== []) {
                    return $event->storedMedia[$route->destination] ?? [];
                }

                return $this->mediaIngestor->store(
                    parser: $parser,
                    provider: $event->provider,
                    domainUuid: $route->domainUuid,
                    mediaUrls: $event->mediaUrls,
                );
            },
            isMms: $event->isMms
                || ! empty($event->mediaUrls)
                || ! empty($event->storedMedia),
        );
    }

    private function handleResolvedInbound(
        InboundMessageEventData $event,
        callable $storeMedia,
        bool $isMms
    ): void {
        messaging_webhook_debug('handleInbound started', [
            'provider' => $event->provider,
            'from' => $event->from,
            'to' => $event->to,
            'media_count' => count($event->mediaUrls ?? []),
            'provider_reference_id' => $event->providerReferenceId,
        ]);

        foreach (array_unique(array_filter($event->to)) as $destination) {
            // Inteliquent includes external group participants in `to` too.
            if ($event->provider === 'sinch' && !$this->resolver->isLocal($destination)) continue;
            messaging_webhook_debug('Resolving destination', [
                'destination' => $destination,
            ]);

            $route = $this->resolver->resolve($destination);
            $local = app(MessageParticipantService::class)->normalize($route->domainUuid, $route->destination);
            if ($event->providerReferenceId && \App\Models\Messages::where('domain_uuid', $route->domainUuid)
                ->where('direction', 'in')->where('destination', $local)
                ->where('reference_id', $event->providerReferenceId)
                ->where('delivery_meta->provider->name', $event->provider)->exists()) continue;
            $group = null;
            if ($event->provider === 'sinch') {
                $groups = app(MessageGroupService::class);
                $recipients = $groups->recipients(array_merge([$event->from], $event->to), $local,
                    get_domain_setting('country', $route->domainUuid) ?? 'US');
                if (count($recipients) > 1) $group = $groups->findOrCreate($route->domainUuid, $local, $recipients);
            }

            messaging_webhook_debug('Destination resolved', [
                'domain_uuid' => $route->domainUuid,
                'extension_uuid' => $route->extensionUuid,
                'extension' => $route->extension,
                'has_mobile_app' => $route->hasMobileApp,
                'email' => $route->email,
                'org_id' => $route->orgId,
            ]);

            $storedMedia = $storeMedia($route);

            messaging_webhook_debug('Media ingested', [
                'stored_media_count' => count($storedMedia),
            ]);

            $message = $this->messages->storeInbound(
                domainUuid: $route->domainUuid,
                extensionUuid: $route->extensionUuid,
                source: $event->from,
                destination: $route->destination,
                text: $event->text,
                type: $group || $isMms ? 'mms' : 'sms',
                providerName: $event->provider,
                providerReferenceId: $event->providerReferenceId,
                media: $storedMedia,
                providerEvent: $event->providerEvent,
                messageGroupUuid: $group?->message_group_uuid,
            );

            messaging_webhook_debug('Message saved', [
                'message_uuid' => $message->message_uuid,
            ]);

            if (app(RingotelSyncDispatcher::class)->dispatch($message)) {

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
