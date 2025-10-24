<?php

namespace App\Services;

use App\Models\CallTranscriptionPolicy;
use App\Models\CallTranscriptionProvider;
use App\Models\CallTranscriptionProviderConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\ArrayObject;

class CallTranscriptionConfigService
{
    /** Cache for 24 hours */
    private const CACHE_TTL_HOURS = 24;

    /**
     * Resolve effective transcription config for a domain.
     * - Policy: domain row overrides system row.
     * - Config: if a domain config exists, use it as-is; else use system config.
     */
    public function forDomain(?string $domainUuid): array
    {
        return Cache::remember(
            $this->cacheKey($domainUuid),
            now()->addHours(self::CACHE_TTL_HOURS),
            function () use ($domainUuid) {
                // 1) Load policies (system + domain)
                $systemPolicy = $this->getSystemPolicy();
                $domainPolicy = $this->getDomainPolicy($domainUuid);

                // 2) Resolve flags & provider
                $enabled      = $this->resolveEnabled($systemPolicy, $domainPolicy);
                $providerUuid = $this->resolveProviderUuid($systemPolicy, $domainPolicy);

                if (!$enabled || !$providerUuid) {
                    return $this->disabledResponse($domainUuid);
                }

                // 3) Ensure provider is active
                $provider = CallTranscriptionProvider::query()
                    ->whereKey($providerUuid)
                    ->where('is_active', true)
                    ->first();

                if (!$provider) {
                    return $this->disabledResponse($domainUuid);
                }

                // 4) Pick config: domain first, else system (NO MERGE)
                $config = $this->pickProviderConfig($providerUuid, $domainUuid);

                return [
                    'domain_uuid'   => $domainUuid,
                    'enabled'       => true,
                    'provider_uuid' => $provider->getKey(),
                    'provider_key'  => $provider->key,
                    'provider_name' => $provider->name,
                    'config'        => $config,        // domain as-is OR system as-is
                    'resolved_at'   => Carbon::now(),
                ];
            }
        );
    }

    /** Bust cache after writes */
    public function invalidate(?string $domainUuid = null): void
    {
        Cache::forget($this->cacheKey($domainUuid));
    }

    // =========================
    // Internals
    // =========================

    private function cacheKey(?string $domainUuid): string
    {
        return 'txcfg:' . ($domainUuid ?: 'system');
    }

    private function getSystemPolicy(): ?CallTranscriptionPolicy
    {
        return CallTranscriptionPolicy::query()->whereNull('domain_uuid')->first();
    }

    private function getDomainPolicy(?string $domainUuid): ?CallTranscriptionPolicy
    {
        if (!$domainUuid) return null;

        return CallTranscriptionPolicy::query()
            ->where('domain_uuid', $domainUuid)
            ->first();
    }

    private function resolveEnabled(?CallTranscriptionPolicy $system, ?CallTranscriptionPolicy $domain): bool
    {
        // system must be ON; domain inherits true unless explicitly false
        $systemEnabled = (bool) ($system->enabled ?? false);
        $domainEnabled = (bool) ($domain->enabled ?? true);
        return $systemEnabled && $domainEnabled;
    }

    private function resolveProviderUuid(?CallTranscriptionPolicy $system, ?CallTranscriptionPolicy $domain): ?string
    {
        return $domain->provider_uuid ?? $system->provider_uuid ?? null;
    }

    /**
     * Return provider config with precedence:
     *   1) domain row (exact domain_uuid)
     *   2) system row (domain_uuid NULL)
     * If none found, returns [].
     */
    private function pickProviderConfig(string $providerUuid, ?string $domainUuid): array
    {
        $query = CallTranscriptionProviderConfig::query()
            ->where('provider_uuid', $providerUuid);

        if ($domainUuid) {
            // Try domain first
            $domainRow = (clone $query)->where('domain_uuid', $domainUuid)->first();
            if ($domainRow) {
                return $this->toArray($domainRow->config);
            }
        }

        // Fallback to system
        $systemRow = $query->whereNull('domain_uuid')->first();
        return $this->toArray($systemRow?->config);
    }

    private function toArray(mixed $val): array
    {
        if (is_array($val)) return $val;
        if ($val instanceof ArrayObject) return $val->toArray();
        if (is_string($val)) return json_decode($val, true) ?: [];
        return [];
    }

    private function disabledResponse(?string $domainUuid): array
    {
        return [
            'domain_uuid'   => $domainUuid,
            'enabled'       => false,
            'provider_uuid' => null,
            'provider_key'  => null,
            'provider_name' => null,
            'config'        => [],
            'resolved_at'   => Carbon::now(),
        ];
    }
}
