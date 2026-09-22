<?php

namespace App\Observers;

use Throwable;
use App\Models\Messages;
use App\Events\MessageSent;
use App\Events\ConversationUpdated;
use App\Jobs\DeliverTenantMessagingWebhook;

class MessageObserver
{
    public function updated(Messages $message): void
    {
        if ($message->wasChanged(['status', 'media'])) $this->broadcast($message);

        if ($message->wasChanged('status')) {
            $event = match (strtolower((string) $message->status)) {
                'success', 'accepted', 'queued' => 'message.accepted',
                'delivered' => 'message.delivered',
                'failed' => 'message.failed',
                default => null,
            };
            if ($event) DeliverTenantMessagingWebhook::dispatch($message->message_uuid, $event)->onQueue('messages');
        }
    }

    /**
     * Handle the Messages "created" event.
     */
    public function created(Messages $message): void
    {
        DeliverTenantMessagingWebhook::dispatch(
            $message->message_uuid,
            strtolower((string) $message->direction) === 'in' ? 'message.received' : 'message.queued'
        )->onQueue('messages');

        $this->broadcast($message);
    }

    private function broadcast(Messages $message): void
    {
        try {
            // logger("Observer Fired for Message: {$message->message_uuid}");

            $isOutbound = in_array(strtolower($message->direction), ['out', 'outbound', 'outgoing']);
            $role = $isOutbound ? 'user' : 'ai';

            // 1. Identify Local & Remote
            $local = $isOutbound ? $message->source : $message->destination;
            $remote = $isOutbound ? $message->destination : $message->source;

            // 2. Active Chat Window
            $roomId = $message->roomId();

            $mediaPayload = $message->media;
            
            if (is_array($mediaPayload)) {
                foreach ($mediaPayload as $index => &$item) {
                    // If the path is null (because it just inserted), build it manually
                    if (empty($item['access_path'])) {
                        $fileName = $item['stored_name'] ?? 'image.png';
                        // Matches your MessageMediaController route structure
                        $item['access_path'] = "/messages/media/{$message->message_uuid}/{$index}/{$fileName}";
                    }
                }
            }

            $payload = [
                'id' => $message->message_uuid,
                'sender_name' => $message->message_group_uuid && !$isOutbound ? $message->source
                    : app(\App\Services\Messaging\MessageParticipantService::class)->sender($message),
                'text' => $message->message,
                'role' => $role,
                'timestamp' => $message->created_at->toIsoString(),
                'media'     => $mediaPayload,
            ];

            // logger("Broadcasting to channel: room." . str_replace('+', '', $roomId));

            // 3. Broadcast message update
            try {
                broadcast(new MessageSent($payload, $roomId, $message->domain_uuid));
            } catch (Throwable $e) {
                logger('Error broadcasting MessageSent: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            }

            // 4. Global Sidebar Update
            $sidebarPayload = [
                'roomId' => $roomId,
                'lastMessage' => $message->message,
                'timestamp' => $message->created_at->toIsoString(),
                'name' => $remote,
                'my_number' => $local,
                'direction' => $isOutbound ? 'out' : 'in',
                'media'     => $message->media,
            ];

            try {
                foreach (app(\App\Services\Messaging\MessageParticipantService::class)->members($message->domain_uuid, $local) as $member) {
                    broadcast(new ConversationUpdated($sidebarPayload, $member->extension_uuid));
                }
            } catch (Throwable $e) {
                logger('Error broadcasting ConversationUpdated: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            }
        } catch (Throwable $e) {
            logger('Error in MessageObserver: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        }
    }
}
