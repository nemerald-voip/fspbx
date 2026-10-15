<?php

namespace App\Services\Messaging;

use App\Models\DomainSettings;
use App\Models\DefaultSettings;
use Illuminate\Support\Facades\DB;

class PhotoCompressionSettings
{
    private const OPTIONS = [
        'enabled' => ['subcategory' => 'compress_photos', 'default' => false,
            'description' => 'Reduce outbound photo file sizes before carrier delivery'],
        'convert_photos' => ['subcategory' => 'convert_photos', 'default' => true,
            'description' => 'Convert supported outbound photo formats to JPEG before carrier delivery'],
    ];

    protected function query(string $domainUuid)
    {
        return DomainSettings::where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', 'messaging')
            ->whereIn('domain_setting_subcategory', array_column(self::OPTIONS, 'subcategory'))
            ->where('domain_setting_name', 'boolean');
    }

    public function settings(?string $domainUuid = null): array
    {
        // Keep the established default-setting keys; account rows are overrides.
        $defaults = DefaultSettings::where('default_setting_category', 'messaging')
            ->whereIn('default_setting_subcategory', ['compress_photos_setting', 'convert_photos_setting'])
            ->where('default_setting_name', 'boolean')
            ->where('default_setting_enabled', 'true')->get();
        $overrides = $domainUuid ? $this->query($domainUuid)->get() : collect();
        $values = ['scope' => $overrides->isEmpty() ? 'system' : 'domain'];

        foreach (self::OPTIONS as $key => $option) {
            $default = $defaults->firstWhere('default_setting_subcategory', $option['subcategory'].'_setting');
            $override = $overrides->firstWhere('domain_setting_subcategory', $option['subcategory']);
            $values[$key] = $override
                ? (filter_var($override->domain_setting_enabled, FILTER_VALIDATE_BOOLEAN)
                    && $override->domain_setting_value === 'true')
                : ($default ? $default->default_setting_value === 'true' : $option['default']);
        }

        return $values;
    }

    public function enabled(string $domainUuid): bool
    {
        return $this->settings($domainUuid)['enabled'];
    }

    public function set(string $domainUuid, bool $enabled): void
    {
        $this->save($domainUuid, ['enabled' => $enabled]);
    }

    public function conversionEnabled(string $domainUuid): bool
    {
        return $this->settings($domainUuid)['convert_photos'];
    }

    public function setConversion(string $domainUuid, bool $enabled): void
    {
        $this->save($domainUuid, ['convert_photos' => $enabled]);
    }

    public function save(?string $domainUuid, array $values): array
    {
        DB::transaction(function () use ($domainUuid, $values) {
            foreach (self::OPTIONS as $key => $option) {
                if (!array_key_exists($key, $values)) continue;

                if ($domainUuid) {
                    $setting = $this->query($domainUuid)
                        ->where('domain_setting_subcategory', $option['subcategory'])->first()
                        ?? new DomainSettings([
                            'domain_uuid' => $domainUuid, 'domain_setting_category' => 'messaging',
                            'domain_setting_subcategory' => $option['subcategory'], 'domain_setting_name' => 'boolean',
                        ]);
                    $prefix = 'domain_setting_';
                } else {
                    $setting = DefaultSettings::firstOrNew([
                        'default_setting_category' => 'messaging',
                        'default_setting_subcategory' => $option['subcategory'].'_setting',
                        'default_setting_name' => 'boolean',
                    ]);
                    $prefix = 'default_setting_';
                }

                $setting->{$prefix.'value'} = $values[$key] ? 'true' : 'false';
                $setting->{$prefix.'enabled'} = 'true';
                $setting->{$prefix.'description'} ??= $option['description'];
                $setting->save();
            }
        });

        return $this->settings($domainUuid);
    }

    public function revert(string $domainUuid): array
    {
        $this->query($domainUuid)->delete();

        return $this->settings($domainUuid);
    }
}
