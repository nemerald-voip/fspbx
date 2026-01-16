<?php

namespace App\Services;

use App\Models\ProFeatures;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Nwidart\Modules\Facades\Module;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class ProFeaturesService
{
    public function __construct(
        protected KeygenAPIService $keygenApiService
    ) {}

    /**
     * Refresh only enabled modules (for app:update).
     */
    public function refreshModules(): array
    {
        $enabled = collect(Module::allEnabled())->map(fn($m) => $m->getName())->values();

        return $this->syncModules(
            mode: 'enabled_only',
            enabledModules: $enabled
        );
    }

    /**
     * Install/refresh all entitled modules (for UI "Install" action).
     * If $licenseOverride is provided (e.g. just saved from request), use it.
     */
    public function installModules(?string $licenseOverride = null): array
    {
        return $this->syncModules(
            mode: 'all_entitled',
            enabledModules: null,
            licenseOverride: $licenseOverride
        );
    }

    /**
     * Uninstall everything (your current behavior).
     */
    public function uninstallAllModules(): array
    {
        $result = ['updated' => [], 'skipped' => [], 'errors' => []];

        try {
            $modules = Module::all();

            foreach ($modules as $module) {
                $moduleName = $module->getName();

                // optional uninstall hook
                $this->callIfExists("module:uninstall-{$moduleName}");

                Module::disable($moduleName);
                Module::delete($moduleName);

                $result['updated'][] = "{$moduleName}: deleted";
            }

            return $result;
        } catch (\Throwable $e) {
            $result['errors'][] = "Failed to uninstall modules: {$e->getMessage()}";
            return $result;
        }
    }

    /**
     * Update license + handle machine activation edge case + clear caches.
     * Return array suitable for controller JSON.
     */
    public function updateLicense(ProFeatures $proFeature, array $validatedInputs): array
    {
        $result = ['updated' => [], 'skipped' => [], 'errors' => []];

        $inputs = array_map(fn($v) => $v === 'NULL' ? null : $v, $validatedInputs);

        $licenseKey = $inputs['license'] ?? $proFeature->license;

        if ($licenseKey) {
            $licenseResponse = $this->keygenApiService->validateLicenseKey($licenseKey);

            // FIX: operator precedence - wrap the code checks together
            $needsActivation =
                $licenseResponse
                && (($licenseResponse['meta']['valid'] ?? true) === false)
                && in_array(($licenseResponse['meta']['code'] ?? ''), ['NO_MACHINE', 'NO_MACHINES', 'FINGERPRINT_SCOPE_MISMATCH'], true);

            if ($needsActivation) {
                $machineCount = $licenseResponse['data']['attributes']['machines']['meta']['count'] ?? 0;
                $maxMachines  = $licenseResponse['data']['attributes']['maxMachines'] ?? 1;

                if ($machineCount < $maxMachines) {
                    $licenseId = $licenseResponse['data']['id'] ?? null;
                    if ($licenseId) {
                        $this->keygenApiService->activateMachine($licenseKey, $licenseId);
                    }
                } else {
                    $result['errors'][] = 'Max machine limit reached';
                    return $result;
                }
            }
        }

        $proFeature->update($inputs);

        if ($licenseKey) {
            $this->clearLicenseCaches($licenseKey);
        }

        $result['updated'][] = 'License updated';
        return $result;
    }

    /**
     * Core sync logic used by both refresh + install.
     */
    protected function syncModules(string $mode, ?\Illuminate\Support\Collection $enabledModules = null, ?string $licenseOverride = null): array
    {
        $result = ['updated' => [], 'skipped' => [], 'errors' => []];

        $pro = $this->getProRow();
        if (!$pro) {
            $result['errors'][] = 'ProFeatures row not found (slug=fspbx).';
            return $result;
        }

        $licenseKey = $licenseOverride ?: $pro->license;
        if (!$licenseKey) {
            $result['errors'][] = 'No license key found.';
            return $result;
        }

        // Validate license
        $licenseResponse = $this->validateLicenseOrFail($licenseKey);
        if (isset($licenseResponse['__error'])) {
            $result['errors'][] = $licenseResponse['__error'];
            return $result;
        }

        $entitlements = $this->getEntitlementsOrFail($licenseResponse);
        if (isset($entitlements['__error'])) {
            $result['errors'][] = $entitlements['__error'];
            return $result;
        }

        $releases = $this->getReleasesOrFail($licenseKey);
        if (isset($releases['__error'])) {
            $result['errors'][] = $releases['__error'];
            return $result;
        }

        $map = $this->moduleMap();

        foreach ($entitlements as $entitlement) {
            $code = $entitlement['attributes']['code'] ?? null;
            if (!$code || !isset($map[$code])) {
                continue;
            }

            $moduleName = $map[$code]['module'];

            // Mode gating
            if ($mode === 'enabled_only') {
                if (!$enabledModules || !$enabledModules->contains($moduleName)) {
                    $result['skipped'][] = "{$moduleName}: not enabled";
                    continue;
                }
            }

            $latest = $this->findLatestReleaseForCode($releases, $code);
            if (!$latest) {
                $result['errors'][] = "{$moduleName}: no release found for {$code}";
                continue;
            }

            $version = $latest['attributes']['version'] ?? null;
            if (!$version) {
                $result['errors'][] = "{$moduleName}: latest release missing version";
                continue;
            }

            $artifactName = ($map[$code]['artifact'])($version);

            $deploy = $this->downloadAndDeployModule($licenseKey, $moduleName, $version, $artifactName);
            if ($deploy !== true) {
                $result['errors'][] = "{$moduleName}: {$deploy}";
                continue;
            }

            $result['updated'][] = "{$moduleName}: refreshed to latest ({$version})";
        }

        // IMPORTANT: don’t route:cache inside per-module loop. Cache once outside (app:update).
        // In UI install flow, you can still do it once at the end if you want:
        if ($mode === 'all_entitled') {
            Artisan::call('route:cache');
        }

        $this->clearLicenseCaches($licenseKey);

        return $result;
    }

    protected function downloadAndDeployModule(string $licenseKey, string $moduleName, string $version, string $artifactName): bool|string
    {
        $content = $this->keygenApiService->downloadArtifact($licenseKey, $version, $artifactName);
        if (!$content) {
            return "failed to download artifact {$artifactName}";
        }

        $this->saveAndExtract($artifactName, $content, $moduleName);

        Artisan::call('module:enable', ['module' => $moduleName]);

        // If you have module migrations, you can enable this:
        // Artisan::call('module:migrate', ['module' => $moduleName, '--force' => true]);

        Artisan::call('module:seed', ['module' => $moduleName, '--force' => true]);

        // Optional post hooks
        $this->callIfExists("module:install-{$moduleName}");
        $this->callIfExists("module:update-{$moduleName}");

        return true;
    }

    protected function getProRow(): ?ProFeatures
    {
        return ProFeatures::query()->where('slug', 'fspbx')->first();
    }

    protected function validateLicenseOrFail(string $licenseKey): array
    {
        $licenseResponse = $this->keygenApiService->validateLicenseKey($licenseKey);

        if (!$licenseResponse || !($licenseResponse['meta']['valid'] ?? false)) {
            return ['__error' => 'License invalid or expired.'];
        }

        return $licenseResponse;
    }

    protected function getEntitlementsOrFail(array $licenseResponse): array
    {
        $entitlements = $this->keygenApiService->getEntitlementsByLicense($licenseResponse) ?? [];
        if (empty($entitlements)) {
            return ['__error' => 'No entitlements found for this license.'];
        }
        return $entitlements;
    }

    protected function getReleasesOrFail(string $licenseKey): array
    {
        $releases = $this->keygenApiService->getReleases($licenseKey) ?? [];
        if (empty($releases)) {
            return ['__error' => 'No releases found for this license.'];
        }
        return $releases;
    }

    protected function moduleMap(): array
    {
        return [
            'CONTACT_CENTER_MODULE' => [
                'module' => 'ContactCenter',
                'artifact' => fn(string $version) => "fspbx-contact-module-{$version}.tar.gz",
            ],
            'STIR_SHAKEN_MODULE' => [
                'module' => 'StirShaken',
                'artifact' => fn(string $version) => "fspbx-stir-shaken-module-{$version}.tar.gz",
            ],
        ];
    }

    protected function findLatestReleaseForCode(array $releases, string $code): ?array
    {
        $matched = array_values(array_filter($releases, function ($r) use ($code) {
            return strtoupper($r['attributes']['name'] ?? '') === strtoupper($code);
        }));

        if (!$matched) return null;

        usort($matched, function ($a, $b) {
            $a_ver = $a['attributes']['semver'] ?? [];
            $b_ver = $b['attributes']['semver'] ?? [];

            return version_compare(
                "{$b_ver['major']}.{$b_ver['minor']}.{$b_ver['patch']}",
                "{$a_ver['major']}.{$a_ver['minor']}.{$a_ver['patch']}"
            );
        });

        return $matched[0] ?? null;
    }

    protected function clearLicenseCaches(string $licenseKey): void
    {
        Cache::forget("stir_shaken_license_validation_{$licenseKey}");
        Cache::forget("license_validation_{$licenseKey}");
    }

    protected function callIfExists(string $command): void
    {
        try {
            Artisan::call($command);
        } catch (CommandNotFoundException $e) {
            // ignore
        }
    }

    // --- move your existing extraction helpers here (same as you already have) ---

    protected function saveAndExtract(string $artifactName, string $artifactContent, string $moduleName): void
    {
        $filePath = base_path("Modules/{$artifactName}");
        $extractPath = base_path("Modules/{$moduleName}");

        if (file_exists($extractPath)) {
            $this->deleteDirectory($extractPath);
        }
        mkdir($extractPath, 0755, true);

        file_put_contents($filePath, $artifactContent);

        $tarFile = str_replace('.gz', '', $filePath);
        if (file_exists($tarFile)) {
            unlink($tarFile);
        }

        $phar = new \PharData($filePath);
        $phar->decompress();

        $phar = new \PharData($tarFile);
        $phar->extractTo($extractPath, null, true);

        unlink($filePath);
        unlink($tarFile);

        $subDirs = glob($extractPath . '/*', GLOB_ONLYDIR);

        if (count($subDirs) > 0) {
            $extractedDir = $subDirs[0];
            $files = scandir($extractedDir);

            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    rename("{$extractedDir}/{$file}", "{$extractPath}/{$file}");
                }
            }

            @rmdir($extractedDir);
        }
    }

    private function deleteDirectory(string $dirPath): void
    {
        if (!is_dir($dirPath)) return;

        $files = array_diff(scandir($dirPath), ['.', '..']);
        foreach ($files as $file) {
            $filePath = "{$dirPath}/{$file}";
            is_dir($filePath) ? $this->deleteDirectory($filePath) : @unlink($filePath);
        }
        @rmdir($dirPath);
    }
}
