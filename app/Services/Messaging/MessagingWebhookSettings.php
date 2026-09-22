<?php

namespace App\Services\Messaging;

use App\Models\DefaultSettings;
use App\Models\DomainSettings;
use Illuminate\Support\Str;

class MessagingWebhookSettings
{
    public function initializeForNewDomain(string $domainUuid): void
    {
        if ($this->urlQuery($domainUuid)->exists()) {
            return;
        }

        $url = DefaultSettings::query()
            ->where('default_setting_category', 'messaging')
            ->where('default_setting_subcategory', 'webhook_url')
            ->where('default_setting_name', 'text')
            ->where('default_setting_enabled', 'true')
            ->value('default_setting_value');

        $this->set($domainUuid, filled($url) ? trim((string) $url) : null, false);
    }

    public function get(string $domainUuid): array
    {
        $url = $this->urlQuery($domainUuid)->value('domain_setting_value');
        $enabled = $this->enabledQuery($domainUuid)->value('domain_setting_value') === 'true';

        return [
            'webhook_url' => $url ?: null,
            'webhook_enabled' => $enabled && filled($url),
        ];
    }

    public function set(string $domainUuid, ?string $url, bool $enabled): void
    {
        $url = filled($url) ? trim((string) $url) : null;
        $this->upsert($this->urlQuery($domainUuid), $domainUuid, 'webhook_url', 'text', $url ?? '', 'Tenant messaging webhook URL');
        $this->upsert($this->enabledQuery($domainUuid), $domainUuid, 'webhook_enabled', 'boolean', $enabled && $url ? 'true' : 'false', 'Enable tenant messaging webhook delivery');

        if ($url && ! $this->secretQuery($domainUuid)->exists()) {
            $this->upsert($this->secretQuery($domainUuid), $domainUuid, 'webhook_secret', 'text', Str::random(64), 'Tenant messaging webhook signing secret');
        }
    }

    public function signingSecret(string $domainUuid): ?string
    {
        return $this->secretQuery($domainUuid)->value('domain_setting_value') ?: null;
    }

    private function urlQuery(string $domainUuid)
    {
        return $this->query($domainUuid, 'webhook_url', 'text');
    }

    private function enabledQuery(string $domainUuid)
    {
        return $this->query($domainUuid, 'webhook_enabled', 'boolean');
    }

    private function secretQuery(string $domainUuid)
    {
        return $this->query($domainUuid, 'webhook_secret', 'text');
    }

    private function query(string $domainUuid, string $subcategory, string $name)
    {
        return DomainSettings::query()->where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', 'messaging')
            ->where('domain_setting_subcategory', $subcategory)
            ->where('domain_setting_name', $name);
    }

    private function upsert($query, string $domainUuid, string $subcategory, string $name, string $value, string $description): void
    {
        $setting = $query->first() ?? new DomainSettings([
            'domain_uuid' => $domainUuid,
            'domain_setting_category' => 'messaging',
            'domain_setting_subcategory' => $subcategory,
            'domain_setting_name' => $name,
        ]);
        $setting->domain_setting_value = $value;
        $setting->domain_setting_enabled = 'true';
        $setting->domain_setting_description = $description;
        $setting->save();
    }
}
