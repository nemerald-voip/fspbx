<?php

namespace App\Services;

use App\Models\DomainSettings;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PmsProviderSettings
{
    public const CHARPMS = 'charpms';
    public const TIGERTMS = 'tigertms';

    public function provider(?string $domainUuid = null): string
    {
        $provider = strtolower(trim((string) get_domain_setting('pms_provider', $domainUuid)));

        return in_array($provider, $this->providerKeys(), true)
            ? $provider
            : self::CHARPMS;
    }

    public function isCharPms(?string $domainUuid = null): bool
    {
        return $this->provider($domainUuid) === self::CHARPMS;
    }

    public function isTigerTms(?string $domainUuid = null): bool
    {
        return $this->provider($domainUuid) === self::TIGERTMS;
    }

    public function options(): array
    {
        return [
            ['value' => self::CHARPMS, 'label' => 'CharPMS'],
            ['value' => self::TIGERTMS, 'label' => 'TigerTMS'],
        ];
    }

    public function saveProvider(string $domainUuid, string $provider): DomainSettings
    {
        $provider = strtolower(trim($provider));

        if (!in_array($provider, $this->providerKeys(), true)) {
            throw new InvalidArgumentException('Invalid PMS provider.');
        }

        $setting = DomainSettings::query()->firstOrNew([
            'domain_uuid' => $domainUuid,
            'domain_setting_category' => 'pms',
            'domain_setting_subcategory' => 'pms_provider',
            'domain_setting_name' => 'text',
        ]);

        if (!$setting->exists) {
            $setting->domain_setting_uuid = (string) Str::uuid();
        }

        $setting->domain_setting_value = $provider;
        $setting->domain_setting_enabled = true;
        $setting->domain_setting_description = 'Hotel PMS provider for this tenant.';
        $setting->save();

        return $setting;
    }

    private function providerKeys(): array
    {
        return [self::CHARPMS, self::TIGERTMS];
    }
}
