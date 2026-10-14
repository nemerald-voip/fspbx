<?php

namespace App\Services\Messaging;

use App\Jobs\SyncMessageToRingotel;
use App\Models\Messages;
use App\Models\RingotelMessageSync;
use App\Models\RingotelMessageDelivery;
use Illuminate\Support\Facades\DB;

class RingotelSyncDispatcher
{
    public function __construct(protected RingotelConversationService $conversations) {}

    public static function carrierAccepted(Messages $message): bool
    {
        return (bool) data_get($message->delivery_meta, 'outbound.provider.accepted_at')
            || in_array(data_get($message->delivery_meta, 'outbound.provider.status'), ['success', 'sent', 'delivered'], true);
    }

    /** This boundary must never turn a successful carrier send into a failed/retried send. */
    public function dispatch(Messages $message): bool
    {
        try {
            if ($message->direction === 'out'
                && !self::carrierAccepted($message)) {
                return false;
            }
            $contexts = $this->conversations->contexts($message);
            if (!$contexts) {
                return false;
            }
            foreach ($this->targets($message, $contexts) as $target) {
                if (isset($contexts[$target->ringotel_conversation_uuid]) && $target->status !== 'success') {
                    SyncMessageToRingotel::dispatch($message->message_uuid, $target->ringotel_conversation_uuid)
                        ->onConnection('ringotel')->onQueue('ringotel');
                }
            }
            return true;
        } catch (\Throwable $e) {
            logger()->warning('Unable to queue Ringotel synchronization; carrier delivery is unchanged.', [
                'message_uuid' => $message->message_uuid, 'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function targets(Messages $message, array $contexts)
    {
        return DB::transaction(function () use ($message, $contexts) {
            RingotelMessageSync::firstOrCreate(['message_uuid' => $message->message_uuid], [
                'ringotel_conversation_uuid' => array_key_first($contexts),
            ]);
            $sync = RingotelMessageSync::where('message_uuid', $message->message_uuid)->lockForUpdate()->firstOrFail();
            if (!$sync->targets_initialized_at) {
                foreach ($contexts as $context) {
                    $legacy = $sync->ringotel_conversation_uuid === $context['ringotel_conversation_uuid'];
                    RingotelMessageDelivery::firstOrCreate([
                        'message_uuid' => $message->message_uuid, 'ringotel_conversation_uuid' => $context['ringotel_conversation_uuid'],
                    ], [
                        'delivery_key' => hash('sha256', $message->message_uuid.'|'.$context['ringotel_conversation_uuid']),
                        'status' => $legacy ? $sync->status : 'pending',
                        'parts' => $legacy ? $sync->parts : null, 'error' => $legacy ? $sync->error : null,
                    ]);
                }
                $sync->update(['targets_initialized_at' => now()]);
            }
            return RingotelMessageDelivery::where('message_uuid', $message->message_uuid)->get();
        });
    }
}
