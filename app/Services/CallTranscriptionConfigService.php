<?php

namespace App\Services;

use App\Models\CallTranscriptionPolicy;
use App\Models\CallTranscriptionProvider;
use App\Models\CallTranscriptionProviderConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class CallTranscriptionConfigService
{
    /** Cache for 24 hour by default */
    private const CACHE_TTL_HOURS = 24;

    public function forTenant(?string $tenantUuid): array
    {
        $key = $this->cacheKey($tenantUuid);

        return Cache::remember($key, now()->addHours(self::CACHE_TTL_HOURS), function () use ($tenantUuid) {
            // 1) Load policies (system + tenant) via Eloquent
            $systemPolicy = $this->getSystemPolicy();
            $tenantPolicy = $this->getTenantPolicy($tenantUuid);

            // 2) Resolve flags & provider
            $enabled = $this->resolveEnabled($systemPolicy, $tenantPolicy);
            $providerId = $this->resolveProviderId($systemPolicy, $tenantPolicy);

            if (!$enabled || !$providerId) {
                return $this->disabledResponse($tenantUuid);
            }

            // 3) Ensure provider is active
            $provider = CallTranscriptionProvider::query()
                ->whereKey($providerId)
                ->where('is_active', true)
                ->first();

            if (!$provider) {
                // Chosen provider is missing/inactive -> effectively disabled
                return $this->disabledResponse($tenantUuid);
            }

            // 4) Load configs for this provider in one query (system + tenant)
            [$systemCfg, $tenantCfg] = $this->getProviderConfigs($providerId, $tenantUuid);

            // 5) Deep-merge in PHP: tenant overrides keys over system
            $merged = $this->deepMergeAssoc($systemCfg, $tenantCfg);

            return [
                'tenant_uuid'   => $tenantUuid,
                'enabled'       => true,
                'provider_id'   => $provider->getKey(),
                'provider_key'  => $provider->key,
                'provider_name' => $provider->name,
                'config'        => $merged,
                'resolved_at'   => Carbon::now(),
            ];
        });
    }

    /** Forget the cached effective config (call from observers after writes) */
    public function invalidate(?string $tenantUuid = null): void
    {
        Cache::forget($this->cacheKey($tenantUuid));
    }

    /* =========================
       Internals (small helpers)
       ========================= */

    private function cacheKey(?string $tenantUuid): string
    {
        return 'txcfg:' . ($tenantUuid ?: 'system');
    }

    private function getSystemPolicy(): ?CallTranscriptionPolicy
    {
        return CallTranscriptionPolicy::query()->whereNull('tenant_uuid')->first();
    }

    private function getTenantPolicy(?string $tenantUuid): ?CallTranscriptionPolicy
    {
        if (!$tenantUuid) {
            return null;
        }
        return CallTranscriptionPolicy::query()->where('tenant_uuid', $tenantUuid)->first();
    }

    private function resolveEnabled(?CallTranscriptionPolicy $system, ?CallTranscriptionPolicy $tenant): bool
    {
        // system must be ON, tenant defaults to inherit(true) unless explicitly false
        $systemEnabled = (bool) ($system->enabled ?? false);
        $tenantEnabled = (bool) ($tenant->enabled ?? true);
        return $systemEnabled && $tenantEnabled;
    }

    private function resolveProviderId(?CallTranscriptionPolicy $system, ?CallTranscriptionPolicy $tenant): ?string
    {
        return $tenant->provider_id ?? $system->provider_id ?? null;
    }

    /**
     * Fetch system + tenant provider configs in one Eloquent query.
     * Returns two associative arrays (system, tenant).
     */
    private function getProviderConfigs(string $providerId, ?string $tenantUuid): array
    {
        $rows = CallTranscriptionProviderConfig::query()
            ->where('provider_id', $providerId)
            ->where(function ($q) use ($tenantUuid) {
                $q->whereNull('tenant_uuid');
                if ($tenantUuid) {
                    $q->orWhere('tenant_uuid', $tenantUuid);
                }
            })
            ->get();

        $sys = optional($rows->firstWhere('tenant_uuid', null))->config ?? [];
        $ten = $tenantUuid ? (optional($rows->firstWhere('tenant_uuid', $tenantUuid))->config ?? []) : [];

        // Normalize to arrays
        $sysArr = is_array($sys) ? $sys : (is_string($sys) ? (json_decode($sys, true) ?: []) : []);
        $tenArr = is_array($ten) ? $ten : (is_string($ten) ? (json_decode($ten, true) ?: []) : []);

        return [$sysArr, $tenArr];
    }

    /**
     * Deep merge associative arrays; lists/scalars are replaced.
     * Tenant ($override) wins over system ($base).
     */
    private function deepMergeAssoc(?array $base, ?array $override): array
    {
        if ($base === null) return $override ?? [];
        if ($override === null) return $base;

        $isAssoc = fn(array $a) => Arr::isAssoc($a);

        $result = $base;
        foreach ($override as $key => $val) {
            $exists = array_key_exists($key, $base);
            if ($exists) {
                $a = $base[$key];
                if (is_array($a) && is_array($val) && $isAssoc($a) && $isAssoc($val)) {
                    $result[$key] = $this->deepMergeAssoc($a, $val); // recurse for objects
                } else {
                    $result[$key] = $val; // replace lists/scalars
                }
            } else {
                $result[$key] = $val;
            }
        }
        return $result;
    }

    private function disabledResponse(?string $tenantUuid): array
    {
        return [
            'tenant_uuid'   => $tenantUuid,
            'enabled'       => false,
            'provider_id'   => null,
            'provider_key'  => null,
            'provider_name' => null,
            'config'        => [],
            'resolved_at'   => Carbon::now(),
        ];
    }
}
