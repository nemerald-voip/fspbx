<?php

namespace App\Services;

use App\Models\MusicStreams;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StreamService
{
    public function visible(): Builder
    {
        abort_unless(session('domain_uuid'), 403);
        return MusicStreams::query()->where(fn ($q) => $q
            ->where('domain_uuid', session('domain_uuid'))->orWhereNull('domain_uuid'));
    }

    public function canManage(MusicStreams $stream): bool
    {
        return $stream->domain_uuid === session('domain_uuid')
            || ($stream->domain_uuid === null && userCheckPermission('stream_all'));
    }

    public function save(array $values, ?MusicStreams $stream = null): MusicStreams
    {
        abort_unless(session('domain_uuid'), 403);
        if ($stream) {
            abort_unless($this->canManage($stream), 403);
        }
        $domain = array_key_exists('domain_uuid', $values)
            ? $values['domain_uuid'] : ($stream ? $stream->domain_uuid : session('domain_uuid'));
        abort_unless($domain === session('domain_uuid') || ($domain === null && userCheckPermission('stream_all')), 403);
        $stream ??= new MusicStreams();
        $stream->forceFill(collect($values)->only([
            'stream_name', 'stream_location', 'stream_enabled', 'stream_description',
        ])->all() + ['domain_uuid' => $domain])->save();

        return $stream;
    }

    public function bulk(array $ids, string $action): void
    {
        DB::transaction(function () use ($ids, $action) {
            $items = $this->visible()->whereKey($ids)->lockForUpdate()->get();
            abort_unless($items->count() === count(array_unique($ids)), 404);
            foreach ($items as $item) {
                // Copying a shared stream creates an account-owned copy.
                abort_unless($action === 'copy' || $this->canManage($item), 403);
            }
            foreach ($items as $item) {
                if ($action === 'delete') {
                    $item->delete();
                } elseif ($action === 'copy') {
                    $copy = $item->replicate();
                    $copy->domain_uuid = session('domain_uuid');
                    $copy->stream_name = mb_substr($item->stream_name, 0, 248).' (Copy)';
                    $copy->save();
                } else {
                    $item->stream_enabled = $action === 'enable' ? 'true' : 'false';
                    $item->save();
                }
            }
        });
    }
}
