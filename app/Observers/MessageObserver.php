<?php

namespace App\Observers;

use App\Models\Messages;
use App\Events\MessageSent;

class MessageObserver
{
    /**
     * Handle the Messages "created" event.
     */
    public function created(Messages $message): void
    {
        // 1. Determine Direction & Role
        // Adjust 'out', 'outbound' based on what your carrier actually saves
        $isOutbound = in_array(strtolower($message->direction), ['out', 'outbound', 'outgoing']);
        
        // Role: If outbound, it's the 'user'. If inbound, it's 'ai' (or contact)
        $role = $isOutbound ? 'user' : 'ai';

        // 2. Determine the "Room ID" (The Phone Number of the OTHER person)
        // If Outbound: Room is the Destination.
        // If Inbound: Room is the Source.
        $rawNumber = $isOutbound ? $message->destination : $message->source;

        // 3. Normalize the Room ID (Logic matches your SQL/Controller)
        // Strip non-digits
        $digits = preg_replace('/\D/', '', $rawNumber);
        
        // Apply E.164-ish logic (US centric example)
        if (strlen($digits) === 10) {
            $roomId = '1' . $digits;
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $roomId = $digits;
        } else {
            $roomId = $digits; // Fallback for international
        }

        // 4. Prepare Payload
        $payload = [
            'text' => $message->message,
            'role' => $role,
            'timestamp' => $message->created_at->toIsoString(),
        ];

        // 5. Broadcast!
        // Note: We broadcast to *everyone* (including the sender).
        // The Frontend is responsible for ignoring 'role: user' duplicates.
        broadcast(new MessageSent($payload, $roomId));
    }
}