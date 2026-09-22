<?php

namespace App\Services\Messaging;

use App\Models\Messages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageReadService
{
    public function notify(string $domainUuid, string $local): void
    {
        try {
            foreach (app(MessageParticipantService::class)->members($domainUuid, $local) as $member) {
                broadcast(new \App\Events\ConversationUpdated(['unread_changed' => true], $member->extension_uuid));
            }
        } catch (\Throwable $e) {
            logger()->warning('Unable to broadcast message unread change.', ['error' => $e->getMessage()]);
        }
    }

    public function unread(string $domainUuid, string $userUuid): Builder
    {
        return Messages::query()->where('messages.domain_uuid', $domainUuid)
            ->where('messages.direction', 'in')
            ->whereNotExists(function ($q) use ($userUuid) {
                $q->selectRaw('1')->from('message_user_reads as reads')
                    ->whereColumn('reads.message_uuid', 'messages.message_uuid')
                    ->whereColumn('reads.domain_uuid', 'messages.domain_uuid')
                    ->where('reads.user_uuid', $userUuid);
            })
            // A reply clears only messages preceding that reply, even if carrier
            // acceptance arrives after a newer customer message.
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('messages as replies')
                    ->whereColumn('replies.domain_uuid', 'messages.domain_uuid')
                    ->whereColumn('replies.source', 'messages.destination')
                    ->where(function ($scope) {
                        $scope->where(fn ($group) => $group->whereNotNull('messages.message_group_uuid')
                            ->whereColumn('replies.message_group_uuid', 'messages.message_group_uuid'))
                            ->orWhere(fn ($direct) => $direct->whereNull('messages.message_group_uuid')
                                ->whereNull('replies.message_group_uuid')
                                ->whereColumn('replies.destination', 'messages.source'));
                    })
                    ->where('replies.direction', 'out')
                    ->whereColumn('replies.created_at', '>=', 'messages.created_at')
                    ->whereNotNull('replies.delivery_meta->outbound->provider->accepted_at');
            });
    }

    public function markRead(string $domainUuid, string $userUuid, string $local, string $remote, array $messageUuids, ?string $authorizedExtensionUuid = null): int
    {
        // Only acknowledge messages actually fetched by this client, never a
        // concurrently arriving message or another tenant's submitted UUID.
        $ids = app(MessageGroupService::class)->conversation(
            Messages::where('domain_uuid', $domainUuid)->where('direction', 'in'), $local, $remote)
            ->whereIn('message_uuid', $messageUuids)->pluck('message_uuid');
        if ($ids->isEmpty()) return 0;
        $inserted = DB::table('message_user_reads')->insertOrIgnore($ids->map(fn ($id) => [
            'message_user_read_uuid' => (string) Str::uuid(), 'domain_uuid' => $domainUuid,
            'user_uuid' => $userUuid, 'message_uuid' => $id, 'read_at' => now(),
        ])->all());
        if ($inserted) {
            $this->notify($domainUuid, $local);
        }
        // Ringotel's read API changes checkmarks only, not its unread count.
        // Personal unread state is maintained independently in FS PBX.
        return $inserted;
    }
}
