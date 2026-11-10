<?php

namespace App\Services\Messaging;

use App\Jobs\SendOutboundSMSMessage;
use App\Models\Messages;
use App\Models\MessageSetting;
use App\Services\PhoneNumberService;
use App\Support\Localization\LocaleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class MessageOptOutService
{
    public function __construct(private MessageOptOutSettings $settings, private PhoneNumberService $numbers) {}

    public function available(): bool
    {
        return Schema::hasColumn('v_sms_destinations', 'opt_out_enabled') && Schema::hasTable('message_opt_outs');
    }

    public function eligible(MessageSetting $number): bool
    {
        if (!$this->settings->props($number->domain_uuid)['opt_out_available']) return false;
        // Preserve legacy per-number webhook routes, including incomplete configurations.
        return $number->inbound_delivery !== 'webhook';
    }

    public function normalize(string $value, string $domainUuid): string
    {
        $util = PhoneNumberUtil::getInstance();
        try {
            if (!preg_match('/^\+?[0-9(][0-9\s().-]*$/D', trim($value))) throw new \InvalidArgumentException();
            $number = $util->parse($value, $this->numbers->countryCodeForDomain($domainUuid));
            if (!$util->isPossibleNumber($number) || $number->hasExtension()) throw new \InvalidArgumentException();
            return $util->format($number, PhoneNumberFormat::E164);
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['numbers' => [__('Enter valid phone numbers, including a country code when needed.')]]);
        }
    }

    public function scope(MessageSetting $number): string
    {
        return $this->settings->props($number->domain_uuid)['opt_out_effective_scope'];
    }

    public function query(MessageSetting $number)
    {
        $query = DB::table('message_opt_outs')->where('domain_uuid', $number->domain_uuid);
        if ($this->scope($number) === 'number') {
            $local = $this->normalize($number->destination, $number->domain_uuid);
            $query->where('local_number', $local);
        }
        return $query;
    }

    public function add(MessageSetting $number, array $phones, string $source = 'manual'): int
    {
        $local = $this->normalize($number->destination, $number->domain_uuid);
        $rows = array_map(fn ($phone) => [
            'message_opt_out_uuid' => (string) Str::uuid(), 'domain_uuid' => $number->domain_uuid,
            'local_number' => $local, 'phone_number' => $phone, 'source' => $source,
            'created_at' => now(), 'updated_at' => now(),
        ], array_values(array_unique($phones)));
        return $rows ? DB::table('message_opt_outs')->insertOrIgnore($rows) : 0;
    }

    public function recordIncoming(Messages $message): void
    {
        if ($message->direction !== 'in' || !in_array($message->type, ['sms', 'mms'], true) || $message->isWebhookInbound()
            || !$this->available()) return;
        $keywords = $this->settings->props($message->domain_uuid)['opt_out_keywords'];
        if (!in_array(mb_strtoupper((string) $message->message, 'UTF-8'), $keywords, true)) return;
        $number = $this->enabledNumber($message->domain_uuid, $message->destination);
        if (!$number) return;
        try {
            $phone = $this->normalize($message->source, $message->domain_uuid);
        } catch (ValidationException) {
            return; // Short codes are not subscriber phone numbers.
        }
        DB::transaction(function () use ($message, $number, $phone) {
            $locked = Messages::where('domain_uuid', $message->domain_uuid)
                ->whereKey($message->getKey())->lockForUpdate()->firstOrFail();
            if (data_get($locked->delivery_meta, 'opt_out_confirmation_uuid')) return;

            $this->add($number, [$phone], strcasecmp((string) $locked->message, 'STOP') === 0 ? 'stop' : 'keyword');
            $locale = app(LocaleRegistry::class)->resolve(get_domain_setting('language', $locked->domain_uuid));
            $reply = app(MessageRepository::class)->storeOutbound(
                domainUuid: $locked->domain_uuid,
                extensionUuid: $locked->extension_uuid,
                source: $locked->destination,
                destination: $phone,
                text: __('Your request to opt out of future text messages has been received and processed.', [], $locale),
                type: 'sms',
                carrier: (string) $number->carrier,
                origin: 'opt_out_confirmation',
                meta: ['in_reply_to' => $locked->getKey(), 'locale' => $locale],
            );
            $locked->updateDeliveryMeta('opt_out_confirmation_uuid', $reply->getKey());
            $locked->save();
        });
        $message->refresh();
    }

    /** Reuse the persisted reply when a carrier callback retries after queue dispatch failed. */
    public function dispatchConfirmation(Messages $message): void
    {
        $id = data_get($message->delivery_meta, 'opt_out_confirmation_uuid');
        if (!$id || $message->direction !== 'in' || $message->isWebhookInbound()) return;

        $reply = Messages::where('domain_uuid', $message->domain_uuid)->whereKey($id)
            ->where('direction', 'out')->where('status', 'queued')
            ->where('delivery_meta->outbound->origin', 'opt_out_confirmation')->first();
        if ($reply && !RingotelSyncDispatcher::carrierAccepted($reply)) {
            SendOutboundSMSMessage::dispatch($reply->getKey())->onQueue('messages')->afterCommit();
        }
    }

    private function enabledNumber(string $domainUuid, string $local): ?MessageSetting
    {
        $local = $this->normalize($local, $domainUuid);
        return MessageSetting::where('domain_uuid', $domainUuid)->where('enabled', 'true')
            ->get()->first(function ($number) use ($local, $domainUuid) {
                try {
                    return $this->normalize($number->destination, $domainUuid) === $local && $this->eligible($number)
                        && ($this->scope($number) === 'account' || $number->opt_out_enabled);
                } catch (ValidationException) {
                    return false;
                }
            });
    }

    public function blockedRecipients(string $domainUuid, string $local, array $recipients): array
    {
        if (!$this->available()) return [];
        $number = $this->enabledNumber($domainUuid, $local);
        if (!$number) return [];
        return $this->query($number)->whereIn('phone_number', $recipients)
            ->distinct()->pluck('phone_number')->all();
    }

    public function remainingGroupRecipients(Messages $message): array
    {
        $group = \App\Models\MessageGroup::where('domain_uuid', $message->domain_uuid)
            ->where('local_number', $message->source)->findOrFail($message->message_group_uuid);
        return array_values(array_diff($group->recipients,
            $this->blockedRecipients($message->domain_uuid, $message->source, $group->recipients)));
    }

    /** Called before queueing and again immediately before carrier delivery, including retries. */
    public function block(Messages $message): bool
    {
        // Customer API and application notifications never enter opt-out handling.
        if ($message->direction !== 'out' || !in_array(data_get($message->delivery_meta, 'outbound.origin'), ['portal', 'ringotel', 'auto_reply'], true)
            || RingotelSyncDispatcher::carrierAccepted($message) || !$this->available()) return false;
        $number = $this->enabledNumber($message->domain_uuid, $message->source);
        if (!$number) return false;
        $recipients = $message->message_group_uuid
            ? \App\Models\MessageGroup::where('domain_uuid', $message->domain_uuid)->whereKey($message->message_group_uuid)->firstOrFail()->recipients
            : [$message->destination];
        $recipients = array_values(array_filter(array_map(function ($phone) use ($message) {
            try { return $this->normalize($phone, $message->domain_uuid); }
            catch (ValidationException) { return null; }
        }, $recipients)));
        $blocked = $this->query($number)->whereIn('phone_number', $recipients)->distinct()->pluck('phone_number')->all();
        if (!$blocked) return false;

        $notice = DB::transaction(function () use ($message, $blocked) {
            $locked = Messages::where('domain_uuid', $message->domain_uuid)->whereKey($message->getKey())->lockForUpdate()->firstOrFail();
            $noticeId = data_get($locked->delivery_meta, 'opt_out_notice_uuid');
            $text = __('This phone number has requested an opt out. Contact your administrator for further assistance.');
            $locked->updateDeliveryMeta('opt_out_blocked_recipients', $blocked);
            app(MessageRepository::class)->markOutboundFailure($locked, (string) data_get($locked->delivery_meta, 'outbound.provider.name'), $text);
            if ($noticeId) return Messages::find($noticeId);
            $notice = Messages::create([
                'message_uuid' => (string) Str::uuid(),
                'domain_uuid' => $locked->domain_uuid, 'extension_uuid' => $locked->extension_uuid,
                'source' => $locked->destination, 'destination' => $locked->source,
                'message_group_uuid' => $locked->message_group_uuid,
                'direction' => 'in', 'type' => 'sms', 'status' => 'received',
                'message' => __('System notice: Message not sent.') . ' ' . implode(', ', $blocked) . ' — ' . $text,
                'delivery_meta' => ['internal_notice' => 'opt_out', 'blocked_message_uuid' => $locked->getKey(), 'ringotel_tracking' => 1],
            ]);
            $locked->updateDeliveryMeta('opt_out_notice_uuid', $notice->getKey());
            $locked->save();
            return $notice;
        });
        if ($notice) DB::afterCommit(fn () => app(RingotelSyncDispatcher::class)->dispatch($notice));
        return true;
    }
}
