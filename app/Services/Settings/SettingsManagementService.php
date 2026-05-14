<?php

namespace App\Services\Settings;

use App\Models\DefaultSettings;
use App\Models\Domain;
use App\Models\DomainSettings;
use App\Models\FusionCache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SettingsManagementService
{
    public const TYPE_OPTIONS = [
        'array' => 'Array',
        'boolean' => 'Boolean',
        'code' => 'Code',
        'dir' => 'Dir',
        'name' => 'Name',
        'numeric' => 'Numeric',
        'text' => 'Text',
        'uuid' => 'UUID',
    ];

    public function effectiveDomainSettings(Domain $domain, array $filters = [], ?string $sort = null, int $page = 1, int $perPage = 50): LengthAwarePaginator
    {
        $defaults = DefaultSettings::query()
            ->select([
                'default_setting_uuid',
                'default_setting_category',
                'default_setting_subcategory',
                'default_setting_name',
                'default_setting_value',
                'default_setting_order',
                'default_setting_enabled',
                'default_setting_description',
            ])
            ->get();

        $domainRows = DomainSettings::query()
            ->where('domain_uuid', $domain->domain_uuid)
            ->select([
                'domain_setting_uuid',
                'domain_uuid',
                'domain_setting_category',
                'domain_setting_subcategory',
                'domain_setting_name',
                'domain_setting_value',
                'domain_setting_order',
                'domain_setting_enabled',
                'domain_setting_description',
            ])
            ->get();

        $domainByKey = $domainRows
            ->filter(fn (DomainSettings $row) => strtolower((string) $row->domain_setting_name) !== 'array')
            ->keyBy(fn (DomainSettings $row) => $this->settingKey(
                $row->domain_setting_category,
                $row->domain_setting_subcategory,
                $row->domain_setting_name
            ));

        $matchedDomainUuids = [];

        $rows = $defaults->map(function (DefaultSettings $default) use ($domainByKey, &$matchedDomainUuids) {
            $key = $this->settingKey(
                $default->default_setting_category,
                $default->default_setting_subcategory,
                $default->default_setting_name
            );

            $override = strtolower((string) $default->default_setting_name) === 'array'
                ? null
                : $domainByKey->get($key);

            if ($override) {
                $matchedDomainUuids[] = $override->domain_setting_uuid;
            }

            return $this->serializeEffectiveRow($default, $override);
        });

        $customRows = $domainRows
            ->reject(fn (DomainSettings $row) => in_array($row->domain_setting_uuid, $matchedDomainUuids, true))
            ->map(fn (DomainSettings $row) => $this->serializeEffectiveRow(null, $row));

        return $this->paginateRows(
            $this->filterRows($rows->merge($customRows), $filters),
            $sort,
            $page,
            $perPage
        );
    }

    public function defaultSettings(array $filters = [], ?string $sort = null, int $page = 1, int $perPage = 50): LengthAwarePaginator
    {
        $rows = DefaultSettings::query()
            ->select([
                'default_setting_uuid',
                'default_setting_category',
                'default_setting_subcategory',
                'default_setting_name',
                'default_setting_value',
                'default_setting_order',
                'default_setting_enabled',
                'default_setting_description',
            ])
            ->get()
            ->map(fn (DefaultSettings $row) => $this->serializeDefaultRow($row));

        return $this->paginateRows(
            $this->filterRows($rows, $filters),
            $sort,
            $page,
            $perPage
        );
    }

    public function defaultItem(?string $uuid = null): array
    {
        $setting = $uuid ? DefaultSettings::query()->findOrFail($uuid) : new DefaultSettings();

        return [
            'default_setting_uuid' => $setting->default_setting_uuid,
            'default_setting_category' => $setting->default_setting_category,
            'default_setting_subcategory' => $setting->default_setting_subcategory,
            'default_setting_name' => $setting->default_setting_name ?: 'text',
            'default_setting_value' => $setting->default_setting_value,
            'default_setting_order' => $setting->default_setting_order ?: 100,
            'default_setting_enabled' => $setting->exists ? $this->boolValue($setting->default_setting_enabled) : true,
            'default_setting_description' => $setting->default_setting_description,
        ];
    }

    public function domainItem(Domain $domain, array $input): array
    {
        $domainUuid = $input['domain_setting_uuid'] ?? null;
        $defaultUuid = $input['default_setting_uuid'] ?? null;

        if ($domainUuid) {
            $setting = DomainSettings::query()
                ->where('domain_uuid', $domain->domain_uuid)
                ->findOrFail($domainUuid);

            return [
                'domain_setting_uuid' => $setting->domain_setting_uuid,
                'default_setting_uuid' => $defaultUuid,
                'domain_setting_category' => $setting->domain_setting_category,
                'domain_setting_subcategory' => $setting->domain_setting_subcategory,
                'domain_setting_name' => $setting->domain_setting_name,
                'domain_setting_value' => $setting->domain_setting_value,
                'domain_setting_order' => $setting->domain_setting_order ?: 100,
                'domain_setting_enabled' => $this->boolValue($setting->domain_setting_enabled),
                'domain_setting_description' => $setting->domain_setting_description,
                'default_value' => $input['default_value'] ?? null,
                'is_custom' => ! $defaultUuid,
            ];
        }

        if ($defaultUuid) {
            $default = DefaultSettings::query()->findOrFail($defaultUuid);

            return [
                'domain_setting_uuid' => null,
                'default_setting_uuid' => $default->default_setting_uuid,
                'domain_setting_category' => $default->default_setting_category,
                'domain_setting_subcategory' => $default->default_setting_subcategory,
                'domain_setting_name' => $default->default_setting_name,
                'domain_setting_value' => $default->default_setting_value,
                'domain_setting_order' => $default->default_setting_order ?: 100,
                'domain_setting_enabled' => $this->boolValue($default->default_setting_enabled),
                'domain_setting_description' => $default->default_setting_description,
                'default_value' => $default->default_setting_value,
                'is_custom' => false,
            ];
        }

        return [
            'domain_setting_uuid' => null,
            'default_setting_uuid' => null,
            'domain_setting_category' => '',
            'domain_setting_subcategory' => '',
            'domain_setting_name' => 'text',
            'domain_setting_value' => '',
            'domain_setting_order' => 100,
            'domain_setting_enabled' => true,
            'domain_setting_description' => '',
            'default_value' => null,
            'is_custom' => true,
        ];
    }

    public function saveDefault(array $data, ?DefaultSettings $setting = null): DefaultSettings
    {
        return DB::transaction(function () use ($data, $setting) {
            $setting ??= new DefaultSettings();
            $setting->forceFill([
                'default_setting_uuid' => $setting->default_setting_uuid ?: Str::uuid()->toString(),
                'default_setting_category' => strtolower($data['default_setting_category']),
                'default_setting_subcategory' => strtolower($data['default_setting_subcategory']),
                'default_setting_name' => strtolower($data['default_setting_name']),
                'default_setting_value' => $data['default_setting_value'] ?? null,
                'default_setting_order' => $data['default_setting_order'] ?? null,
                'default_setting_enabled' => (bool) $data['default_setting_enabled'],
                'default_setting_description' => $data['default_setting_description'] ?? null,
            ])->save();

            $this->applySettingSideEffects('default', [
                'category' => $setting->default_setting_category,
                'subcategory' => $setting->default_setting_subcategory,
                'name' => $setting->default_setting_name,
                'value' => $setting->default_setting_value,
            ]);

            return $setting;
        });
    }

    public function saveDomainOverride(Domain $domain, array $data, ?DomainSettings $setting = null): DomainSettings
    {
        return DB::transaction(function () use ($domain, $data, $setting) {
            $setting ??= new DomainSettings();
            $setting->forceFill([
                'domain_setting_uuid' => $setting->domain_setting_uuid ?: Str::uuid()->toString(),
                'domain_uuid' => $domain->domain_uuid,
                'domain_setting_category' => strtolower($data['domain_setting_category']),
                'domain_setting_subcategory' => strtolower($data['domain_setting_subcategory']),
                'domain_setting_name' => strtolower($data['domain_setting_name']),
                'domain_setting_value' => $data['domain_setting_value'] ?? null,
                'domain_setting_order' => $data['domain_setting_order'] ?? null,
                'domain_setting_enabled' => (bool) $data['domain_setting_enabled'],
                'domain_setting_description' => $data['domain_setting_description'] ?? null,
            ])->save();

            $this->applySettingSideEffects('domain', [
                'domain_uuid' => $domain->domain_uuid,
                'domain_name' => $domain->domain_name,
                'category' => $setting->domain_setting_category,
                'subcategory' => $setting->domain_setting_subcategory,
                'name' => $setting->domain_setting_name,
                'value' => $setting->domain_setting_value,
            ]);

            return $setting;
        });
    }

    public function toggleDefault(array $uuids): int
    {
        $settings = DefaultSettings::query()->whereIn('default_setting_uuid', $uuids)->get();
        $settings->each(function (DefaultSettings $setting) {
            $setting->default_setting_enabled = ! $this->boolValue($setting->default_setting_enabled);
            $setting->save();
        });

        return $settings->count();
    }

    public function toggleDomain(Domain $domain, array $uuids): int
    {
        $settings = DomainSettings::query()
            ->where('domain_uuid', $domain->domain_uuid)
            ->whereIn('domain_setting_uuid', $uuids)
            ->get();

        $settings->each(function (DomainSettings $setting) {
            $setting->domain_setting_enabled = ! $this->boolValue($setting->domain_setting_enabled);
            $setting->save();
        });

        return $settings->count();
    }

    public function deleteDefaults(array $uuids): int
    {
        return DefaultSettings::query()->whereIn('default_setting_uuid', $uuids)->delete();
    }

    public function revertDomain(Domain $domain, array $uuids): int
    {
        return DomainSettings::query()
            ->where('domain_uuid', $domain->domain_uuid)
            ->whereIn('domain_setting_uuid', $uuids)
            ->delete();
    }

    public function copyDefaultsToDomain(array $defaultUuids, Domain $targetDomain): int
    {
        $copied = 0;

        foreach (DefaultSettings::query()->whereIn('default_setting_uuid', $defaultUuids)->get() as $default) {
            $value = $default->default_setting_subcategory === 'http_auth_password'
                ? generate_password()
                : $default->default_setting_value;

            $target = null;
            if (strtolower((string) $default->default_setting_name) !== 'array') {
                $target = DomainSettings::query()
                    ->where('domain_uuid', $targetDomain->domain_uuid)
                    ->where('domain_setting_category', $default->default_setting_category)
                    ->where('domain_setting_subcategory', $default->default_setting_subcategory)
                    ->where('domain_setting_name', $default->default_setting_name)
                    ->first();
            }

            $this->saveDomainOverride($targetDomain, [
                'domain_setting_category' => $default->default_setting_category,
                'domain_setting_subcategory' => $default->default_setting_subcategory,
                'domain_setting_name' => $default->default_setting_name,
                'domain_setting_value' => $value,
                'domain_setting_order' => $default->default_setting_order,
                'domain_setting_enabled' => $this->boolValue($default->default_setting_enabled),
                'domain_setting_description' => $default->default_setting_description,
            ], $target);

            $copied++;
        }

        return $copied;
    }

    public function copyDomainSettings(Domain $sourceDomain, array $domainSettingUuids, string $target): int
    {
        $rows = DomainSettings::query()
            ->where('domain_uuid', $sourceDomain->domain_uuid)
            ->whereIn('domain_setting_uuid', $domainSettingUuids)
            ->get();

        if ($target === 'default') {
            return $this->copyDomainSettingsToDefaults($rows);
        }

        $targetDomain = Domain::query()->findOrFail($target);
        $copied = 0;

        foreach ($rows as $row) {
            $value = $row->domain_setting_subcategory === 'http_auth_password'
                ? generate_password()
                : $row->domain_setting_value;

            $targetRow = null;
            if ($targetDomain->domain_uuid !== $sourceDomain->domain_uuid && strtolower((string) $row->domain_setting_name) !== 'array') {
                $targetRow = DomainSettings::query()
                    ->where('domain_uuid', $targetDomain->domain_uuid)
                    ->where('domain_setting_category', $row->domain_setting_category)
                    ->where('domain_setting_subcategory', $row->domain_setting_subcategory)
                    ->where('domain_setting_name', $row->domain_setting_name)
                    ->first();
            }

            $this->saveDomainOverride($targetDomain, [
                'domain_setting_category' => $row->domain_setting_category,
                'domain_setting_subcategory' => $row->domain_setting_subcategory,
                'domain_setting_name' => $row->domain_setting_name,
                'domain_setting_value' => $value,
                'domain_setting_order' => $row->domain_setting_order,
                'domain_setting_enabled' => $this->boolValue($row->domain_setting_enabled),
                'domain_setting_description' => $row->domain_setting_description,
            ], $targetRow);

            $copied++;
        }

        return $copied;
    }

    public function categories(?Domain $domain = null): array
    {
        $defaultCategories = DefaultSettings::query()
            ->select('default_setting_category as category')
            ->distinct()
            ->pluck('category');

        $domainCategories = $domain
            ? DomainSettings::query()
                ->where('domain_uuid', $domain->domain_uuid)
                ->select('domain_setting_category as category')
                ->distinct()
                ->pluck('category')
            : collect();

        return $defaultCategories
            ->merge($domainCategories)
            ->filter()
            ->unique()
            ->sort()
            ->map(fn ($category) => [
                'value' => $category,
                'label' => $this->formatCategory((string) $category),
            ])
            ->values()
            ->all();
    }

    public function affectedDomains(DefaultSettings $setting): array
    {
        if (strtolower((string) $setting->default_setting_name) === 'array') {
            return [];
        }

        return DomainSettings::query()
            ->join('v_domains', 'v_domains.domain_uuid', '=', 'v_domain_settings.domain_uuid')
            ->where('domain_setting_category', $setting->default_setting_category)
            ->where('domain_setting_subcategory', $setting->default_setting_subcategory)
            ->where('domain_setting_name', $setting->default_setting_name)
            ->orderBy('v_domains.domain_name')
            ->get([
                'v_domains.domain_uuid',
                'v_domains.domain_name',
                'v_domains.domain_description',
                'v_domain_settings.domain_setting_uuid',
                'v_domain_settings.domain_setting_value',
                'v_domain_settings.domain_setting_enabled',
            ])
            ->map(fn ($row) => [
                'domain_uuid' => $row->domain_uuid,
                'domain_name' => $row->domain_name,
                'domain_description' => $row->domain_description,
                'domain_setting_uuid' => $row->domain_setting_uuid,
                'value' => $row->domain_setting_value,
                'enabled' => $this->boolValue($row->domain_setting_enabled),
            ])
            ->all();
    }

    private function copyDomainSettingsToDefaults(Collection $rows): int
    {
        $copied = 0;

        foreach ($rows as $row) {
            $value = $row->domain_setting_subcategory === 'http_auth_password'
                ? generate_password()
                : $row->domain_setting_value;

            $target = null;
            if (strtolower((string) $row->domain_setting_name) !== 'array') {
                $target = DefaultSettings::query()
                    ->where('default_setting_category', $row->domain_setting_category)
                    ->where('default_setting_subcategory', $row->domain_setting_subcategory)
                    ->where('default_setting_name', $row->domain_setting_name)
                    ->first();
            }

            $this->saveDefault([
                'default_setting_category' => $row->domain_setting_category,
                'default_setting_subcategory' => $row->domain_setting_subcategory,
                'default_setting_name' => $row->domain_setting_name,
                'default_setting_value' => $value,
                'default_setting_order' => $row->domain_setting_order,
                'default_setting_enabled' => $this->boolValue($row->domain_setting_enabled),
                'default_setting_description' => $row->domain_setting_description,
            ], $target);

            $copied++;
        }

        return $copied;
    }

    private function serializeEffectiveRow(?DefaultSettings $default, ?DomainSettings $override): array
    {
        $category = $override?->domain_setting_category ?? $default?->default_setting_category;
        $subcategory = $override?->domain_setting_subcategory ?? $default?->default_setting_subcategory;
        $name = $override?->domain_setting_name ?? $default?->default_setting_name;

        return [
            'id' => $default ? 'default:' . $default->default_setting_uuid : 'domain:' . $override->domain_setting_uuid,
            'default_setting_uuid' => $default?->default_setting_uuid,
            'domain_setting_uuid' => $override?->domain_setting_uuid,
            'category' => $category,
            'category_label' => $this->formatCategory((string) $category),
            'subcategory' => $subcategory,
            'type' => $name,
            'type_label' => self::TYPE_OPTIONS[strtolower((string) $name)] ?? ucfirst((string) $name),
            'default_value' => $default?->default_setting_value,
            'override_value' => $override?->domain_setting_value,
            'effective_value' => $override ? $override->domain_setting_value : $default?->default_setting_value,
            'default_enabled' => $default ? $this->boolValue($default->default_setting_enabled) : null,
            'override_enabled' => $override ? $this->boolValue($override->domain_setting_enabled) : null,
            'enabled' => $override ? $this->boolValue($override->domain_setting_enabled) : $this->boolValue($default?->default_setting_enabled),
            'source' => $override ? ($default ? 'override' : 'custom') : 'default',
            'source_label' => $override ? ($default ? 'Domain Override' : 'Domain Only') : 'Default',
            'description' => $override?->domain_setting_description ?? $default?->default_setting_description,
            'order' => $override?->domain_setting_order ?? $default?->default_setting_order,
            'is_secret' => $this->isSecret((string) $subcategory),
        ];
    }

    private function serializeDefaultRow(DefaultSettings $row): array
    {
        $overrideCount = strtolower((string) $row->default_setting_name) === 'array'
            ? 0
            : DomainSettings::query()
                ->where('domain_setting_category', $row->default_setting_category)
                ->where('domain_setting_subcategory', $row->default_setting_subcategory)
                ->where('domain_setting_name', $row->default_setting_name)
                ->count();

        return [
            'id' => $row->default_setting_uuid,
            'default_setting_uuid' => $row->default_setting_uuid,
            'category' => $row->default_setting_category,
            'category_label' => $this->formatCategory((string) $row->default_setting_category),
            'subcategory' => $row->default_setting_subcategory,
            'type' => $row->default_setting_name,
            'type_label' => self::TYPE_OPTIONS[strtolower((string) $row->default_setting_name)] ?? ucfirst((string) $row->default_setting_name),
            'value' => $row->default_setting_value,
            'enabled' => $this->boolValue($row->default_setting_enabled),
            'description' => $row->default_setting_description,
            'order' => $row->default_setting_order,
            'override_count' => $overrideCount,
            'is_secret' => $this->isSecret((string) $row->default_setting_subcategory),
        ];
    }

    private function filterRows(Collection $rows, array $filters): Collection
    {
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $category = (string) ($filters['category'] ?? '');
        $source = (string) ($filters['source'] ?? 'all');
        $enabled = (string) ($filters['enabled'] ?? 'all');

        return $rows
            ->when($search !== '', fn (Collection $items) => $items->filter(function (array $row) use ($search) {
                return str_contains(strtolower(implode(' ', array_filter([
                    $row['category'] ?? '',
                    $row['subcategory'] ?? '',
                    $row['type'] ?? '',
                    $row['effective_value'] ?? $row['value'] ?? '',
                    $row['description'] ?? '',
                ], fn ($value) => is_scalar($value)))), $search);
            }))
            ->when($category !== '', fn (Collection $items) => $items->where('category', $category))
            ->when($source !== '' && $source !== 'all', function (Collection $items) use ($source) {
                return $source === 'overrides'
                    ? $items->filter(fn (array $row) => in_array($row['source'] ?? '', ['override', 'custom'], true))
                    : $items->where('source', $source);
            })
            ->when($enabled !== '' && $enabled !== 'all', fn (Collection $items) => $items->filter(
                fn (array $row) => (bool) ($row['enabled'] ?? false) === ($enabled === 'true')
            ))
            ->values();
    }

    private function paginateRows(Collection $rows, ?string $sort, int $page, int $perPage): LengthAwarePaginator
    {
        $sortField = $sort ?: 'category';
        $descending = str_starts_with($sortField, '-');
        $sortField = ltrim($sortField, '-');

        $sorted = $rows->sortBy([
            [$sortField, $descending ? 'desc' : 'asc'],
            ['subcategory', 'asc'],
            ['order', 'asc'],
        ])->values();

        return new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    private function settingKey(?string $category, ?string $subcategory, ?string $name): string
    {
        return strtolower((string) $category) . '|' . strtolower((string) $subcategory) . '|' . strtolower((string) $name);
    }

    private function boolValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function formatCategory(string $category): string
    {
        return match (strtolower($category)) {
            'api' => 'API',
            'cdr' => 'CDR',
            'ldap' => 'LDAP',
            'ivr_menu' => 'IVR Menu',
            default => Str::of($category)->replace(['_', '-'], ' ')->title()->toString(),
        };
    }

    private function isSecret(string $subcategory): bool
    {
        return $subcategory === 'password'
            || str_contains($subcategory, '_password')
            || str_contains($subcategory, '_secret');
    }

    private function applySettingSideEffects(string $scope, array $setting): void
    {
        if ($setting['category'] === 'destinations' && $setting['subcategory'] === 'dialplan_mode') {
            FusionCache::clear('dialplan:mode');
        }

        if ($setting['category'] === 'domain' && $setting['subcategory'] === 'time_zone' && $setting['name'] === 'name') {
            if ($scope === 'domain' && ! empty($setting['domain_name'])) {
                FusionCache::clear('dialplan:' . $setting['domain_name']);
            }

            if ($scope === 'default') {
                FusionCache::clear('dialplan:public');
            }
        }
    }
}
