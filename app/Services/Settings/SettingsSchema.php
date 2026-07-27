<?php

namespace App\Services\Settings;

use App\Support\Localization\LocaleRegistry;

/**
 * Shared behaviour for the declarative settings surfaces (Account = domain
 * overrides, System = global defaults). Subclasses declare their own field
 * list and where current values come from / go to; everything scope-neutral
 * -- the option lists a field references and the valid-value sets used for
 * validation -- lives here so the two surfaces stay consistent.
 */
abstract class SettingsSchema
{
    /**
     * The declarative field list for this surface.
     *
     * @return array<int, array{
     *   key: string, category: string, subcategory: string, name: string,
     *   type: string, label: string, group: string, placeholder: string,
     *   options: ?string, grouped: bool, searchable: bool, info: ?string
     * }>
     */
    abstract public function fields(): array;

    /**
     * Valid values per field key for fields backed by a fixed list, so an
     * update endpoint can reject anything the UI wouldn't offer. Keys absent
     * here (e.g. time_zone) are open and validated only as strings. Language
     * accepts any registered locale -- not just the UI-ready subset the
     * picker shows -- so re-saving a value already on a below-threshold
     * locale still validates.
     *
     * @return array<string, array<int, string>>
     */
    public function allowedValues(): array
    {
        return [
            'language' => array_keys(app(LocaleRegistry::class)->locales()),
        ];
    }

    /**
     * Resolve the option lists referenced by fields()['options'], keyed by
     * that reference name. $currentLanguage is the value already in effect
     * for this surface so a below-threshold locale still appears.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function optionLists(string $currentLanguage): array
    {
        return [
            'timezones' => getGroupedTimezones(),
            'locales' => $this->localeOptions($currentLanguage),
        ];
    }

    /**
     * UI-ready locales plus $current (so an already-selected below-threshold
     * locale still displays), labeled by native name.
     *
     * @return array<int, array{value: string, label: string}>
     */
    protected function localeOptions(string $current): array
    {
        $registry = app(LocaleRegistry::class);
        $current = strtolower($current);

        return collect($registry->available())
            ->filter(fn (array $locale) => $locale['ready'] || $locale['code'] === $current)
            ->map(fn (array $locale) => [
                'value' => $locale['code'],
                'label' => $locale['native'],
            ])
            ->values()
            ->all();
    }
}
