<?php

namespace App\Services\Settings;

use App\Models\Domain;
use App\Models\DomainSettings;
use App\Support\Localization\LocaleRegistry;

/**
 * Declarative source of truth for the settings shown on the Account Settings
 * "General" tab. Each field names the domain_settings row it maps to
 * (category/subcategory/name), how it renders, and where its value comes
 * from. Values are this account's own overrides; empty means inherit the
 * system default (see AccountSettingsController::applySettings).
 *
 * Adding an account setting is one entry in fields() -- no per-field wiring
 * across template, mount, and save.
 */
class AccountSettingsSchema extends SettingsSchema
{
    public function fields(): array
    {
        return [
            [
                'key' => 'time_zone',
                'category' => 'domain',
                'subcategory' => 'time_zone',
                'name' => 'name',
                'type' => 'select',
                'label' => 'Time Zone',
                'group' => 'Regional',
                'placeholder' => 'Select Time Zone',
                'options' => 'timezones',
                'grouped' => true,
                'searchable' => true,
                'info' => 'Leave empty to inherit the system default.',
            ],
            [
                'key' => 'language',
                'category' => 'domain',
                'subcategory' => 'language',
                'name' => 'code',
                'type' => 'select',
                'label' => 'Language',
                'group' => 'Regional',
                'placeholder' => 'Select Language',
                'options' => 'locales',
                'grouped' => false,
                'searchable' => true,
                'info' => 'Sets the display language for everyone in this account. '
                    . 'Only languages that are translated enough to use are listed. '
                    . 'Leave empty to inherit the system default.',
            ],
        ];
    }

    /**
     * Resolve the option lists this account sees, keyed by reference name.
     */
    public function options(?Domain $domain = null): array
    {
        $current = (string) (get_domain_setting('language', $domain?->domain_uuid)
            ?: app(LocaleRegistry::class)->default());

        return $this->optionLists($current);
    }

    /**
     * The account's own override value for each schema key, or null when it
     * has none (the field renders empty and inherits the default).
     *
     * @return array<string, ?string>
     */
    public function values(Domain $domain): array
    {
        $rows = DomainSettings::query()
            ->where('domain_uuid', $domain->domain_uuid)
            ->whereIn('domain_setting_subcategory', collect($this->fields())->pluck('subcategory')->all())
            ->get()
            ->keyBy('domain_setting_subcategory');

        $values = [];
        foreach ($this->fields() as $field) {
            $values[$field['key']] = $rows->get($field['subcategory'])?->domain_setting_value;
        }

        return $values;
    }
}
