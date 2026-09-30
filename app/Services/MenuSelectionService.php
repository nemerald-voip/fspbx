<?php

namespace App\Services;

use App\Models\DefaultSettings;
use App\Models\DomainSettings;
use App\Models\Menu;

class MenuSelectionService
{
    public function forAccount(string $domainUuid): ?Menu
    {
        $uuid = DomainSettings::query()
            ->where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', 'domain')
            ->where('domain_setting_subcategory', 'menu')
            ->where('domain_setting_name', 'uuid')
            ->where('domain_setting_enabled', true)
            ->value('domain_setting_value');

        return ($uuid ? Menu::query()->find($uuid) : null) ?? $this->systemDefault();
    }

    public function systemDefault(): ?Menu
    {
        $uuid = DefaultSettings::query()
            ->where('default_setting_category', 'domain')
            ->where('default_setting_subcategory', 'menu')
            ->where('default_setting_name', 'uuid')
            ->value('default_setting_value');

        return ($uuid ? Menu::query()->find($uuid) : null)
            ?? Menu::query()->where('menu_name', 'fspbx')->first();
    }
}
