<?php

namespace App\Observers;

use Throwable;
use App\Models\Messages;
use App\Events\MessageSent;
use App\Events\ConversationUpdated;

class MessageObserver
{
    /**
     * Handle the Messages "created" event.
     */
    public function created(Messages $message): void
    {
        try {
            // logger("Observer Fired for Message: {$message->message_uuid}");

            $isOutbound = in_array(strtolower($message->direction), ['out', 'outbound', 'outgoing']);
            $role = $isOutbound ? 'user' : 'ai';

            // 1. Identify Local & Remote
            $local = $isOutbound ? $message->source : $message->destination;
            $remote = $isOutbound ? $message->destination : $message->source;

            // 2. Active Chat Window
            $roomId = "{$local}_{$remote}";

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
                'text' => $message->message,
                'role' => $role,
                'timestamp' => $message->created_at->toIsoString(),
                'media'     => $mediaPayload,
            ];

            // logger("Broadcasting to channel: room." . str_replace('+', '', $roomId));

            // 3. Broadcast message update
            try {
                broadcast(new MessageSent($payload, $roomId));
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
                broadcast(new ConversationUpdated($sidebarPayload, $message->extension_uuid));
            } catch (Throwable $e) {
                logger('Error broadcasting ConversationUpdated: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            }
        } catch (Throwable $e) {
            logger('Error in MessageObserver: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        }
    }
}
