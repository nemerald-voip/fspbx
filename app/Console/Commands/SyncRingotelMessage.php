<?php

namespace App\Console\Commands;

use App\Models\Messages;
use App\Models\RingotelMessageSync;
use App\Models\RingotelMessageDelivery;
use App\Services\Messaging\RingotelConversationService;
use App\Services\Messaging\RingotelSyncDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncRingotelMessage extends Command
{
    protected $signature = 'ringotel:sync-message {message_uuid}
        {--status : Show synchronization state without changing anything}
        {--extension= : Select the recipient extension for session binding or a part retry}
        {--session= : Bind a session ID verified from this user/contact conversation}
        {--retry-part= : Retry an uncertain part only after verifying it is absent in Ringotel}';

    protected $description = 'Inspect or queue Ringotel synchronization without resending through the carrier';

    public function handle(RingotelConversationService $conversations, RingotelSyncDispatcher $dispatcher): int
    {
        $message = Messages::findOrFail($this->argument('message_uuid'));
        $sync = RingotelMessageSync::find($message->message_uuid);
        if ($this->option('status')) {
            $this->line(json_encode([
                'summary' => $sync?->toArray() ?? ['status' => 'not_queued'],
                'recipients' => RingotelMessageDelivery::where('message_uuid', $message->message_uuid)->get()->toArray(),
            ], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }
        if ($message->direction === 'out'
            && !RingotelSyncDispatcher::carrierAccepted($message)) {
            $this->error('Only carrier-accepted messages can be synchronized outbound.');
            return self::FAILURE;
        }
        $contexts = $conversations->contexts($message);
        $context = collect($contexts)->first(fn ($c) => $this->option('extension')
            ? $c['extension'] === $this->option('extension') : $c['extension_uuid'] === $message->extension_uuid);
        if (!$context && count($contexts) === 1 && !$this->option('extension')) {
            $context = reset($contexts);
        }
        if (!$context) {
            $this->error('This message has no current Ringotel assignment.');
            return self::FAILURE;
        }

        $dispatcher->targets($message, $contexts);
        $sync = RingotelMessageDelivery::where('message_uuid', $message->message_uuid)
            ->where('ringotel_conversation_uuid', $context['ringotel_conversation_uuid'])->first();

        Cache::store('redis')->lock('ringotel:conversation:'.$context['ringotel_conversation_uuid'], 180)->block(2,
            function () use ($context, $conversations, $sync) {
                if ($sessionId = $this->option('session')) {
                    if (!preg_match('/^[A-Za-z0-9_-]+$/', $sessionId)) {
                        throw new \RuntimeException('Invalid session ID.');
                    }
                    $conversations->conversation($context)->update([
                        'session_id' => $sessionId, 'observed_at' => now(),
                    ]);
                }
                if ($key = $this->option('retry-part')) {
                    $parts = $sync?->fresh()->parts ?? [];
                    if (!in_array($parts[$key]['status'] ?? null, ['sending', 'uncertain'], true)) {
                        throw new \RuntimeException('Only sending/uncertain parts can be reset. Accepted parts are never reset.');
                    }
                    unset($parts[$key]);
                    $sync->update(['parts' => $parts, 'status' => 'pending', 'error' => null]);
                }
            });

        if (!$dispatcher->dispatch($message)) {
            $this->error('Synchronization could not be queued. Check the application log.');
            return self::FAILURE;
        }
        $this->info('Ringotel synchronization queued. Use --status to inspect the result.');
        return self::SUCCESS;
    }
}
