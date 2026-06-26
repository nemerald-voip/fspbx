<?php

namespace App\Services;

use App\Models\DefaultSettings;
use App\Models\FusionCache;
use App\Models\SipProfileDomain;
use App\Models\SipProfiles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SipProfileService
{
    public function save(array $validated, ?SipProfiles $profile = null): SipProfiles
    {
        $existingHostname = $profile?->sip_profile_hostname;
        $profile ??= new SipProfiles(['sip_profile_uuid' => (string) Str::uuid()]);
        $isNew = ! $profile->exists;

        DB::transaction(function () use ($validated, $profile, $isNew) {
            $profile->fill($this->profileData($validated, $isNew));
            $profile->save();

            $this->syncDomains($profile, $validated['domains'] ?? []);
            $this->syncSettings($profile, $validated['settings'] ?? []);
        });

        $this->syncRuntime(collect([$existingHostname, $profile->sip_profile_hostname]));

        return $profile;
    }

    public function duplicate(SipProfiles $source): SipProfiles
    {
        $copy = new SipProfiles([
            'sip_profile_uuid' => (string) Str::uuid(),
            'sip_profile_name' => $this->uniqueName($source->sip_profile_name),
            'sip_profile_hostname' => $source->sip_profile_hostname,
            'sip_profile_enabled' => 'false',
            'sip_profile_description' => $source->sip_profile_description,
        ]);

        DB::transaction(function () use ($copy, $source) {
            $copy->fill($this->withAudit([], 'v_sip_profiles', true));
            $copy->save();

            $settings = DB::table('v_sip_profile_settings')
                ->where('sip_profile_uuid', $source->sip_profile_uuid)
                ->get();

            foreach ($settings as $setting) {
                DB::table('v_sip_profile_settings')->insert($this->withAudit([
                    'sip_profile_setting_uuid' => (string) Str::uuid(),
                    'sip_profile_uuid' => $copy->sip_profile_uuid,
                    'sip_profile_setting_name' => $setting->sip_profile_setting_name,
                    'sip_profile_setting_value' => $setting->sip_profile_setting_value,
                    'sip_profile_setting_enabled' => $setting->sip_profile_setting_enabled,
                    'sip_profile_setting_description' => $setting->sip_profile_setting_description,
                ], 'v_sip_profile_settings', true));
            }

            $domains = DB::table('v_sip_profile_domains')
                ->where('sip_profile_uuid', $source->sip_profile_uuid)
                ->get();

            foreach ($domains as $domain) {
                DB::table('v_sip_profile_domains')->insert($this->withAudit([
                    'sip_profile_domain_uuid' => (string) Str::uuid(),
                    'sip_profile_uuid' => $copy->sip_profile_uuid,
                    'sip_profile_domain_name' => $domain->sip_profile_domain_name,
                    'sip_profile_domain_alias' => $domain->sip_profile_domain_alias,
                    'sip_profile_domain_parse' => $domain->sip_profile_domain_parse,
                ], 'v_sip_profile_domains', true));
            }
        });

        return $copy;
    }

    private function uniqueName(?string $name): string
    {
        $base = trim((string) $name) !== '' ? trim((string) $name) : 'profile';
        $candidate = $base . ' copy';
        $suffix = 1;

        while (SipProfiles::query()->where('sip_profile_name', $candidate)->exists()) {
            $suffix++;
            $candidate = $base . ' copy ' . $suffix;
        }

        return $candidate;
    }

    public function toggle(Collection $profiles): void
    {
        $hostnames = $profiles->pluck('sip_profile_hostname');

        DB::transaction(function () use ($profiles) {
            foreach ($profiles as $profile) {
                $profile->sip_profile_enabled = $profile->sip_profile_enabled === 'true' ? 'false' : 'true';
                $this->applyAudit($profile, false);
                $profile->save();
            }
        });

        $this->syncRuntime($hostnames);
    }

    public function delete(Collection $profiles): void
    {
        $hostnames = $profiles->pluck('sip_profile_hostname');
        $profileNames = $profiles->pluck('sip_profile_name')->filter()->values();

        DB::transaction(function () use ($profiles) {
            $uuids = $profiles->pluck('sip_profile_uuid')->all();

            DB::table('v_sip_profile_domains')->whereIn('sip_profile_uuid', $uuids)->delete();
            DB::table('v_sip_profile_settings')->whereIn('sip_profile_uuid', $uuids)->delete();
            DB::table('v_sip_profiles')->whereIn('sip_profile_uuid', $uuids)->delete();
        });

        $this->deleteLegacyProfileFiles($profileNames);
        $this->syncRuntime($hostnames);
    }

    private function profileData(array $validated, bool $isNew): array
    {
        return $this->withAudit([
            'sip_profile_name' => $validated['sip_profile_name'],
            'sip_profile_hostname' => $this->nullable($validated['sip_profile_hostname'] ?? null),
            'sip_profile_enabled' => $validated['sip_profile_enabled'] ?? 'true',
            'sip_profile_description' => $validated['sip_profile_description'],
        ], 'v_sip_profiles', $isNew);
    }

    private function syncDomains(SipProfiles $profile, array $domains): void
    {
        $existing = SipProfileDomain::query()
            ->where('sip_profile_uuid', $profile->sip_profile_uuid)
            ->pluck('sip_profile_domain_uuid')
            ->all();

        $kept = [];

        foreach ($domains as $domain) {
            if (! $this->rowHasAnyValue($domain, [
                'sip_profile_domain_name',
                'sip_profile_domain_alias',
                'sip_profile_domain_parse',
            ])) {
                continue;
            }

            $uuid = $this->validUuid($domain['sip_profile_domain_uuid'] ?? null)
                ? $domain['sip_profile_domain_uuid']
                : (string) Str::uuid();
            $exists = in_array($uuid, $existing, true);

            if ($exists && ! userCheckPermission('sip_profile_domain_edit')) {
                $kept[] = $uuid;
                continue;
            }

            if (! $exists && ! userCheckPermission('sip_profile_domain_add')) {
                continue;
            }

            DB::table('v_sip_profile_domains')->updateOrInsert(
                ['sip_profile_domain_uuid' => $uuid],
                $this->withAudit([
                    'sip_profile_uuid' => $profile->sip_profile_uuid,
                    'sip_profile_domain_name' => $this->nullable($domain['sip_profile_domain_name'] ?? null),
                    'sip_profile_domain_alias' => $this->nullable($domain['sip_profile_domain_alias'] ?? null),
                    'sip_profile_domain_parse' => $this->nullable($domain['sip_profile_domain_parse'] ?? null),
                ], 'v_sip_profile_domains', ! $exists)
            );

            $kept[] = $uuid;
        }

        if (userCheckPermission('sip_profile_domain_delete')) {
            DB::table('v_sip_profile_domains')
                ->where('sip_profile_uuid', $profile->sip_profile_uuid)
                ->whereNotIn('sip_profile_domain_uuid', $kept ?: [''])
                ->delete();
        }
    }

    private function syncSettings(SipProfiles $profile, array $settings): void
    {
        $existing = DB::table('v_sip_profile_settings')
            ->where('sip_profile_uuid', $profile->sip_profile_uuid)
            ->pluck('sip_profile_setting_uuid')
            ->all();

        $kept = [];

        foreach ($settings as $setting) {
            if (! $this->rowHasAnyValue($setting, [
                'sip_profile_setting_name',
                'sip_profile_setting_value',
                'sip_profile_setting_description',
            ])) {
                continue;
            }

            $uuid = $this->validUuid($setting['sip_profile_setting_uuid'] ?? null)
                ? $setting['sip_profile_setting_uuid']
                : (string) Str::uuid();
            $exists = in_array($uuid, $existing, true);

            if ($exists && ! userCheckPermission('sip_profile_setting_edit')) {
                $kept[] = $uuid;
                continue;
            }

            if (! $exists && ! userCheckPermission('sip_profile_setting_add')) {
                continue;
            }

            DB::table('v_sip_profile_settings')->updateOrInsert(
                ['sip_profile_setting_uuid' => $uuid],
                $this->withAudit([
                    'sip_profile_uuid' => $profile->sip_profile_uuid,
                    'sip_profile_setting_name' => $this->nullable($setting['sip_profile_setting_name'] ?? null),
                    'sip_profile_setting_value' => $this->nullable($setting['sip_profile_setting_value'] ?? null),
                    'sip_profile_setting_enabled' => $setting['sip_profile_setting_enabled'] ?? 'true',
                    'sip_profile_setting_description' => $this->nullable($setting['sip_profile_setting_description'] ?? null),
                ], 'v_sip_profile_settings', ! $exists)
            );

            $kept[] = $uuid;
        }

        if (userCheckPermission('sip_profile_setting_delete')) {
            DB::table('v_sip_profile_settings')
                ->where('sip_profile_uuid', $profile->sip_profile_uuid)
                ->whereNotIn('sip_profile_setting_uuid', $kept ?: [''])
                ->delete();
        }
    }

    private function syncRuntime(Collection $hostnames): void
    {
        $hostnames = $hostnames
            ->map(fn ($hostname) => is_string($hostname) ? trim($hostname) : $hostname)
            ->filter()
            ->unique()
            ->values();

        if ($hostnames->isEmpty()) {
            $hostname = $this->switchName();
            if ($hostname) {
                $hostnames->push($hostname);
            }
        }

        $hostnames->each(fn (string $hostname) => FusionCache::clear('configuration:sofia.conf:' . $hostname));

        session(['reload_xml' => true]);
    }

    private function switchName(): ?string
    {
        $service = new FreeswitchEslService();

        if (! $service->isConnected()) {
            return null;
        }

        $hostname = trim((string) $service->executeCommand('switchname'));

        return $hostname !== '' ? $hostname : null;
    }

    private function deleteLegacyProfileFiles(Collection $profileNames): void
    {
        $confDir = $this->switchConfDir();
        if (! $confDir) {
            return;
        }

        $profileDir = rtrim($confDir, '/') . '/sip_profiles';
        if (! File::isDirectory($profileDir)) {
            return;
        }

        foreach ($profileNames as $profileName) {
            $profileName = (string) $profileName;
            if ($profileName === '' || basename($profileName) !== $profileName) {
                continue;
            }

            File::delete($profileDir . '/' . $profileName . '.xml');

            $directory = $profileDir . '/' . $profileName;
            if (File::isDirectory($directory)) {
                File::deleteDirectory($directory);
            }
        }
    }

    private function switchConfDir(): ?string
    {
        $value = DefaultSettings::query()
            ->where('default_setting_category', 'switch')
            ->where('default_setting_subcategory', 'conf')
            ->where('default_setting_name', 'dir')
            ->where('default_setting_enabled', true)
            ->value('default_setting_value');

        return filled($value) ? (string) $value : null;
    }

    private function withAudit(array $data, string $table, bool $isNew): array
    {
        if ($isNew && Schema::hasColumn($table, 'insert_date')) {
            $data['insert_date'] = now();
        }

        if ($isNew && Schema::hasColumn($table, 'insert_user')) {
            $data['insert_user'] = session('user_uuid');
        }

        if (Schema::hasColumn($table, 'update_date')) {
            $data['update_date'] = now();
        }

        if (Schema::hasColumn($table, 'update_user')) {
            $data['update_user'] = session('user_uuid');
        }

        return $data;
    }

    private function applyAudit(SipProfiles $profile, bool $isNew): void
    {
        foreach ($this->withAudit([], 'v_sip_profiles', $isNew) as $key => $value) {
            $profile->{$key} = $value;
        }
    }

    private function rowHasAnyValue(array $row, array $keys): bool
    {
        foreach ($keys as $key) {
            if (filled($row[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function validUuid($value): bool
    {
        return is_string($value) && Str::isUuid($value);
    }

    private function nullable($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return filled($value) ? (string) $value : null;
    }
}
