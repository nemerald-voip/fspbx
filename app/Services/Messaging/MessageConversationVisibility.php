<?php

namespace App\Services\Messaging;

use App\Models\Messages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageConversationVisibility
{
    public function visible(Builder $query, string $userUuid): Builder
    {
        return $query->whereNotExists(function ($q) use ($userUuid) {
            $q->selectRaw('1')->from('message_user_hides as hides')
                ->whereColumn('hides.message_uuid', 'messages.message_uuid')
                ->whereColumn('hides.domain_uuid', 'messages.domain_uuid')
                ->where('hides.user_uuid', $userUuid);
        });
    }

    public function hide(string $domainUuid, string $userUuid, string $local, string $remote): void
    {
        $this->record($domainUuid, $userUuid, $local, $remote, false);
    }

    public function deleteForUser(string $domainUuid, string $userUuid, string $local, string $remote): void
    {
        $this->record($domainUuid, $userUuid, $local, $remote, true);
    }

    public function history(Builder $query, string $userUuid): Builder
    {
        return $query->whereNotExists(function ($q) use ($userUuid) {
            $q->selectRaw('1')->from('message_user_hides as hides')
                ->whereColumn('hides.message_uuid', 'messages.message_uuid')
                ->whereColumn('hides.domain_uuid', 'messages.domain_uuid')
                ->where('hides.user_uuid', $userUuid)->where('hides.history_deleted', true);
        });
    }

    private function record(string $domainUuid, string $userUuid, string $local, string $remote, bool $permanent): void
    {
        // Snapshot exact messages: a concurrent/new message (even in the same
        // second) reopens the conversation. Never delete shared history or receipts.
        $ids = app(MessageGroupService::class)->conversation(Messages::where('domain_uuid', $domainUuid), $local, $remote)
            ->pluck('message_uuid');

        DB::transaction(function () use ($ids, $domainUuid, $userUuid, $permanent) {
            foreach ($ids->chunk(300) as $chunk) {
                DB::table('message_user_hides')->insertOrIgnore($chunk->map(fn ($id) => [
                    'message_user_hide_uuid' => (string) Str::uuid(),
                    'domain_uuid' => $domainUuid, 'user_uuid' => $userUuid, 'message_uuid' => $id,
                    'history_deleted' => $permanent,
                ])->values()->all());
                if ($permanent) {
                    DB::table('message_user_hides')->where('domain_uuid', $domainUuid)
                        ->where('user_uuid', $userUuid)->whereIn('message_uuid', $chunk)
                        ->update(['history_deleted' => true]);
                }
            }
        });
    }
}
