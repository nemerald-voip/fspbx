<?php

namespace App\Services\Messaging;

use App\Models\Messages;
use App\Models\RingotelConversation;
use App\Models\RingotelMessageDelivery;
use App\Models\User;

/** Compatibility for callers and serialized jobs queued before read sync was retired. */
class RingotelReadService
{
    public function dispatch(Messages $message, ?string $userUuid = null, ?string $authorizedExtensionUuid = null): void
    {
        // Ringotel confirmed that read changes checkmarks, not unread counts.
        // Keep old callers harmless without queuing any replacement work.
    }

    public function sync(string $messageUuid, string $extensionUuid, ?string $userUuid, bool $authorizedViewAs = false): bool
    {
        // Drain already queued SyncRingotelRead jobs without API calls or retries.
        return true;
    }

    /**
     * Ringotel supplies a session and its own message ID when its user opens a
     * conversation. Resolve both locally before changing one FS PBX user's
     * personal unread state; an unknown or ambiguous mapping is harmless.
     */
    public function capture(array $params): bool
    {
        $sessionId = (string) ($params['sessionid'] ?? '');
        $messageId = (string) ($params['messageid'] ?? '');
        if ($sessionId === '' || $messageId === '') {
            return false;
        }

        $conversation = RingotelConversation::query()->where('session_id', $sessionId)->first();
        if (! $conversation) {
            return false;
        }

        $delivery = RingotelMessageDelivery::query()
            ->where('ringotel_conversation_uuid', $conversation->ringotel_conversation_uuid)
            ->get()
            ->first(function (RingotelMessageDelivery $delivery) use ($messageId) {
                foreach ($delivery->parts ?? [] as $part) {
                    if (($part['message_id'] ?? null) === $messageId) {
                        return true;
                    }
                }

                return false;
            });

        $message = $delivery
            ? Messages::query()->where('message_uuid', $delivery->message_uuid)
                ->where('domain_uuid', $conversation->domain_uuid)
                ->where('direction', 'in')->first()
            : null;
        if (! $message) {
            return false;
        }

        // Ringotel reports the message current when its user opened the chat.
        // Treat that as a conversation read through this point, without
        // consuming a newer message that could have arrived concurrently.
        $conversationKey = $message->message_group_uuid ?: $conversation->remote_number;
        $messageUuids = app(MessageGroupService::class)->conversation(
            Messages::query()->where('domain_uuid', $conversation->domain_uuid)
                ->where('direction', 'in'),
            $conversation->local_number,
            $conversationKey,
        )->where('created_at', '<=', $message->created_at)
            ->pluck('message_uuid')->all();
        if ($messageUuids === []) {
            return false;
        }

        $users = User::query()->where('domain_uuid', $conversation->domain_uuid)
            ->where('extension_uuid', $conversation->extension_uuid)
            ->where('user_enabled', 'true')->pluck('user_uuid');
        if ($users->isEmpty()) {
            return false;
        }

        $marked = 0;
        $reads = app(MessageReadService::class);
        foreach ($users as $userUuid) {
            $marked += $reads->markRead(
                $conversation->domain_uuid,
                $userUuid,
                $conversation->local_number,
                $conversation->remote_number,
                $messageUuids,
                $conversation->extension_uuid,
            );
        }

        return $marked > 0;
    }
}
