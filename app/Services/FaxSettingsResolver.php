<?php

namespace App\Services;

use App\Models\DefaultSettings;
use App\Models\DomainSettings;

class FaxSettingsResolver
{
    private const CATEGORY = 'fax';

    public function boolean(string $subcategory, ?string $domainUuid = null, bool $fallback = false): bool
    {
        if ($domainUuid) {
            $domainSetting = DomainSettings::query()
                ->where('domain_uuid', $domainUuid)
                ->where('domain_setting_category', self::CATEGORY)
                ->where('domain_setting_subcategory', $subcategory)
                ->where('domain_setting_name', 'boolean')
                ->where('domain_setting_enabled', 'true')
                ->first(['domain_setting_value']);

            if ($domainSetting) {
                return filter_var($domainSetting->domain_setting_value, FILTER_VALIDATE_BOOLEAN);
            }
        }

        $defaultSetting = DefaultSettings::query()
            ->where('default_setting_category', self::CATEGORY)
            ->where('default_setting_subcategory', $subcategory)
            ->where('default_setting_name', 'boolean')
            ->where('default_setting_enabled', 'true')
            ->first(['default_setting_value']);

        if (! $defaultSetting) {
            return $fallback;
        }

        return filter_var($defaultSetting->default_setting_value, FILTER_VALIDATE_BOOLEAN);
    }
}
