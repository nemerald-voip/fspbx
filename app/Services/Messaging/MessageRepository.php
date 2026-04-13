<?php

namespace App\Services\Messaging;

use App\Models\Messages;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;
use libphonenumber\PhoneNumberFormat;

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
        $countryCode = get_domain_setting('country', $domainUuid) ?? 'US';

        $normalizedSource = $this->normalizePhoneNumberForStorage($source, $countryCode);
        $normalizedDestination = $this->normalizePhoneNumberForStorage($destination, $countryCode);

        messaging_webhook_debug('storeInbound called', [
            'domain_uuid' => $domainUuid,
            'extension_uuid' => $extensionUuid,
            'source' => $source,
            'normalized_source' => $normalizedSource,
            'destination' => $destination,
            'normalized_destination' => $normalizedDestination,
            'type' => $type,
            'provider' => $providerName,
            'reference_id' => $providerReferenceId,
            'media_count' => count($media),
        ]);

        $message = Messages::create([
            'domain_uuid'    => $domainUuid,
            'extension_uuid' => $extensionUuid,
            'source'         => $normalizedSource,
            'destination'    => $normalizedDestination,
            'message'        => $text,
            'direction'      => 'in',
            'type'           => $type,
            'reference_id'   => $providerReferenceId,
            'status'         => 'received',
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

        if (!empty($media)) {
            $message->media = $this->attachMediaAccessPaths(
                messageUuid: $message->message_uuid,
                media: $message->media ?? []
            );

            $message->save();

            messaging_webhook_debug('Media access paths attached', [
                'message_uuid' => $message->message_uuid,
                'media' => $message->media,
            ]);
        }

        messaging_webhook_debug('storeInbound created message', [
            'message_uuid' => $message->message_uuid,
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
        messaging_webhook_debug('applyProviderStatus called', [
            'provider' => $provider,
            'reference_id' => $referenceId,
            'status' => $status,
            'provider_event' => $providerEvent,
        ]);

        $message = Messages::where('reference_id', $referenceId)->first();

        if (!$message) {
            messaging_webhook_debug('applyProviderStatus message not found', [
                'reference_id' => $referenceId,
            ]);
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
        $message->status = $status;
        $message->save();

        messaging_webhook_debug('applyProviderStatus updated message', [
            'message_uuid' => $message->message_uuid,
        ]);
    }

    public function storeOutbound(
        string $domainUuid,
        ?string $extensionUuid,
        string $source,
        string $destination,
        string $text,
        string $type,
        string $carrier,
        string $origin,
        ?string $providerReferenceId = null,
        array $media = [],
        array $meta = [],
    ): Messages {
        messaging_webhook_debug('storeOutbound called', [
            'domain_uuid' => $domainUuid,
            'extension_uuid' => $extensionUuid,
            'source' => $source,
            'destination' => $destination,
            'type' => $type,
            'carrier' => $carrier,
            'origin' => $origin,
            'media_count' => count($media),
        ]);

        $countryCode = get_domain_setting('country', $domainUuid) ?? 'US';

        $normalizedSource = $this->normalizePhoneNumberForStorage($source, $countryCode);
        $normalizedDestination = $this->normalizePhoneNumberForStorage($destination, $countryCode);

        $message = Messages::create([
            'domain_uuid'    => $domainUuid,
            'extension_uuid' => $extensionUuid,
            'source'         => $normalizedSource,
            'destination'    => $normalizedDestination,
            'message'        => $text,
            'direction'      => 'out',
            'type'           => $type,
            'reference_id'   => $providerReferenceId,
            'status'         => 'queued',
            'media'          => $media,
            'delivery_meta'  => [
                'outbound' => [
                    'origin' => $origin,
                    'provider' => [
                        'name' => $carrier,
                        'status' => 'queued',
                        'queued_at' => now()->toIso8601String(),
                        'reference_id' => $providerReferenceId,
                        'error' => null,
                    ],
                    'meta' => $meta,
                ],
            ],
        ]);

        messaging_webhook_debug('storeOutbound created message', [
            'message_uuid' => $message->message_uuid,
        ]);

        if (!empty($media)) {
            $message->media = $this->attachMediaAccessPaths(
                messageUuid: $message->message_uuid,
                media: $message->media ?? []
            );

            $message->save();
        }

        messaging_webhook_debug('storeOutbound created message', [
            'message_uuid' => $message->message_uuid,
        ]);

        return $message;
    }

    public function applyOutboundSendResult(
        Messages $message,
        string $carrier,
        OutboundSendResultData $result
    ): void {
        messaging_webhook_debug('applyOutboundSendResult called', [
            'message_uuid' => $message->message_uuid,
            'carrier' => $carrier,
            'success' => $result->success,
            'status' => $result->status,
            'provider_reference_id' => $result->providerReferenceId,
            'error' => $result->error,
        ]);

        $meta = $message->delivery_meta ?? [];

        data_set($meta, 'outbound.provider.name', $carrier);
        data_set($meta, 'outbound.provider.status', $result->status);
        data_set($meta, 'outbound.provider.reference_id', $result->providerReferenceId);
        data_set($meta, 'outbound.provider.error', $result->error);
        data_set($meta, 'outbound.provider.response', $result->providerResponse);
        data_set($meta, 'outbound.provider.updated_at', now()->toIso8601String());

        $message->delivery_meta = $meta;
        $message->status = $result->status;

        if ($result->providerReferenceId) {
            $message->reference_id = $result->providerReferenceId;
        }

        $message->save();
        messaging_webhook_debug('applyOutboundSendResult called', [
            'message_uuid' => $message->message_uuid,
            'carrier' => $carrier,
            'success' => $result->success,
            'status' => $result->status,
            'provider_reference_id' => $result->providerReferenceId,
            'error' => $result->error,
        ]);
    }


    public function markRingotelStatus(string $messageUuid, string $status, ?string $error = null): void
    {
        messaging_webhook_debug('markRingotelStatus called', [
            'message_uuid' => $messageUuid,
            'status' => $status,
        ]);

        $message = Messages::find($messageUuid);

        if (!$message) {
            messaging_webhook_debug('markRingotelStatus message not found', [
                'message_uuid' => $messageUuid,
            ]);
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
        messaging_webhook_debug('markEmailStatus called', [
            'message_uuid' => $messageUuid,
            'status' => $status,
            'to' => $to,
        ]);

        $message = Messages::find($messageUuid);

        if (!$message) {
            messaging_webhook_debug('markEmailStatus message not found', [
                'message_uuid' => $messageUuid,
            ]);
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

    protected function attachMediaAccessPaths(string $messageUuid, array $media): array
    {
        return collect($media)
            ->values()
            ->map(function ($item, $index) use ($messageUuid) {
                if (!is_array($item)) {
                    return $item;
                }

                $storedName = $item['stored_name'] ?? basename($item['object_key'] ?? 'attachment');

                $item['access_path'] = $this->buildMediaAccessPath(
                    messageUuid: $messageUuid,
                    index: $index,
                    storedName: $storedName,
                );

                $item['mime_type'] = $this->normalizeMimeType($item['mime_type'] ?? null);

                return $item;
            })
            ->all();
    }

    protected function buildMediaAccessPath(string $messageUuid, int $index, string $storedName): string
    {
        return "/messages/media/{$messageUuid}/{$index}/{$storedName}";
    }

    protected function normalizeMimeType(?string $mimeType): ?string
    {
        if (!$mimeType) {
            return $mimeType;
        }

        return trim(explode(';', $mimeType)[0]);
    }

    protected function normalizePhoneNumberForStorage(?string $number, string $countryCode): ?string
    {
        if (!$number) {
            return $number;
        }

        $formatted = formatPhoneNumber($number, $countryCode, PhoneNumberFormat::E164);

        return $formatted ?: $number;
    }

    public function markOutboundFailure(Messages $message, string $carrier, string $error): void
    {
        $meta = $message->delivery_meta ?? [];

        data_set($meta, 'outbound.provider.name', $carrier);
        data_set($meta, 'outbound.provider.status', 'failed');
        data_set($meta, 'outbound.provider.error', $error);
        data_set($meta, 'outbound.provider.updated_at', now()->toIso8601String());

        $message->delivery_meta = $meta;
        $message->status = 'failed';
        $message->save();

        messaging_webhook_debug('markOutboundFailure updated message', [
            'message_uuid' => $message->message_uuid,
            'carrier' => $carrier,
            'error' => $error,
        ]);
    }
}
