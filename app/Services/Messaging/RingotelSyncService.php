<?php

namespace App\Services\Messaging;

use App\Models\Messages;
use App\Models\RingotelMessageSync;
use App\Models\RingotelMessageDelivery;
use App\Services\RingotelApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RingotelSyncService
{
    public function __construct(
        protected RingotelConversationService $conversations,
        protected RingotelApiService $api,
        protected MessageRepository $messages,
    ) {}

    public function sync(string $messageUuid, ?string $conversationId = null): void
    {
        // Jobs queued before the UUID conversion still carry the old hash.
        if ($conversationId && strlen($conversationId) === 64) {
            $conversationId = RingotelConversationService::uuidForKey($conversationId);
        }
        $message = Messages::find($messageUuid);
        if (!$message || !in_array($message->direction, ['in', 'out'], true)) {
            return;
        }
        if ($message->direction === 'out'
            && !RingotelSyncDispatcher::carrierAccepted($message)) {
            return;
        }
        $contexts = $this->conversations->contexts($message);
        if (!$contexts) {
            return;
        }
        $targets = (new RingotelSyncDispatcher($this->conversations))->targets($message, $contexts);
        foreach ($targets as $target) {
            if ((!$conversationId || $target->ringotel_conversation_uuid === $conversationId)
                && isset($contexts[$target->ringotel_conversation_uuid])) {
                $this->syncTarget($message, $contexts[$target->ringotel_conversation_uuid]);
            }
        }
    }

    protected function syncTarget(Messages $message, array $context): void
    {
        // Dedicated workers serialize each local conversation, including partial MMS retries.
        Cache::store('redis')->lock('ringotel:conversation:'.$context['ringotel_conversation_uuid'], 180)->block(2, function () use ($message, $context) {
            $sync = RingotelMessageDelivery::where('message_uuid', $message->message_uuid)
                ->where('ringotel_conversation_uuid', $context['ringotel_conversation_uuid'])->firstOrFail();
            if ($sync->status === 'success') {
                return;
            }
            if ($sync->ringotel_conversation_uuid !== $context['ringotel_conversation_uuid']) {
                $this->status($sync, 'failed', 'The Ringotel assignment changed. Review this message before synchronizing it.');
                return;
            }

            try {
                // Validate all components before creating a session or sending any part.
                $payloads = $this->payloads($message);
                $author = $message->direction === 'out' ? $this->conversations->author($message) : null;
                $conversation = $this->conversations->resolve($message, $context);
                $parts = $sync->parts ?? [];
                foreach ($payloads as $key => $payload) {
                    if (($parts[$key]['status'] ?? null) === 'success') {
                        continue;
                    }
                    // A crash/timeout can happen after Ringotel accepted the request. There
                    // is no documented idempotency key, so never blindly replay that part.
                    if (in_array($parts[$key]['status'] ?? null, ['sending', 'uncertain'], true)) {
                        $this->status($sync, 'uncertain', 'A Ringotel request has an unknown outcome. Check the chat before retrying this part.');
                        return;
                    }

                    $params = $payload + [
                        'orgid' => $context['org_id'],
                        'sessionid' => $conversation->session_id,
                        'from' => $message->direction === 'in' ? $conversation->remote_identifier : $author,
                        'to' => $message->direction === 'in' ? $context['extension'] : $conversation->remote_identifier,
                    ];
                    // Live incoming messages must use new-message semantics, not
                    // the timestamped history format used for outgoing copies.
                    if ($message->direction === 'out') {
                        $params['timestamp'] = (int) $message->created_at->valueOf();
                    }
                    $parts[$key] = [
                        'status' => 'sending', 'session_id' => $conversation->session_id,
                        'requested_at' => now()->toIso8601String(),
                        // Preserve actual routing separately from the returned session.
                        // Never include content, attachment URLs, or credentials here.
                        'request' => array_intersect_key($params, array_flip(['orgid', 'sessionid', 'from', 'to', 'type', 'timestamp'])),
                    ];
                    $sync->update(['parts' => $parts]);
                    $this->status($sync, 'sending');
                    try {
                        $response = $this->api->message($params);
                        if (empty($response['messageid'])) {
                            throw new RuntimeException('Ringotel did not return a message ID.');
                        }
                    } catch (\Throwable $e) {
                        $parts[$key]['status'] = 'uncertain';
                        $sync->update(['parts' => $parts]);
                        $this->status($sync, 'uncertain', $e->getMessage());
                        return;
                    }

                    // Save acceptance before any other work so successful attachments are
                    // not copied again if a later attachment or session update fails.
                    $parts[$key] = array_merge($parts[$key], [
                        'status' => 'success',
                        'message_id' => $response['messageid'],
                        'session_id' => $response['sessionid'] ?? $conversation->session_id,
                    ]);
                    $sync->update(['parts' => $parts]);
                    if (!empty($response['sessionid']) && $response['sessionid'] !== $conversation->session_id) {
                        $conversation->update(['session_id' => $response['sessionid'], 'observed_at' => now()]);
                    }
                }
                $this->status($sync, 'success');
            } catch (\Throwable $e) {
                $this->status($sync, 'failed', $e->getMessage());
            }
        });
    }

    /** Text and attachments are separate Ringotel messages in the same session. */
    public function payloads(Messages $message): array
    {
        $payloads = [];
        if ((string) $message->message !== '') {
            $payloads['text'] = ['content' => $message->message, 'type' => 1];
        }
        $seen = [];
        foreach ($message->media ?? [] as $index => $media) {
            $path = $media['access_path'] ?? null;
            if (!$path) {
                throw new RuntimeException('An MMS attachment has no accessible media URL.');
            }
            $url = filter_var($path, FILTER_VALIDATE_URL) ? $path : url($path);
            if (!isset($seen[$url])) {
                $payloads['media_'.$index] = ['content' => $url, 'type' => 7];
                $seen[$url] = true;
            }
        }
        // Carrier group messages are MMS even when their only content is text.
        // Keep the attachment-loss guard for legacy one-to-one MMS records.
        if (!$payloads || ($message->type === 'mms' && !$seen && !$message->message_group_uuid)) {
            throw new RuntimeException('The message has no content or its MMS attachments are unavailable.');
        }
        return $payloads;
    }

    protected function status(RingotelMessageSync $sync, string $status, ?string $error = null): void
    {
        DB::transaction(function () use ($sync, $status, $error) {
            $parent = RingotelMessageSync::where('message_uuid', $sync->message_uuid)->lockForUpdate()->firstOrFail();
            $sync->update(['status' => $status, 'error' => $error]);
            $targets = RingotelMessageDelivery::where('message_uuid', $sync->message_uuid)->get();
            $states = $targets->pluck('status')->unique();
            $aggregate = $states->count() === 1 ? $states->first() : 'partial';
            $errors = $targets->pluck('error')->filter()->implode('; ') ?: null;
            $primary = $targets->firstWhere('ringotel_conversation_uuid', $parent->ringotel_conversation_uuid);
            $parent->update(['status' => $aggregate, 'error' => $errors, 'parts' => $primary?->parts]);
            $this->messages->markRingotelStatus($sync->message_uuid, $aggregate, $errors);
        });
        if ($error) {
            logger()->warning('Ringotel message synchronization needs attention.', [
                'message_uuid' => $sync->message_uuid, 'status' => $status, 'error' => $error,
            ]);
        }
    }
}
