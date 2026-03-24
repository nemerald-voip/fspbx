<?php

namespace App\Services\Messaging;

use App\Models\Messages;

class MessageRepository
{
    public function storeInbound(
        string $domainUuid,
        ?string $extensionUuid,
        string $source,
        string $destination,
        string $text,
        string $type,
        ?string $providerName,
        ?string $providerReferenceId,
        array $media = [],
        ?string $providerEvent = null,
    ): Messages {
        $message = Messages::create([
            'domain_uuid'    => $domainUuid,
            'extension_uuid' => $extensionUuid,
            'source'         => $source,
            'destination'    => $destination,
            'message'        => $text,
            'direction'      => 'in',
            'type'           => $type,
            'reference_id'   => $providerReferenceId, // legacy
            'status'         => 'received',           // legacy
            'media'          => $media,
            'delivery_meta'  => [
                'provider' => [
                    'name'         => $providerName,
                    'reference_id' => $providerReferenceId,
                    'status'       => 'received',
                    'received_at'  => now()->toIso8601String(),
                    'last_event'   => $providerEvent,
                    'error'        => null,
                ],
            ],
        ]);

        return $message;
    }

    public function applyProviderStatus(
        string $provider,
        string $referenceId,
        string $status,
        ?string $description = null,
        ?string $providerEvent = null,
    ): void {
        $message = Messages::where('reference_id', $referenceId)->first();

        if (!$message) {
            logger("Message not found for provider status update: {$referenceId}");
            return;
        }

        $meta = $message->delivery_meta ?? [];

        data_set($meta, 'provider.name', $provider);
        data_set($meta, 'provider.reference_id', $referenceId);
        data_set($meta, 'provider.status', $status);
        data_set($meta, 'provider.last_event', $providerEvent);
        data_set($meta, 'provider.error', $description);
        data_set($meta, 'provider.updated_at', now()->toIso8601String());

        $message->delivery_meta = $meta;

        // keep old fields alive for now
        $message->status = $status;

        $message->save();
    }

    public function markRingotelStatus(string $messageUuid, string $status, ?string $error = null): void
    {
        $message = Messages::find($messageUuid);

        if (!$message) {
            return;
        }

        $meta = $message->delivery_meta ?? [];

        data_set($meta, 'ringotel.status', $status);
        data_set($meta, 'ringotel.attempted_at', now()->toIso8601String());
        data_set($meta, 'ringotel.error', $error);

        $message->delivery_meta = $meta;
        $message->save();
    }

    public function markEmailStatus(string $messageUuid, string $status, ?string $to = null, ?string $error = null): void
    {
        $message = Messages::find($messageUuid);

        if (!$message) {
            return;
        }

        $meta = $message->delivery_meta ?? [];

        data_set($meta, 'email.status', $status);
        data_set($meta, 'email.attempted_at', now()->toIso8601String());
        data_set($meta, 'email.to', $to);
        data_set($meta, 'email.error', $error);

        $message->delivery_meta = $meta;
        $message->save();
    }
}