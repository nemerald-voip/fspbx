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
    public function syncFromDisk(): int
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
            ->map(fn ($name) => $this->newModuleRow($name))
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
            if ($module->module_enabled !== 'true') {
                $responses[] = "{$module->module_name}: disabled";
                continue;
            }

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
                $xml .= "\n\t\t<!-- {$module->module_category} -->\n";
            }

            if ($module->module_enabled === 'true') {
                $xml .= "\t\t<load module=\"{$module->module_name}\"/>\n";
            }

            $previousCategory = $module->module_category;
        }

        $xml .= "\n\t</modules>\n";
        $xml .= '</configuration>';

        File::put($path, $xml);
        session(['reload_xml' => true]);

        return true;
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

    private function newModuleRow(string $name): array
    {
        return [
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

                    return strtolower(trim((string) $response)) === 'true';
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
