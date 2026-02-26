<?php

namespace App\Observers;

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
        logger("Observer Fired for Message: {$message->message_uuid}");
        $isOutbound = in_array(strtolower($message->direction), ['out', 'outbound', 'outgoing']);
        $role = $isOutbound ? 'user' : 'ai';

        // 1. Identify Local & Remote (Assuming E.164 consistency)
        $local = $isOutbound ? $message->source : $message->destination;
        $remote = $isOutbound ? $message->destination : $message->source;

        // 2. Active Chat Window (DeepChat)
        // e.g. +15551234567_+16469998888
        $roomId = "{$local}_{$remote}";

        $payload = [
            'text' => $message->message,
            'role' => $role,
            'timestamp' => $message->created_at->toIsoString(),
        ];

        logger("Broadcasting to channel: room." . str_replace('+', '', $roomId)); 

        // 3. Broadcast
        broadcast(new MessageSent($payload, $roomId));

        // 4. Global Sidebar Update
        // Broadcasts to 'extension.{uuid}'
        $sidebarPayload = [
            'roomId' => $roomId,
            'lastMessage' => $message->message,
            'timestamp' => $message->created_at->toIsoString(),
            'name' => $remote,       // Display Name (Customer)
            'my_number' => $local,   // Context
            'direction' => $isOutbound ? 'out' : 'in',
        ];

        broadcast(new ConversationUpdated($sidebarPayload, $message->extension_uuid));
    }
}
