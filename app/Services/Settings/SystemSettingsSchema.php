<?php

namespace App\Services\Settings;

use App\Models\DefaultSettings;
use App\Services\MenuSelectionService;
use App\Support\Localization\LocaleRegistry;

/**
 * Declarative source of truth for the settings shown on the System Settings
 * "General" tab. These are the global
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
                'label' => __('Time Zone'),
                'group' => __('Regional'),
                'placeholder' => __('Select Time Zone'),
                'options' => 'timezones',
                'grouped' => true,
                'searchable' => true,
                'info' => __('The default time zone for accounts that have not set their own.'),
            ],
            [
                'key' => 'language',
                'category' => 'domain',
                'subcategory' => 'language',
                'name' => 'code',
                'type' => 'select',
                'label' => __('Language'),
                'group' => __('Regional'),
                'placeholder' => __('Select Language'),
                'options' => 'locales',
                'grouped' => false,
                'searchable' => true,
                'info' => __('The default display language for accounts that have not set their own. Only languages that are translated enough to use are listed.'),
            ],
            [
                'key' => 'menu',
                'category' => 'domain',
                'subcategory' => 'menu',
                'name' => 'uuid',
                'type' => 'select',
                'label' => __('Default Menu'),
                'group' => __('Navigation'),
                'placeholder' => __('Select a menu'),
                'options' => 'menus',
                'grouped' => false,
                'searchable' => true,
                'info' => __('Used by accounts without their own menu override.'),
                'description' => __('Sign out and back in to apply menu changes.'),
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
            $values[$field['key']] = $rows->get($field['key'])?->default_setting_value;
        }
        $values['menu'] = app(MenuSelectionService::class)->systemDefault()?->menu_uuid;

        return $values;
    }

    /**
     * @return \Illuminate\Support\Collection<string, DefaultSettings>
     */
    private function defaultRows()
    {
        return collect($this->fields())->mapWithKeys(fn (array $field) => [
            $field['key'] => DefaultSettings::query()
                ->where('default_setting_category', $field['category'])
                ->where('default_setting_subcategory', $field['subcategory'])
                ->where('default_setting_name', $field['name'])
                ->first(),
        ]);
    }
}
