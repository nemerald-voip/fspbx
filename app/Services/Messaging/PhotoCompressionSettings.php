<?php

namespace App\Services\Messaging;

use App\Models\DomainSettings;
use App\Models\DefaultSettings;

class PhotoCompressionSettings
{
    public function initializeForNewDomain(string $domainUuid): void
    {
        // Snapshot once. Existing accounts must never inherit later global changes.
        if (!$this->query($domainUuid)->exists()) {
            $enabled = DefaultSettings::where('default_setting_category', 'messaging')
                ->where('default_setting_subcategory', 'compress_photos_setting')
                ->where('default_setting_name', 'boolean')
                ->where('default_setting_enabled', 'true')
                ->value('default_setting_value') === 'true';
            $this->set($domainUuid, $enabled);
        }
        if (!$this->query($domainUuid, 'convert_photos')->exists()) {
            $default = DefaultSettings::where('default_setting_category', 'messaging')
                ->where('default_setting_subcategory', 'convert_photos_setting')
                ->where('default_setting_name', 'boolean')
                ->where('default_setting_enabled', 'true')->first();
            $this->setConversion($domainUuid, !$default || $default->default_setting_value === 'true');
        }
    }

    protected function query(string $domainUuid, string $subcategory = 'compress_photos')
    {
        return DomainSettings::where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', 'messaging')
            ->where('domain_setting_subcategory', $subcategory)
            ->where('domain_setting_name', 'boolean');
    }

    public function enabled(string $domainUuid): bool
    {
        return $this->query($domainUuid)->where('domain_setting_enabled', 'true')
            ->value('domain_setting_value') === 'true';
    }

    public function set(string $domainUuid, bool $enabled): void
    {
        $this->saveSetting($domainUuid, 'compress_photos', $enabled, 'Compress outbound photos before carrier delivery');
    }

    public function conversionEnabled(string $domainUuid): bool
    {
        $setting = $this->query($domainUuid, 'convert_photos')->first();
        // Preserve conversion for existing accounts without an explicit setting.
        return !$setting || (filter_var($setting->domain_setting_enabled, FILTER_VALIDATE_BOOLEAN)
            && $setting->domain_setting_value === 'true');
    }

    public function setConversion(string $domainUuid, bool $enabled): void
    {
        $this->saveSetting($domainUuid, 'convert_photos', $enabled, 'Convert outbound photos to JPEG before carrier delivery');
    }

    protected function saveSetting(string $domainUuid, string $subcategory, bool $enabled, string $description): void
    {
        $setting = $this->query($domainUuid, $subcategory)->first() ?? new DomainSettings([
            'domain_uuid' => $domainUuid, 'domain_setting_category' => 'messaging',
            'domain_setting_subcategory' => $subcategory, 'domain_setting_name' => 'boolean',
        ]);
        $setting->domain_setting_value = $enabled ? 'true' : 'false';
        $setting->domain_setting_enabled = 'true';
        $setting->domain_setting_description = $description;
        $setting->save();
    }
}
