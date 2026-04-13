<?php

namespace App\Services\Messaging;

use App\Jobs\DeliverMessageToEmail;
use App\Jobs\DeliverMessageToRingotel;
use App\Jobs\SendOutboundSMSMessage;
use App\Models\Extensions;
use App\Models\Messages;
use App\Models\SmsDestinations;
use Illuminate\Support\Collection;

class RetryMessageService
{
    public function __construct(
        protected MessageDestinationResolver $resolver,
    ) {}

    public function retryMany(Collection $messages): void
    {
        messaging_webhook_debug('RetryMessageService retryMany() started', [
            'count' => $messages->count(),
            'message_ids' => $messages->pluck('message_uuid')->all(),
        ]);

        foreach ($messages as $message) {
            $this->retryOne($message);
        }
    }

    public function retryOne(Messages $message): void
    {
        messaging_webhook_debug('RetryMessageService retryOne()', [
            'message_uuid' => $message->message_uuid,
            'direction' => $message->direction,
            'type' => $message->type,
            'status' => $message->status,
        ]);

        if ($message->direction === 'out') {
            $this->retryOutbound($message);
            return;
        }

        if ($message->direction === 'in') {
            $this->retryInbound($message);
            return;
        }

        throw new \RuntimeException("Unsupported message direction: {$message->direction}");
    }

    protected function retryOutbound(Messages $message): void
    {
        $carrier = data_get($message->delivery_meta, 'outbound.provider.name');

        messaging_webhook_debug('Retry outbound started', [
            'message_uuid' => $message->message_uuid,
            'carrier_from_meta' => $carrier,
        ]);

        if (!$carrier) {
            $extension = Extensions::find($message->extension_uuid);

            if (!$extension) {
                throw new \RuntimeException("Extension not found for outbound message {$message->message_uuid}");
            }

            $smsConfig = SmsDestinations::where('domain_uuid', $message->domain_uuid)
                ->where('destination', $message->source)
                ->where('chatplan_detail_data', $extension->extension)
                ->first();

            if (!$smsConfig) {
                throw new \RuntimeException(
                    "SMS configuration not found for source {$message->source} on extension {$extension->extension}"
                );
            }

            $carrier = $smsConfig->carrier;

            messaging_webhook_debug('Retry outbound carrier fallback used', [
                'message_uuid' => $message->message_uuid,
                'carrier' => $carrier,
            ]);
        }

        $meta = $message->delivery_meta ?? [];

        data_set($meta, 'outbound.provider.name', $carrier);
        data_set($meta, 'outbound.provider.status', 'queued');
        data_set($meta, 'outbound.provider.error', null);
        data_set($meta, 'outbound.provider.updated_at', now()->toIso8601String());

        $message->delivery_meta = $meta;
        $message->status = 'queued';
        $message->save();

        messaging_webhook_debug('Retry outbound re-queued', [
            'message_uuid' => $message->message_uuid,
            'carrier' => $carrier,
        ]);

        SendOutboundSMSMessage::dispatch($message->message_uuid)->onQueue('messages');
    }

    protected function retryInbound(Messages $message): void
    {
        messaging_webhook_debug('Retry inbound started', [
            'message_uuid' => $message->message_uuid,
            'destination' => $message->destination,
        ]);

        $route = $this->resolver->resolve($message->destination);

        messaging_webhook_debug('Retry inbound route resolved', [
            'message_uuid' => $message->message_uuid,
            'has_mobile_app' => $route->hasMobileApp,
            'org_id' => $route->orgId,
            'extension' => $route->extension,
            'email' => $route->email,
        ]);

        if ($route->hasMobileApp && $route->orgId && $route->extension) {
            DeliverMessageToRingotel::dispatch(
                $message->message_uuid,
                $route->orgId,
                $route->extension
            )->onQueue('messages');

            messaging_webhook_debug('Retry inbound Ringotel queued', [
                'message_uuid' => $message->message_uuid,
            ]);
        }

        if ($route->email) {
            DeliverMessageToEmail::dispatch(
                $message->message_uuid,
                $route->orgId,
                $route->email
            )->onQueue('emails');

            messaging_webhook_debug('Retry inbound email queued', [
                'message_uuid' => $message->message_uuid,
                'email' => $route->email,
            ]);
        }

        if (!$route->hasMobileApp && !$route->email) {
            throw new \RuntimeException(
                "No retry destination found for inbound message {$message->message_uuid}"
            );
        }
    }
}