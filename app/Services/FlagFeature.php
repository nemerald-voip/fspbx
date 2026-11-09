<?php

namespace App\Services;

use App\Models\DefaultSettings;
use Throwable;

class FlagFeature
{
    public function enabled(): bool
    {
        try {
            // Never use session settings or account overrides for this server-wide switch.
            $rows = DefaultSettings::query()
                ->where('default_setting_category', 'flags')
                ->where('default_setting_subcategory', 'enabled')
                ->where('default_setting_name', 'boolean')
                ->get(['default_setting_enabled', 'default_setting_value']);

            return $rows->count() === 1
                && $this->truthy($rows->first()->default_setting_enabled)
                && $this->truthy($rows->first()->default_setting_value);
        } catch (Throwable) {
            return false;
        }
    }

    public function canAssign(string $ownerPermission): bool
    {
        return userCheckPermission($ownerPermission)
            && userCheckPermission('flags_assign')
            && $this->enabled();
    }

    public static function isSetting(?DefaultSettings $setting): bool
    {
        return $setting?->default_setting_category === 'flags'
            && $setting->default_setting_subcategory === 'enabled'
            && $setting->default_setting_name === 'boolean';
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true'], true);
    }
}
