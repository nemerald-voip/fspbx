<?php

namespace App\Services\Messaging;

use App\Models\Messages;
use App\Models\ScheduledJobExecution;
use App\Services\Ha\ActiveNodeResolver;
use App\Services\MessageMediaObjectStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MessageRetentionService
{
    public function __construct(
        private MessageMediaObjectStorageService $storage,
        private ActiveNodeResolver $activeNode,
    ) {}

    /** Return a cursor when another bounded job is needed to finish this pass. */
    public function deleteExpired(int $days, ScheduledJobExecution $execution, ?string $after = null): ?string
    {
        if ($days < 1) throw new \InvalidArgumentException('Message retention must be a positive number of days.');

        $cutoff = now()->subDays($days);
        $deadline = microtime(true) + 240;
        $tables = array_values(array_filter([
            'message_user_reads', 'message_user_hides', 'ringotel_message_deliveries',
            'ringotel_message_syncs', 'message_api_requests', 'outbound_message_webhook_events',
        ], fn ($table) => Schema::hasTable($table)));
        $hasGroups = Schema::hasTable('message_groups');
        $query = $this->candidates($cutoff, $tables);
        if ($after !== null) $query->where('message_uuid', '>=', $after);

        foreach ($query->lazyById(100, 'message_uuid') as $message) {
            try {
                $this->activeNode->assertExecution($execution);
                foreach ($this->objects($message) as $object) {
                    $this->activeNode->assertExecution($execution);
                    if (microtime(true) >= $deadline) return $message->getKey();
                    if (!$this->isReferencedElsewhere($message, $object)) {
                        $this->storage->deleteObjectForDomain($message->domain_uuid, $object['bucket'], $object['object_key']);
                    }
                }

                // Keep network I/O outside the ownership/write transaction.
                $this->activeNode->withExecution($execution, function () use ($message, $cutoff, $tables, $hasGroups) {
                    $current = $this->candidates($cutoff, $tables)->whereKey($message->getKey())->lockForUpdate()->first();
                    if (!$current || $current->getRawOriginal('media') !== $message->getRawOriginal('media')
                        || $current->getRawOriginal('updated_at') !== $message->getRawOriginal('updated_at')) return;

                    foreach ($tables as $table) {
                        DB::table($table)->where('message_uuid', $message->getKey())->delete();
                    }
                    $current->delete();
                    if ($hasGroups && $message->message_group_uuid) {
                        DB::table('message_groups')->where('message_group_uuid', $message->message_group_uuid)
                            ->where('domain_uuid', $message->domain_uuid)
                            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('messages')
                                ->whereColumn('messages.message_group_uuid', 'message_groups.message_group_uuid'))
                            ->delete();
                    }
                });
            } catch (Throwable $exception) {
                // Revocation stops the entire pass. Storage failures keep the row for the next pass.
                $this->activeNode->assertExecution($execution);
                Log::warning('Message retention could not delete a message; it will be retried.', [
                    'message_uuid' => $message->getKey(), 'exception' => get_class($exception),
                ]);
            }

            if (microtime(true) >= $deadline) return $message->getKey();
        }

        return null;
    }

    private function candidates($cutoff, array $tables): Builder
    {
        return Messages::query()->where('created_at', '<', $cutoff)
            ->where(fn ($query) => $query->whereNull('status')->orWhereNotIn('status', [
                'pending', 'queued', 'sending', 'processing', 'preparing',
            ]))
            ->when(in_array('outbound_message_webhook_events', $tables, true), fn ($query) =>
                $query->whereNotExists(fn ($events) => $events->selectRaw('1')->from('outbound_message_webhook_events')
                    ->whereColumn('outbound_message_webhook_events.message_uuid', 'messages.message_uuid')
                    ->whereIn('status', ['pending', 'queued'])))
            ->when(in_array('message_api_requests', $tables, true), fn ($query) =>
                $query->whereNotExists(fn ($requests) => $requests->selectRaw('1')->from('message_api_requests')
                    ->whereColumn('message_api_requests.message_uuid', 'messages.message_uuid')
                    ->where('expires_at', '>', now())));
    }

    private function objects(Messages $message): array
    {
        $objects = [];
        foreach ($message->mediaItems() as $item) {
            foreach ([$item, data_get($item, 'photo_compression.original', [])] as $object) {
                if (!is_array($object)) continue;
                $bucket = $object['bucket'] ?? null;
                $key = $object['object_key'] ?? null;
                if ($bucket === null && $key === null) continue; // Legacy carrier-hosted URL.
                if (!is_string($bucket) || $bucket === '' || !is_string($key) || $key === '') {
                    throw new \RuntimeException('Incomplete message media object reference.');
                }
                $objects[$bucket . '/' . $key] = ['bucket' => $bucket, 'object_key' => $key];
            }
        }
        return array_values($objects);
    }

    private function isReferencedElsewhere(Messages $message, array $object): bool
    {
        // Include other accounts: multiple accounts can share the default bucket.
        $query = Messages::query()->whereKeyNot($message->getKey())->whereNotNull('media');
        $name = basename($object['object_key']);
        if (preg_match('/\A[a-zA-Z0-9._-]+\z/', $name)) {
            // Generated names are UUIDs. This also matches legacy double-encoded JSON.
            $query->whereRaw("CAST(media AS TEXT) LIKE ? ESCAPE '!'", ['%' . str_replace('_', '!_', $name) . '%']);
        }
        foreach ($query->lazyById(100, 'message_uuid') as $other) {
            foreach ($this->objects($other) as $reference) {
                if ($reference === $object) return true;
            }
        }
        return false;
    }
}
