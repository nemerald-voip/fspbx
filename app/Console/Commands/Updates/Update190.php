<?php

namespace App\Console\Commands\Updates;

use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Services\DialplanService;
use Illuminate\Support\Facades\DB;
use Throwable;

class Update190
{
    private const VERSION = '1.9.0';
    private const VALET_PARK_APP_UUID = '3cc8363d-5ce3-48aa-8ac1-143cf297c4f7';
    private const OLD_EFFECTIVE_CALLER_ID = 'effective_caller_id_name=${cond ${regex ${direction} | inbound} == true ? \'park#${caller_id_name}\' : \'park#${callee_id_name}\'}';
    private const LUA_ACTION = 'park_set_callee_id.lua park@${domain_name} *${park_lot}';
    private const VALET_PARK_ACTION = 'park@${domain_name} *${park_lot}';

    public function apply(): bool
    {
        try {
            DB::transaction(function () {
                $this->updateValetParkDialplans();
            });

            echo "Update " . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function updateValetParkDialplans(): void
    {
        $dialplans = Dialplans::query()
            ->where('app_uuid', self::VALET_PARK_APP_UUID)
            ->orWhere(function ($query) {
                $query->where('dialplan_name', 'valet_park')
                    ->where('dialplan_number', 'park+*5901-*5999');
            })
            ->get(['domain_uuid', 'dialplan_uuid', 'dialplan_context', 'dialplan_xml']);

        if ($dialplans->isEmpty()) {
            echo "No Valet Park dialplans found.\n";
            return;
        }

        $updatedXml = 0;
        $contextsToClear = collect();

        foreach ($dialplans as $dialplan) {
            $xml = (string) $dialplan->dialplan_xml;
            $newXml = $this->updateValetParkXml($xml);

            if ($newXml === $xml) {
                continue;
            }

            $dialplan->forceFill([
                'dialplan_xml' => $newXml,
                'update_date' => now(),
            ])->save();

            $updatedXml++;
            $contextsToClear->push($dialplan->dialplan_context);
        }

        $dialplanUuids = $dialplans->pluck('dialplan_uuid')->filter()->values();

        $removedCallerIdDetails = DialplanDetails::query()
            ->whereIn('dialplan_uuid', $dialplanUuids)
            ->where('dialplan_detail_tag', 'anti-action')
            ->where('dialplan_detail_type', 'set')
            ->where('dialplan_detail_data', self::OLD_EFFECTIVE_CALLER_ID)
            ->delete();

        $insertedLuaDetails = $this->ensureLuaDetails($dialplans);

        if ($removedCallerIdDetails > 0 || $insertedLuaDetails > 0) {
            $contextsToClear = $contextsToClear->merge($dialplans->pluck('dialplan_context'));
        }

        $contextsToClear
            ->filter()
            ->unique()
            ->each(fn ($context) => app(DialplanService::class)->clearDialplanCache($context));

        echo "Updated {$updatedXml} Valet Park dialplan XML record(s).\n";
        echo "Removed {$removedCallerIdDetails} Valet Park caller ID detail record(s).\n";
        echo "Inserted {$insertedLuaDetails} Valet Park Lua detail record(s).\n";
    }

    private function updateValetParkXml(string $xml): string
    {
        $updatedXml = preg_replace(
            '/^[ \t]*<anti-action\s+application="set"\s+data="' . preg_quote(self::OLD_EFFECTIVE_CALLER_ID, '/') . '"(?:\s+inline="true")?\s*\/>\R?/m',
            '',
            $xml
        ) ?? $xml;

        if (! str_contains($updatedXml, self::LUA_ACTION)) {
            $updatedXml = preg_replace(
                '/^([ \t]*)<anti-action\s+application="answer"\s+data=""\s*\/>/m',
                "$1<anti-action application=\"lua\" data=\"" . self::LUA_ACTION . "\"/>\n$0",
                $updatedXml,
                1
            ) ?? $updatedXml;
        }

        return $updatedXml;
    }

    private function ensureLuaDetails($dialplans): int
    {
        $inserted = 0;

        foreach ($dialplans as $dialplan) {
            $hasLuaDetail = DialplanDetails::query()
                ->where('dialplan_uuid', $dialplan->dialplan_uuid)
                ->where('dialplan_detail_tag', 'anti-action')
                ->where('dialplan_detail_type', 'lua')
                ->where('dialplan_detail_data', self::LUA_ACTION)
                ->exists();

            if ($hasLuaDetail) {
                continue;
            }

            $parkDetail = DialplanDetails::query()
                ->where('dialplan_uuid', $dialplan->dialplan_uuid)
                ->where('dialplan_detail_tag', 'anti-action')
                ->where('dialplan_detail_type', 'valet_park')
                ->where('dialplan_detail_data', self::VALET_PARK_ACTION)
                ->orderBy('dialplan_detail_group')
                ->orderBy('dialplan_detail_order')
                ->first();

            if (! $parkDetail) {
                continue;
            }

            $answerDetail = DialplanDetails::query()
                ->where('dialplan_uuid', $dialplan->dialplan_uuid)
                ->where('dialplan_detail_group', $parkDetail->dialplan_detail_group)
                ->where('dialplan_detail_tag', 'anti-action')
                ->where('dialplan_detail_type', 'answer')
                ->where('dialplan_detail_order', '<=', $parkDetail->dialplan_detail_order)
                ->orderBy('dialplan_detail_order')
                ->first();

            $targetOrder = $answerDetail?->dialplan_detail_order ?? $parkDetail->dialplan_detail_order;

            DialplanDetails::query()
                ->where('dialplan_uuid', $dialplan->dialplan_uuid)
                ->where('dialplan_detail_group', $parkDetail->dialplan_detail_group)
                ->where('dialplan_detail_order', '>=', $targetOrder)
                ->update([
                    'dialplan_detail_order' => DB::raw('dialplan_detail_order + 5'),
                    'update_date' => now(),
                ]);

            DialplanDetails::query()->create([
                'domain_uuid' => $parkDetail->domain_uuid,
                'dialplan_uuid' => $dialplan->dialplan_uuid,
                'dialplan_detail_tag' => 'anti-action',
                'dialplan_detail_type' => 'lua',
                'dialplan_detail_data' => self::LUA_ACTION,
                'dialplan_detail_group' => $parkDetail->dialplan_detail_group,
                'dialplan_detail_order' => $targetOrder,
                'dialplan_detail_enabled' => 'true',
                'insert_date' => now(),
            ]);

            $inserted++;
        }

        return $inserted;
    }
}
