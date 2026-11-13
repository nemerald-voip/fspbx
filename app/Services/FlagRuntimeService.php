<?php

namespace App\Services;

use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\FlagOverride;
use App\Models\FusionCache;
use App\Models\IvrMenus;
use App\Models\RingGroups;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class FlagRuntimeService
{
    public const TOGGLE_UUID = '6f1b8a52-3c0e-4d7b-9a61-2f4e8c7d5b13';
    private const APP_UUID = 'b8e3d1f4-6a2c-4e95-8d07-4c1f9e2a7b60';

    /** Called in the same transaction as a Default Settings change. */
    public function reconcile(): void
    {
        $enabled = app(FlagFeature::class)->enabled();
        if ($enabled) {
            foreach (['flags', 'flag_rules', 'flag_extensions', 'flag_overrides'] as $table) {
                if (! Schema::hasTable($table)) {
                    throw ValidationException::withMessages([
                        'default_setting_value' => __('Run the database migrations before enabling Flags.'),
                    ]);
                }
            }
            if (! Schema::hasColumn('flag_overrides', 'match_state')) {
                throw ValidationException::withMessages([
                    'default_setting_value' => __('Run the database migrations before enabling Flags.'),
                ]);
            }
        }

        $globalChanged = $this->toggleDialplan($enabled);
        $contexts = [];

        if (Schema::hasTable('flag_overrides')) {
            $owners = FlagOverride::query()->select(['domain_uuid', 'owner_type', 'owner_uuid'])->distinct()->get();
            $renderer = app(FlagDialplanRenderer::class);
            foreach ($owners as $assignment) {
                $model = match ($assignment->owner_type) {
                    FlagOverride::OWNER_RING_GROUP => RingGroups::class,
                    FlagOverride::OWNER_IVR_MENU => IvrMenus::class,
                    default => null,
                };
                $owner = $model ? $model::query()->where('domain_uuid', $assignment->domain_uuid)
                    ->find($assignment->owner_uuid) : null;
                if (! $owner) {
                    continue;
                }

                // Preserve the owner's existing dialplan and account, independent of the operator's session.
                $dialplan = Dialplans::query()->where('domain_uuid', $assignment->domain_uuid)
                    ->find($owner->dialplan_uuid);
                if (! $dialplan) {
                    continue;
                }
                $xml = $owner instanceof RingGroups ? $renderer->ringGroup($owner) : $renderer->ivr($owner);
                if ($dialplan->dialplan_xml !== $xml) {
                    $dialplan->update(['dialplan_xml' => $xml]);
                    $contexts[] = $dialplan->dialplan_context;
                }
            }
        }

        if ($globalChanged || $contexts) {
            DB::afterCommit(fn () => $this->refresh(array_values(array_unique(array_filter($contexts))), $globalChanged));
        }
    }

    private function toggleDialplan(bool $enabled): bool
    {
        $dialplan = Dialplans::query()->find(self::TOGGLE_UUID);
        if ($dialplan && ($dialplan->app_uuid !== self::APP_UUID || $dialplan->domain_uuid !== null)) {
            throw new RuntimeException('The Flags toggle dialplan UUID belongs to another application.');
        }

        if (! $enabled) {
            if (! $dialplan) {
                return false;
            }
            DialplanDetails::query()->where('dialplan_uuid', self::TOGGLE_UUID)->delete();
            $dialplan->delete();

            return true;
        }

        $expression = '^flag(\d+)$';
        $xml = '<extension name="toggle-flag" continue="false" uuid="'.self::TOGGLE_UUID.'">' . "\n"
            .'    <condition field="destination_number" expression="'.$expression.'">' . "\n"
            .'        <action application="lua" data="lua/flag_toggle.lua"/>' . "\n"
            .'    </condition>' . "\n" . '</extension>';
        $dialplan ??= new Dialplans(['dialplan_uuid' => self::TOGGLE_UUID]);
        $dialplan->fill([
            'app_uuid' => self::APP_UUID, 'domain_uuid' => null, 'hostname' => null,
            'dialplan_context' => 'global', 'dialplan_name' => 'toggle-flag',
            'dialplan_number' => 'flag[number]', 'dialplan_continue' => 'false',
            'dialplan_xml' => $xml, 'dialplan_order' => 509, 'dialplan_enabled' => 'true',
            'dialplan_description' => 'Switch a manual Flag on or off.',
        ]);
        $changed = ! $dialplan->exists || $dialplan->isDirty();
        if ($changed) {
            $dialplan->save();
        }

        foreach ([['condition', 'destination_number', $expression, 5], ['action', 'lua', 'lua/flag_toggle.lua', 10]] as [$tag, $type, $data, $order]) {
            $detail = DialplanDetails::query()->firstOrNew([
                'dialplan_uuid' => self::TOGGLE_UUID,
                'dialplan_detail_tag' => $tag, 'dialplan_detail_type' => $type,
            ]);
            $detail->fill([
                'domain_uuid' => null, 'dialplan_detail_data' => $data,
                'dialplan_detail_group' => 0, 'dialplan_detail_order' => $order,
                'dialplan_detail_enabled' => 'true',
            ]);
            if (! $detail->exists || $detail->isDirty()) {
                $detail->save();
                $changed = true;
            }
        }

        return $changed;
    }

    protected function refresh(array $contexts, bool $globalChanged): void
    {
        try {
            // The global toggle is included in every account context.
            if ($globalChanged) {
                FusionCache::clearPattern('dialplan.*');
            } else {
                foreach ($contexts as $context) {
                    FusionCache::clear('dialplan:'.$context);
                }
            }
            $response = app(FreeswitchEslService::class)->executeCommand('reloadxml');
            if ($response === null || (is_string($response) && str_contains($response, '-ERR'))) {
                logger()->warning('Flags dialplan reload failed: '.($response ?? 'FreeSWITCH is unavailable.'));
            }
        } catch (Throwable $error) {
            logger()->warning('Flags settings saved, but dialplan refresh failed: '.$error->getMessage());
        }
    }
}
