<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Models\CallTranscriptionPolicy;
use App\Models\CallTranscriptionProvider;
use App\Models\CallTranscriptionProviderConfig;
use Illuminate\Database\Eloquent\Casts\ArrayObject;

class CallTranscriptionConfigService
{
    /** Cache for 24 hours */
    private const CACHE_TTL_HOURS = 24;
    private const CACHE_PREFIX = 'call-transcription:config:';

    /**
     * Build the effective transcription config for a domain (or system if null).
     *
     * Returns:
     * [
     *   'enabled'         => bool,
     *   'provider_uuid'   => string|null,
     *   'provider_key'    => string|null,   // e.g. 'assemblyai'
     *   'provider_active' => bool|null,
     *   'provider_config' => array|null,    // exact domain config if present, else system config; no merge
     * ]
     */
    public function effective(?string $domainUuid): array
    {
        // 1) Load both possible policy rows (system + domain), pick domain if present
        /** @var Collection<int,CallTranscriptionPolicy> $policies */
        $policies = CallTranscriptionPolicy::query()
            ->where(function ($q) use ($domainUuid) {
                $q->whereNull('domain_uuid');
                if ($domainUuid) {
                    $q->orWhere('domain_uuid', $domainUuid);
                }
            })
            ->get()
            ->keyBy(fn($r) => $r->domain_uuid === null ? 'system' : 'domain');

        $system = $policies->get('system'); // may be null
        $domain = $policies->get('domain'); // may be null

        // Effective: domain overrides if set; else system
        $enabled       = $domain?->enabled ?? ($system?->enabled ?? false);
        $auto_transcribe = $domain?->auto_transcribe ?? ($system?->auto_transcribe ?? false);
        $providerUuid  = $domain?->provider_uuid ?? ($system?->provider_uuid ?? null);
        $emailTranscription = $domain?->email_transcription ?? ($system?->email_transcription ?? false);
        $email              = $domain?->email ?? ($system?->email ?? null);

        // 2) Provider row (may be null if not set yet)
        $provider = null;
        $providerKey = null;
        $providerActive = null;

        if ($providerUuid) {
            $provider = CallTranscriptionProvider::query()->find($providerUuid);
            $providerKey = $provider?->key;
            $providerActive = $provider?->is_active;
        }

        // 3) Provider config: use DOMAIN config if exists, otherwise SYSTEM config. 
        $config = null;

        if ($providerUuid) {
            // Domain-specific config takes precedence if present
            $providerConfig = CallTranscriptionProviderConfig::query()
                ->where('provider_uuid', $providerUuid)
                ->where('domain_uuid', $domainUuid)
                ->first();

            if (!$providerConfig) {
                // Fall back to system-level config (domain_uuid IS NULL)
                $providerConfig = CallTranscriptionProviderConfig::query()
                    ->where('provider_uuid', $providerUuid)
                    ->whereNull('domain_uuid')
                    ->first();
            }

            if ($providerConfig) $config = $providerConfig->toArray();
        }

        return [
            'enabled'          => $enabled,
            'auto_transcribe'  => $auto_transcribe,
            'email_transcription' => $emailTranscription,
            'email' => $email,
            'provider_uuid'    => $providerUuid,
            'provider_key'     => $providerKey,
            'provider_active'  => $providerActive,
            'provider_config'  => $config,
        ];
    }

    /** Bust cache after writes */
    public function invalidate(?string $domainUuid = null): void
    {
        if ($domainUuid) {
            Cache::tags('ct-config')->forget($this->cacheKey($domainUuid));
            return;
        }

        // no domain => wipe *all* tagged entries (system + every domain)
        Cache::tags('ct-config')->flush();
    }

    // =========================
    // Internals
    // =========================

    private function cacheKey(?string $domainUuid): string
    {
        return self::CACHE_PREFIX . ($domainUuid ?: 'system');
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
