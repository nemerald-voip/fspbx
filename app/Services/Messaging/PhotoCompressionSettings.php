<?php

namespace App\Services\Messaging;

use App\Models\DomainSettings;
use App\Models\DefaultSettings;

class PhotoCompressionSettings
{
    public function initializeForNewDomain(string $domainUuid): void
    {
        // Snapshot once. Existing accounts must never inherit later global changes.
        if ($this->query($domainUuid)->exists()) return;
        $enabled = DefaultSettings::where('default_setting_category', 'messaging')
            ->where('default_setting_subcategory', 'compress_photos_setting')
            ->where('default_setting_name', 'boolean')
            ->where('default_setting_enabled', 'true')
            ->value('default_setting_value') === 'true';
        $this->set($domainUuid, $enabled);
    }

    protected function query(string $domainUuid)
    {
        return DomainSettings::where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', 'messaging')
            ->where('domain_setting_subcategory', 'compress_photos')
            ->where('domain_setting_name', 'boolean');
    }

    public function enabled(string $domainUuid): bool
    {
        return $this->query($domainUuid)->where('domain_setting_enabled', 'true')
            ->value('domain_setting_value') === 'true';
    }

    public function set(string $domainUuid, bool $enabled): void
    {
        $setting = $this->query($domainUuid)->first() ?? new DomainSettings([
            'domain_uuid' => $domainUuid, 'domain_setting_category' => 'messaging',
            'domain_setting_subcategory' => 'compress_photos', 'domain_setting_name' => 'boolean',
        ]);
        $setting->domain_setting_value = $enabled ? 'true' : 'false';
        $setting->domain_setting_enabled = 'true';
        $setting->domain_setting_description = 'Compress outbound photos before carrier delivery';
        $setting->save();
    }
}
