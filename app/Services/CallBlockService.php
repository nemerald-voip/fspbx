<?php

namespace App\Services;

use App\Models\CallBlock;
use App\Models\Dialplans;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class CallBlockService
{
    private const CALL_BLOCK_DIALPLAN_APP_UUID = 'b1b31930-d0ee-4395-a891-04df94599f1f';
    private const CALL_BLOCK_DIALPLAN_XML = <<<'XML'
<extension name="call_block" number="" context="${domain_name}" continue="true" app_uuid="b1b31930-d0ee-4395-a891-04df94599f1f" enabled="true" order="40">
	<condition field="${call_direction}" expression="^(inbound|outbound)$" >
		<action application="lua" data="lua/call_block.lua"/>
	</condition>
</extension>
XML;

    public function save(array $validated, array $parsedAction, ?CallBlock $callBlock = null): CallBlock
    {
        return DB::transaction(function () use ($validated, $parsedAction, $callBlock) {
            $callBlock ??= new CallBlock();
            $isNew = ! $callBlock->exists;
            [$app, $data] = $parsedAction;

            $extensionUuid = userCheckPermission('call_block_view_all_records')
                ? ($validated['extension_uuid'] ?? null)
                : optional(auth()->user())->extension_uuid;

            $callBlock->forceFill([
                'domain_uuid' => session('domain_uuid'),
                'call_block_uuid' => $callBlock->call_block_uuid ?: (string) Str::uuid(),
                'call_block_direction' => $validated['call_block_direction'],
                'extension_uuid' => $extensionUuid,
                'call_block_name' => $this->blankToNull($validated['call_block_name'] ?? null),
                'call_block_country_code' => $this->blankToNull($validated['call_block_country_code'] ?? null),
                'call_block_number' => $this->blankToNull($validated['call_block_number'] ?? null),
                'call_block_app' => $app,
                'call_block_data' => $app === 'voicemail' ? $this->blankToNull($data) : null,
                'call_block_count' => $callBlock->call_block_count ?? 0,
                'call_block_enabled' => $validated['call_block_enabled'],
                'call_block_description' => $this->blankToNull($validated['call_block_description'] ?? null),
                'date_added' => $callBlock->date_added ?: time(),
                $isNew ? 'insert_date' : 'update_date' => now(),
                $isNew ? 'insert_user' : 'update_user' => session('user_uuid'),
            ])->save();

            $this->enableCallBlockDialplanIfNeeded();
            $this->bumpRuleCacheVersion(session('domain_uuid'));

            return $callBlock;
        });
    }

    public function toggle(Collection $callBlocks): void
    {
        DB::transaction(function () use ($callBlocks) {
            foreach ($callBlocks as $callBlock) {
                $callBlock->forceFill([
                    'call_block_enabled' => $callBlock->call_block_enabled === 'true' ? 'false' : 'true',
                    'update_date' => now(),
                    'update_user' => session('user_uuid'),
                ])->save();
            }

            $this->bumpRuleCacheVersion(session('domain_uuid'));
        });
    }

    public function delete(Collection $callBlocks): int
    {
        return DB::transaction(function () use ($callBlocks) {
            $deleted = CallBlock::query()
                ->whereIn('call_block_uuid', $callBlocks->pluck('call_block_uuid'))
                ->delete();

            $this->bumpRuleCacheVersion(session('domain_uuid'));

            return $deleted;
        });
    }

    public function bumpRuleCacheVersion(?string $domainUuid): void
    {
        if (! $domainUuid) {
            return;
        }

        try {
            $key = "call_block:version:{$domainUuid}";
            $redis = Redis::connection('freeswitch');
            $version = $redis->incr($key);

            if ((int) $version === 1) {
                $redis->incr($key);
            }
        } catch (\Throwable $e) {
            logger('CallBlockService Redis version bump failed: ' . $e->getMessage());
        }
    }

    private function enableCallBlockDialplanIfNeeded(): void
    {
        $updated = Dialplans::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('app_uuid', self::CALL_BLOCK_DIALPLAN_APP_UUID)
            ->where(function ($query) {
                $query->where('dialplan_enabled', '<>', 'true')
                    ->orWhereNull('dialplan_xml')
                    ->orWhere('dialplan_xml', '<>', self::CALL_BLOCK_DIALPLAN_XML);
            })
            ->update([
                'dialplan_enabled' => 'true',
                'dialplan_xml' => self::CALL_BLOCK_DIALPLAN_XML,
                'update_date' => now(),
                'update_user' => session('user_uuid'),
            ]);

        if ($updated > 0) {
            app(DialplanService::class)->clearDialplanCache(session('domain_name'));
        }
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
