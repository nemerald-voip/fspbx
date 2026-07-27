<?php

namespace App\Services\Settings;

use App\Models\DefaultSettings;
use App\Support\Localization\LocaleRegistry;

/**
 * Declarative source of truth for the settings shown on the System Settings
 * "General" tab. Same fields as the account surface, but these are the global
 * default_settings values -- the base every account inherits unless it sets
 * its own override. A default is the root of the chain, so there is no
 * "empty = inherit": the System tab edits the value in place
 * (SystemSettingsController::applyDefaults).
 */
class SystemSettingsSchema extends SettingsSchema
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
                'info' => 'The default time zone for accounts that have not set their own.',
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
                'info' => 'The default display language for accounts that have not set their own. '
                    . 'Only languages that are translated enough to use are listed.',
            ],
        ];
    }

    /**
     * Resolve the option lists, using the current default language so a
     * below-threshold default still displays in the picker.
     */
    public function options(): array
    {
        $current = (string) ($this->defaultRows()->get('language')?->default_setting_value
            ?: app(LocaleRegistry::class)->default());

        return $this->optionLists($current);
    }

    /**
     * The current global default value for each schema key.
     *
     * @return array<string, ?string>
     */
    public function values(): array
    {
        $rows = $this->defaultRows();

        $values = [];
        foreach ($this->fields() as $field) {
            $values[$field['key']] = $rows->get($field['subcategory'])?->default_setting_value;
        }

        return $values;
    }

    /**
     * @return \Illuminate\Support\Collection<string, DefaultSettings>
     */
    private function defaultRows()
    {
        return DefaultSettings::query()
            ->whereIn('default_setting_subcategory', collect($this->fields())->pluck('subcategory')->all())
            ->get()
            ->keyBy('default_setting_subcategory');
    }
}
