<?php

namespace App\Services;

use App\Models\DefaultSettings;
use App\Models\SwitchVariable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SwitchVariableService
{
    public const COMMAND_OPTIONS = [
        'set' => 'Set',
        'exec-set' => 'Exec Set',
    ];

    public function variables(array $filters = [], ?string $sort = null, int $page = 1, int $perPage = 50): LengthAwarePaginator
    {
        $rows = SwitchVariable::query()
            ->select([
                'var_uuid',
                'var_category',
                'var_name',
                'var_value',
                'var_command',
                'var_hostname',
                'var_enabled',
                'var_order',
                'var_description',
            ])
            ->get()
            ->map(fn (SwitchVariable $variable) => $this->serializeVariable($variable));

        return $this->paginateRows(
            $this->filterRows($rows, $filters),
            $sort,
            $page,
            $perPage
        );
    }

    public function variableItem(?string $uuid = null): array
    {
        $variable = $uuid ? SwitchVariable::query()->findOrFail($uuid) : new SwitchVariable();

        return [
            'var_uuid' => $variable->var_uuid,
            'var_category' => $variable->var_category,
            'var_name' => $variable->var_name,
            'var_value' => $variable->var_value,
            'var_command' => $variable->var_command ?: 'set',
            'var_hostname' => $variable->var_hostname,
            'var_enabled' => $variable->exists ? $this->boolValue($variable->var_enabled) : true,
            'var_order' => $variable->exists ? $variable->var_order : null,
            'var_description' => $variable->var_description,
        ];
    }

    public function saveVariable(array $data, ?SwitchVariable $variable = null): SwitchVariable
    {
        return DB::transaction(function () use ($data, $variable) {
            $variable ??= new SwitchVariable();
            $variable->forceFill([
                'var_uuid' => $variable->var_uuid ?: Str::uuid()->toString(),
                'var_category' => $data['var_category'],
                'var_name' => $data['var_name'],
                'var_value' => $data['var_value'] ?? null,
                'var_command' => ($data['var_command'] ?? null) ?: 'set',
                'var_hostname' => filled($data['var_hostname'] ?? null) ? $data['var_hostname'] : null,
                'var_enabled' => (bool) $data['var_enabled'] ? 'true' : 'false',
                'var_order' => filled($data['var_order'] ?? null) ? $data['var_order'] : null,
                'var_description' => $data['var_description'] ?? null,
            ])->save();

            $this->syncVarsXml();

            return $variable;
        });
    }

    public function toggle(array $uuids): int
    {
        return DB::transaction(function () use ($uuids) {
            $variables = SwitchVariable::query()->whereIn('var_uuid', $uuids)->get();

            $variables->each(function (SwitchVariable $variable) {
                $variable->var_enabled = $this->boolValue($variable->var_enabled) ? 'false' : 'true';
                $variable->save();
            });

            $this->syncVarsXml();

            return $variables->count();
        });
    }

    public function copy(array $uuids): int
    {
        return DB::transaction(function () use ($uuids) {
            $copied = 0;

            foreach (SwitchVariable::query()->whereIn('var_uuid', $uuids)->get() as $variable) {
                $copy = $variable->replicate();
                $copy->var_uuid = Str::uuid()->toString();
                $copy->var_description = trim(trim((string) $variable->var_description) . ' (copy)');
                $copy->save();
                $copied++;
            }

            if ($copied > 0) {
                $this->syncVarsXml();
            }

            return $copied;
        });
    }

    public function delete(array $uuids): int
    {
        return DB::transaction(function () use ($uuids) {
            $deleted = SwitchVariable::query()->whereIn('var_uuid', $uuids)->delete();

            if ($deleted > 0) {
                $this->syncVarsXml();
            }

            return $deleted;
        });
    }

    public function categories(): array
    {
        return SwitchVariable::query()
            ->select('var_category')
            ->distinct()
            ->pluck('var_category')
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

    public function syncVarsXml(bool $markReloadRequired = true): bool
    {
        if ($markReloadRequired) {
            session()->forget('user_defined_variables');
        }

        $confDir = $this->switchConfDir();
        if (! $confDir) {
            return false;
        }

        File::ensureDirectoryExists($confDir);

        $hostname = $this->switchHostname();
        $xml = '';
        $previousCategory = null;

        SwitchVariable::query()
            ->where('var_enabled', 'true')
            ->orderBy('var_category')
            ->orderBy('var_order')
            ->get()
            ->each(function (SwitchVariable $variable) use (&$xml, &$previousCategory, $hostname) {
                if ($variable->var_category === 'Provision') {
                    $previousCategory = $variable->var_category;
                    return;
                }

                if ($previousCategory !== $variable->var_category) {
                    $xml .= "\n<!-- " . $this->xml($variable->var_category) . " -->\n";
                }

                $command = $variable->var_command ?: 'set';
                if ($variable->var_category === 'Exec-Set') {
                    $command = 'exec-set';
                }

                if (! filled($variable->var_hostname) || $variable->var_hostname === $hostname) {
                    $data = $variable->var_name . '=' . $variable->var_value;
                    $xml .= "<X-PRE-PROCESS cmd=\"" . $this->xml($command) . "\" data=\"" . $this->xml($data) . "\" />\n";
                }

                $previousCategory = $variable->var_category;
            });

        File::put(rtrim($confDir, '/') . '/vars.xml', $xml . "\n");

        if ($markReloadRequired) {
            session(['reload_xml' => true]);
        }

        return true;
    }

    private function serializeVariable(SwitchVariable $variable): array
    {
        return [
            'id' => $variable->var_uuid,
            'var_uuid' => $variable->var_uuid,
            'category' => $variable->var_category,
            'category_label' => $this->formatCategory((string) $variable->var_category),
            'name' => $variable->var_name,
            'value' => $variable->var_value,
            'command' => $variable->var_command ?: 'set',
            'command_label' => self::COMMAND_OPTIONS[$variable->var_command ?: 'set'] ?? ucfirst((string) $variable->var_command),
            'hostname' => $variable->var_hostname,
            'enabled' => $this->boolValue($variable->var_enabled),
            'description' => $variable->var_description,
            'order' => $variable->var_order,
            'is_secret' => $this->isSecret((string) $variable->var_name),
        ];
    }

    private function filterRows(Collection $rows, array $filters): Collection
    {
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $category = (string) ($filters['category'] ?? '');
        $enabled = (string) ($filters['enabled'] ?? 'all');

        return $rows
            ->when($search !== '', fn (Collection $items) => $items->filter(function (array $row) use ($search) {
                return str_contains(strtolower(implode(' ', array_filter([
                    $row['category'] ?? '',
                    $row['name'] ?? '',
                    $row['value'] ?? '',
                    $row['command'] ?? '',
                    $row['hostname'] ?? '',
                    $row['description'] ?? '',
                ], fn ($value) => $value !== null && $value !== ''))), $search);
            }))
            ->when($category !== '', fn (Collection $items) => $items->where('category', $category))
            ->when(in_array($enabled, ['true', 'false'], true), function (Collection $items) use ($enabled) {
                $wantEnabled = $enabled === 'true';
                return $items->filter(fn (array $row) => (bool) $row['enabled'] === $wantEnabled);
            })
            ->values();
    }

    private function paginateRows(Collection $rows, ?string $sort, int $page, int $perPage): LengthAwarePaginator
    {
        $rows = $this->sortRows($rows, $sort)->values();

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    private function sortRows(Collection $rows, ?string $sort): Collection
    {
        $sort = $sort ?: 'category,order,name';
        $fields = array_filter(explode(',', $sort));

        return $rows->sort(function (array $a, array $b) use ($fields) {
            foreach ($fields as $field) {
                $descending = str_starts_with($field, '-');
                $key = ltrim($field, '-');
                $result = strnatcasecmp((string) ($a[$key] ?? ''), (string) ($b[$key] ?? ''));
                if ($result !== 0) {
                    return $descending ? -$result : $result;
                }
            }

            return 0;
        });
    }

    private function switchConfDir(): ?string
    {
        $path = DefaultSettings::query()
            ->where('default_setting_category', 'switch')
            ->where('default_setting_subcategory', 'conf')
            ->where('default_setting_name', 'dir')
            ->where('default_setting_enabled', 'true')
            ->value('default_setting_value');

        return filled($path) ? rtrim((string) $path, '/') : null;
    }

    private function switchHostname(): string
    {
        try {
            $esl = app(FreeswitchEslService::class);
            if ($esl->isConnected()) {
                $hostname = trim((string) $esl->executeCommand('switchname'));
                if ($hostname !== '') {
                    return $hostname;
                }
            }
        } catch (\Throwable $exception) {
            logger('SwitchVariableService switchname lookup failed: ' . $exception->getMessage());
        }

        return trim((string) gethostname());
    }

    private function formatCategory(string $category): string
    {
        if ($category === '') {
            return 'Uncategorized';
        }

        return Str::of($category)->replace(['_', '-'], ' ')->title()->toString();
    }

    private function boolValue(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'yes'], true);
    }

    private function isSecret(string $name): bool
    {
        return str_contains(strtolower($name), 'password') || str_contains(strtolower($name), 'secret');
    }

    private function xml(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
