<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SipRegistrationSummaryService
{
    private const CACHE_TTL_SECONDS = 15;

    /**
     * Return unique registered extension counts keyed by normalized SIP realm.
     *
     * @return array<string, int>
     */
    public function onlineExtensionCountsByRealm(): array
    {
        return Cache::remember(
            $this->cacheKey(),
            self::CACHE_TTL_SECONDS,
            fn () => $this->countUniqueExtensionsByRealm(
                app(FreeswitchEslService::class)->getAllSipRegistrations()
            )
        );
    }

    /**
     * A single extension with multiple registered devices counts as one online extension.
     * The realm remains part of the identity because extension numbers are domain-scoped.
     *
     * @param Collection<int, array<string, mixed>> $registrations
     * @return array<string, int>
     */
    public function countUniqueExtensionsByRealm(Collection $registrations): array
    {
        $extensionsByRealm = [];

        foreach ($registrations as $registration) {
            $realm = strtolower(trim((string) ($registration['sip_auth_realm'] ?? '')));
            $user = trim((string) ($registration['user'] ?? ''));

            if ($user === '') {
                $user = trim((string) ($registration['sip_auth_user'] ?? ''));
            }

            if ($realm === '' || $user === '') {
                continue;
            }

            $extensionsByRealm[$realm][$user] = true;
        }

        return collect($extensionsByRealm)
            ->map(fn (array $extensions) => count($extensions))
            ->all();
    }

    private function cacheKey(): string
    {
        $target = implode(':', [
            (string) config('eventsocket.ip'),
            (string) config('eventsocket.port'),
        ]);

        return 'sip_registrations:online_extensions_by_realm:v1:' . sha1($target);
    }
}
