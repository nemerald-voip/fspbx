<?php

namespace App\Services\Messaging;

use App\Jobs\SendOutboundSMSMessage;
use App\Models\Messages;
use App\Models\MessageSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MessageAutoReplyService
{
    public function available(): bool
    {
        return Schema::hasTable('message_auto_replies') && Schema::hasColumn('v_sms_destinations', 'auto_replies_enabled');
    }

    public function eligible(MessageSetting $number): bool
    {
        return $number->inbound_delivery !== 'webhook'
            && !filled(app(MessageWebhookSettings::class)->account($number->domain_uuid)?->endpoint_url);
    }

    public function rules(MessageSetting $number)
    {
        return DB::table('message_auto_replies')->where('domain_uuid', $number->domain_uuid)
            ->where('sms_destination_uuid', $number->getKey());
    }

    /** Runs inside inbound ingestion's receiving-number lock and transaction. */
    public function recordIncoming(Messages $message): void
    {
        if ($message->direction !== 'in' || !in_array($message->type, ['sms', 'mms'], true)
            || $message->isWebhookInbound() || data_get($message->delivery_meta, 'internal_notice')
            || data_get($message->delivery_meta, 'opt_out_confirmation_uuid')
            || data_get($message->delivery_meta, 'auto_reply_uuid') || !$this->available()) return;

        $optOuts = app(MessageOptOutService::class);
        try {
            $local = $optOuts->normalize($message->destination, $message->domain_uuid);
            $sender = $optOuts->normalize($message->source, $message->domain_uuid);
        } catch (ValidationException) {
            return; // Do not auto-reply to short codes or invalid senders.
        }
        $number = MessageSetting::where('domain_uuid', $message->domain_uuid)->where('enabled', 'true')
            ->where('auto_replies_enabled', true)->get()->first(function ($number) use ($local, $optOuts) {
                try { return $optOuts->normalize($number->destination, $number->domain_uuid) === $local && $this->eligible($number); }
                catch (ValidationException) { return false; }
            });
        if (!$number || $local === $sender || $optOuts->blockedRecipients($message->domain_uuid, $local, [$sender])) return;

        $rules = $this->rules($number)->where('enabled', true)->get();
        $text = mb_strtoupper((string) $message->message, 'UTF-8');
        $rule = $rules->first(fn ($rule) => $rule->trigger === 'keyword'
            && in_array($text, json_decode($rule->keywords ?? '[]', true), true));
        if (!$rule && $rules->contains('trigger', 'first')) {
            $hasHistory = Messages::where('domain_uuid', $message->domain_uuid)->where('direction', 'in')
                ->where('source', $sender)->where('destination', $local)->whereKeyNot($message->getKey())
                ->whereNull('delivery_meta->internal_notice')->exists();
            if (!$hasHistory) $rule = $rules->firstWhere('trigger', 'first');
        }
        $rule ??= $rules->firstWhere('trigger', 'every');
        if (!$rule) return;

        $reply = app(MessageRepository::class)->storeOutbound(
            domainUuid: $message->domain_uuid, extensionUuid: $message->extension_uuid,
            source: $local, destination: $sender, text: $rule->reply,
            type: 'sms', carrier: (string) $number->carrier, origin: 'auto_reply',
            meta: ['in_reply_to' => $message->getKey(), 'auto_reply_rule_uuid' => $rule->message_auto_reply_uuid],
        );
        $message->updateDeliveryMeta('auto_reply_uuid', $reply->getKey());
        $message->save();
    }

    /** Carrier callback retries reuse the same stored reply, never create another. */
    public function dispatchReply(Messages $message): void
    {
        $id = data_get($message->delivery_meta, 'auto_reply_uuid');
        if (!$id || $message->direction !== 'in' || $message->isWebhookInbound()) return;
        $reply = Messages::where('domain_uuid', $message->domain_uuid)->whereKey($id)
            ->where('direction', 'out')->where('status', 'queued')
            ->where('delivery_meta->outbound->origin', 'auto_reply')->first();
        if ($reply && !RingotelSyncDispatcher::carrierAccepted($reply)) {
            SendOutboundSMSMessage::dispatch($reply->getKey())->onQueue('messages')->afterCommit();
        }
    }
}
