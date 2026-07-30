<?php

namespace App\Services\Messaging;

use App\Http\Webhooks\Jobs\ProcessFiberneticsWebhookJob;
use App\Models\WhCall;
use App\Services\MessageMediaObjectStorageService;

class FiberneticsMmsWebhookQueueService
{
    public function __construct(
        private readonly MessageDestinationResolver $resolver,
        private readonly MessageMediaObjectStorageService $mediaStorage,
        private readonly MessageRepository $messages,
    ) {}

    public function queue(WhCall $webhookCall, array $media): void
    {
        $payload = $webhookCall->payload ?? [];
        $referenceId = (string) ($payload['transaction_id'] ?? '');
        $storedMediaByDestination = [];

        if ($referenceId === ''
            || ! $this->messages->inboundReferenceExists('fibernetics', $referenceId)) {
            $storedMediaByDomain = [];

            foreach (array_filter($payload['recipients'] ?? []) as $recipient) {
                $route = $this->resolver->resolve((string) $recipient);

                if (! array_key_exists($route->domainUuid, $storedMediaByDomain)) {
                    $storedMediaByDomain[$route->domainUuid] = $this->storeMedia(
                        $route->domainUuid,
                        $media
                    );
                }

                $storedMediaByDestination[$route->destination] =
                    $storedMediaByDomain[$route->domainUuid];
            }
        }

        $payload['stored_media'] = $storedMediaByDestination;
        $webhookCall->payload = $payload;
        $webhookCall->save();

        ProcessFiberneticsWebhookJob::dispatch($webhookCall)->onQueue('messages');
    }

    private function storeMedia(string $domainUuid, array $media): array
    {
        $stored = [];

        foreach ($media as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $stored[] = $this->mediaStorage->storeBinary(
                domainUuid: $domainUuid,
                binary: (string) ($attachment['binary'] ?? ''),
                originalName: (string) ($attachment['original_name'] ?? 'attachment.bin'),
                provider: 'fibernetics',
                mimeType: $attachment['mime_type'] ?? null,
            );
        }

        return $stored;
    }
}
