<?php

namespace App\Services\Provisioning;

use App\Models\DefaultSettings;
use App\Models\DomainSettings;
use Illuminate\Support\Collection;

class ProvisioningSettingsResolver
{
    private const CATEGORY = 'provision';

    /**
     * Resolve enabled provisioning settings for a domain.
     *
     * Domain settings replace matching defaults. Array settings remain arrays,
     * and the presence of enabled domain array rows replaces the default array.
     */
    public function resolve(string $domainUuid): array
    {
        $defaults = DefaultSettings::query()
            ->where('default_setting_category', self::CATEGORY)
            ->where('default_setting_enabled', 'true')
            ->orderBy('default_setting_order')
            ->orderBy('default_setting_uuid')
            ->get([
                'default_setting_subcategory as subcategory',
                'default_setting_name as name',
                'default_setting_value as value',
            ]);

        $domainSettings = DomainSettings::query()
            ->where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', self::CATEGORY)
            ->where('domain_setting_enabled', 'true')
            ->orderBy('domain_setting_order')
            ->orderBy('domain_setting_uuid')
            ->get([
                'domain_setting_subcategory as subcategory',
                'domain_setting_name as name',
                'domain_setting_value as value',
            ]);

        return array_replace(
            $this->settingsFromRows($defaults),
            $this->settingsFromRows($domainSettings)
        );
    }

    private function settingsFromRows(Collection $rows): array
    {
        return $rows
            ->filter(fn ($row) => filled($row->subcategory))
            ->groupBy('subcategory')
            ->map(function (Collection $settings) {
                $arraySettings = $settings
                    ->filter(fn ($setting) => (string) $setting->name === 'array');

                if ($arraySettings->isNotEmpty()) {
                    // Include legacy text rows alongside array rows. FusionPBX
                    // historically accepted both shapes for provisioning CIDRs.
                    return $settings
                        ->pluck('value')
                        ->values()
                        ->all();
                }

                return $settings->first()?->value;
            })
            ->all();
    }
}
