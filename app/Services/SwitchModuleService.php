<?php

namespace App\Services;

use App\Models\DefaultSettings;
use App\Models\SwitchModule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SwitchModuleService
{
    private ?array $moduleDefaults = null;

    /**
     * Replace legacy app/modules/app_defaults.php during fresh installation.
     * This prepares startup XML without connecting to or reloading FreeSWITCH.
     */
    public function initializeForInstallation(): void
    {
        $modDir = $this->switchDir('mod');
        $confDir = $this->switchDir('conf');
        if (! $modDir || ! File::isDirectory($modDir)) {
            throw new \RuntimeException('The FreeSWITCH module directory is missing.');
        }
        if (! $confDir || ! File::isDirectory($confDir.'/autoload_configs')) {
            throw new \RuntimeException('The FreeSWITCH configuration directory is missing.');
        }

        DB::transaction(function () {
            foreach (SwitchModule::query()->whereNull('module_order')->get() as $module) {
                $module->update(['module_order' => $this->defaultModuleInfo((string) $module->module_name)['module_order']]);
            }
            $this->syncFromDisk(true);
        });

        if (! $this->writeXml()) {
            throw new \RuntimeException('Unable to write FreeSWITCH modules.conf.xml.');
        }
    }

    public function save(array $data, ?SwitchModule $module = null): array
    {
        $module ??= new SwitchModule();
        $module->fill($data);

        if (! $module->exists) {
            $module->module_uuid = (string) Str::uuid();
            $module->insert_date = now();
            $module->insert_user = session('user_uuid');
        } else {
            $module->update_date = now();
            $module->update_user = session('user_uuid');
        }

        $module->save();

        // The record is saved even if disk or runtime synchronization fails.
        // Return its identity so callers never retry a successful insert as a new record.
        $result = ['item' => $module, 'success' => false];

        try {
            if (! $this->writeXml()) {
                throw new \RuntimeException(__('modules.conf.xml was not writable.'));
            }

            $esl = $this->esl();
            if (! $esl->isConnected()) {
                throw new \RuntimeException(__('FreeSWITCH event socket is unavailable.'));
            }

            $response = $this->formatEslResponse($esl->executeCommand('reloadxml'));
            if (! str_starts_with($response, '+OK')) {
                throw new \RuntimeException($response ?: __('FreeSWITCH XML reload was not confirmed.'));
            }

            $result['success'] = true;
            $result['messages'] = $this->messageBag([
                __('Module saved.'),
                __('modules.conf.xml updated and FreeSWITCH XML reloaded.'),
            ], 'success');
        } catch (\Throwable $exception) {
            $result['messages'] = $this->messageBag([
                __('Module saved, but FreeSWITCH configuration could not be applied.'),
                $exception->getMessage(),
            ], 'error');
        }

        return $result;
    }

    public function syncFromDisk(bool $useInstallDefaults = false): int
    {
        $modDir = $this->switchDir('mod');

        if (! $modDir || ! File::isDirectory($modDir)) {
            return 0;
        }

        $existing = SwitchModule::query()
            ->pluck('module_name')
            ->filter()
            ->flip();

        $rows = collect(File::files($modDir))
            ->map(fn ($file) => $file->getFilename())
            ->filter(fn ($file) => str_ends_with($file, '.so') || str_ends_with($file, '.dll'))
            ->map(fn ($file) => preg_replace('/\.(so|dll)$/', '', $file))
            ->filter(fn ($name) => $name && ! $existing->has($name))
            ->map(fn ($name) => $this->newModuleRow($name, $useInstallDefaults))
            ->values();

        if ($rows->isEmpty()) {
            return 0;
        }

        DB::table('v_modules')->insert($rows->all());

        return $rows->count();
    }

    public function activeModuleNames(?Collection $candidateModuleNames = null): Collection
    {
        $response = $this->esl()->executeCommand('show modules as json');

        $rows = is_array($response) ? ($response['rows'] ?? []) : [];

        $activeNames = collect($rows)
            ->flatMap(fn ($row) => [
                $row['ikey'] ?? null,
                $this->moduleNameFromFilename($row['filename'] ?? null),
            ])
            ->filter()
            ->unique()
            ->values();

        $candidateModuleNames = $candidateModuleNames
            ? $this->sanitizeModuleNames($candidateModuleNames)
            : collect();

        $missingNames = $candidateModuleNames
            ->reject(fn ($name) => $activeNames->contains($name))
            ->values();

        if ($missingNames->isEmpty()) {
            return $activeNames;
        }

        return $activeNames
            ->merge($this->moduleExistsNames($missingNames))
            ->unique()
            ->values();
    }

    public function eventSocketIsAvailable(): bool
    {
        return $this->esl()->isConnected();
    }

    public function control(Collection $modules, string $action): array
    {
        $command = match ($action) {
            'start' => 'load',
            'stop' => 'unload',
            default => throw new \InvalidArgumentException('Unsupported module action.'),
        };

        $esl = $this->esl();

        if (! $esl->isConnected()) {
            return [
                'success' => false,
                'messages' => ['error' => ['FreeSWITCH event socket is unavailable.']],
            ];
        }

        $responses = [];
        $failures = [];
        $controlledModuleNames = collect();

        foreach ($modules as $module) {
            $response = $esl->executeCommand("{$command} {$module->module_name}", false);
            $message = $this->formatEslResponse($response);

            if ($this->eslResponseFailed($response)) {
                $failures[] = "{$module->module_name}: {$this->cleanEslError($message)}";
                continue;
            }

            $responses[] = "{$module->module_name}: {$message}";
            $controlledModuleNames->push($module->module_name);
        }

        $esl->disconnect();
        $settled = $this->waitForRuntimeState($controlledModuleNames, $action);

        if (! empty($failures)) {
            return [
                'success' => false,
                'messages' => $this->messageBag([
                    'FreeSWITCH returned an error.',
                    ...$failures,
                ], 'error'),
            ];
        }

        return [
            'success' => true,
            'messages' => $this->messageBag([
                ucfirst($action) . ' command sent.',
                ...$responses,
                $settled ? 'Runtime status refreshed.' : 'Runtime status may still be updating.',
            ], 'success'),
        ];
    }

    public function toggle(Collection $modules): array
    {
        $activeNames = $this->activeModuleNames($modules->pluck('module_name'));

        DB::transaction(function () use ($modules) {
            foreach ($modules as $module) {
                $module->module_enabled = $module->module_enabled === 'true' ? 'false' : 'true';
                $module->update_date = now();
                $module->update_user = session('user_uuid');
                $module->save();
            }
        });

        $responses = $this->unloadActiveModules($modules, $activeNames);
        $xmlWritten = $this->writeXml();
        $reloadResponse = $xmlWritten ? $this->reloadXml() : null;

        return [
            'success' => true,
            'messages' => $this->messageBag([
                'Module enabled state toggled.',
                $xmlWritten ? 'modules.conf.xml updated.' : 'modules.conf.xml was not writable.',
                $reloadResponse,
                ...$responses,
            ], 'success'),
        ];
    }

    public function delete(Collection $modules): array
    {
        $activeNames = $this->activeModuleNames($modules->pluck('module_name'));
        $responses = $this->unloadActiveModules($modules, $activeNames);

        DB::table('v_modules')
            ->whereIn('module_uuid', $modules->pluck('module_uuid'))
            ->delete();

        $xmlWritten = $this->writeXml();
        $reloadResponse = $xmlWritten ? $this->reloadXml() : null;

        return [
            'success' => true,
            'messages' => $this->messageBag([
                "Deleted {$modules->count()} module(s).",
                $xmlWritten ? 'modules.conf.xml updated.' : 'modules.conf.xml was not writable.',
                $reloadResponse,
                ...$responses,
            ], 'success'),
        ];
    }

    public function writeXml(): bool
    {
        $confDir = $this->switchDir('conf');

        if (! $confDir) {
            return false;
        }

        $path = rtrim($confDir, '/') . '/autoload_configs/modules.conf.xml';

        if (File::exists($path) && ! File::isWritable($path)) {
            return false;
        }

        $modules = SwitchModule::query()
            ->orderBy('module_order')
            ->orderBy('module_category')
            ->get();

        $xml = "<configuration name=\"modules.conf\" description=\"Modules\">\n";
        $xml .= "\t<modules>\n";

        $previousCategory = null;
        foreach ($modules as $module) {
            if ($previousCategory !== $module->module_category) {
                $category = str_replace('--', '', htmlspecialchars((string) $module->module_category, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
                $xml .= "\n\t\t<!-- {$category} -->\n";
            }

            if ($module->module_enabled === 'true') {
                $name = htmlspecialchars((string) $module->module_name, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= "\t\t<load module=\"{$name}\"/>\n";
            }

            $previousCategory = $module->module_category;
        }

        $xml .= "\n\t</modules>\n";
        $xml .= '</configuration>';

        return File::put($path, $xml) !== false;
    }

    private function switchDir(string $subcategory): ?string
    {
        $defaultPath = DefaultSettings::query()
            ->where('default_setting_category', 'switch')
            ->where('default_setting_subcategory', $subcategory)
            ->where('default_setting_name', 'dir')
            ->where('default_setting_enabled', 'true')
            ->value('default_setting_value');

        return filled($defaultPath) ? rtrim((string) $defaultPath, '/') : null;
    }

    private function reloadXml(): string
    {
        $response = $this->esl()->executeCommand('reloadxml');
        $message = $this->formatEslResponse($response);

        return $message !== ''
            ? "FreeSWITCH reloadxml: {$message}"
            : 'FreeSWITCH XML reload was not confirmed.';
    }

    private function unloadActiveModules(Collection $modules, Collection $activeNames): array
    {
        $activeModules = $modules->filter(fn ($module) => $activeNames->contains($module->module_name));

        if ($activeModules->isEmpty()) {
            return [];
        }

        $esl = $this->esl();

        if (! $esl->isConnected()) {
            return ['FreeSWITCH event socket is unavailable; active modules were not unloaded.'];
        }

        $responses = [];

        foreach ($activeModules as $module) {
            $responses[] = "{$module->module_name}: " . $this->formatEslResponse($esl->executeCommand("unload {$module->module_name}", false));
        }

        $esl->disconnect();

        return $responses;
    }

    private function esl(): FreeswitchEslService
    {
        return app(FreeswitchEslService::class);
    }

    private function formatEslResponse(mixed $response): string
    {
        if (is_array($response)) {
            return $response['job_uuid'] ?? json_encode($response);
        }

        return trim((string) $response);
    }

    private function eslResponseFailed(mixed $response): bool
    {
        return str_starts_with(ltrim($this->formatEslResponse($response)), '-ERR');
    }

    private function cleanEslError(string $message): string
    {
        return trim(preg_replace('/^-ERR\s*/', '', $message)) ?: 'FreeSWITCH returned an error.';
    }

    private function messageBag(array $messages, string $keyPrefix): array
    {
        return collect($messages)
            ->filter(fn ($message) => filled($message))
            ->values()
            ->mapWithKeys(fn ($message, $index) => [
                $index === 0 ? $keyPrefix : "{$keyPrefix}_{$index}" => [(string) $message],
            ])
            ->all();
    }

    private function waitForRuntimeState(Collection $moduleNames, string $action): bool
    {
        $moduleNames = $moduleNames->filter()->unique()->values();

        if ($moduleNames->isEmpty()) {
            return true;
        }

        for ($attempt = 0; $attempt < 8; $attempt++) {
            usleep(250000);

            $activeNames = $this->activeModuleNames($moduleNames);
            $matches = $action === 'start'
                ? $moduleNames->every(fn ($name) => $activeNames->contains($name))
                : $moduleNames->every(fn ($name) => ! $activeNames->contains($name));

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    private function newModuleRow(string $name, bool $useInstallDefaults = false): array
    {
        $row = [
            'module_uuid' => (string) Str::uuid(),
            'module_label' => Str::of($name)->after('mod_')->replace('_', ' ')->title()->toString(),
            'module_name' => $name,
            'module_description' => '',
            'module_category' => 'Auto',
            'module_order' => 800,
            'module_enabled' => 'false',
            'module_default_enabled' => 'false',
            'insert_date' => now(),
            'insert_user' => session('user_uuid'),
        ];

        return $useInstallDefaults ? array_replace($row, $this->defaultModuleInfo($name)) : $row;
    }

    private function defaultModuleInfo(string $name): array
    {
        // Ported from the legacy modules::info() catalog. Only fresh setup uses
        // its autoload defaults; normal module discovery still adds disabled rows.
        $this->moduleDefaults ??= json_decode(File::get(resource_path('freeswitch_modules.json')), true, 512, JSON_THROW_ON_ERROR);

        return $this->moduleDefaults[$name] ?? [
            'module_label' => ucwords(str_replace('_', ' ', substr($name, 4))),
            'module_name' => $name,
            'module_order' => 800,
            'module_enabled' => 'false',
            'module_default_enabled' => 'false',
            'module_description' => '',
            'module_category' => 'Auto',
        ];
    }

    private function moduleExistsNames(Collection $moduleNames): Collection
    {
        $esl = $this->esl();

        if (! $esl->isConnected()) {
            return collect();
        }

        try {
            return $moduleNames
                ->filter(function ($name) use ($esl) {
                    $response = $esl->executeCommand("module_exists {$name}", false);

                    return $response === true || strtolower(trim((string) $response)) === 'true';
                })
                ->values();
        } finally {
            $esl->disconnect();
        }
    }

    private function moduleNameFromFilename(?string $filename): ?string
    {
        if (! $filename) {
            return null;
        }

        $basename = basename($filename);

        return preg_match('/^(mod_[A-Za-z0-9_]+)\.(?:so|dll)$/', $basename, $matches)
            ? $matches[1]
            : null;
    }

    private function sanitizeModuleNames(Collection $moduleNames): Collection
    {
        return $moduleNames
            ->filter(fn ($name) => is_string($name) && preg_match('/^[A-Za-z0-9_]+$/', $name))
            ->unique()
            ->values();
    }
}
