<?php

namespace App\Services\CallWebhooks;

use App\Models\CallWebhookSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CallWebhookSubscriptionRegistry
{
    public const VERSION_KEY = 'call_webhook:subscriptions:version';

    private const REFRESH_SECONDS = 86400;
    private const VERSION_CHECK_SECONDS = 1;
    private const REDIS_WARNING_THROTTLE_SECONDS = 60;

    private array $subscriptionsByDomain = [];
    private array $domainUuidsByName = [];
    private bool $loaded = false;
    private string $loadedVersion = '';
    private float $loadedAt = 0;
    private float $lastVersionCheckAt = 0;
    private float $lastRedisWarningAt = 0;

    public function hasAny(): bool
    {
        $this->refreshIfNeeded();

        return $this->subscriptionsByDomain !== [];
    }

    public function forDomainUuid(string $domainUuid): ?CallWebhookSubscription
    {
        $this->refreshIfNeeded();

        return $this->subscriptionsByDomain[$domainUuid] ?? null;
    }

    public function domainUuidForName(string $domainName): ?string
    {
        $this->refreshIfNeeded();

        return $this->domainUuidsByName[strtolower($domainName)] ?? null;
    }

    public function invalidate(): void
    {
        $this->loaded = false;
        $this->lastVersionCheckAt = 0;

        try {
            Cache::store('redis')->increment(self::VERSION_KEY);
        } catch (Throwable $exception) {
            $this->logRedisWarning('Unable to invalidate the call webhook subscription cache.', $exception);
        }
    }

    private function refreshIfNeeded(): void
    {
        $now = microtime(true);
        if ($this->loaded && ($now - $this->lastVersionCheckAt) < self::VERSION_CHECK_SECONDS) {
            return;
        }

        $this->lastVersionCheckAt = $now;
        $version = $this->currentVersion();
        $expired = ! $this->loaded || ($now - $this->loadedAt) >= self::REFRESH_SECONDS;

        if (! $expired && hash_equals($this->loadedVersion, $version)) {
            return;
        }

        $subscriptions = CallWebhookSubscription::query()
            ->with('domain:domain_uuid,domain_name')
            ->where('enabled', true)
            ->get();

        $this->subscriptionsByDomain = [];
        $this->domainUuidsByName = [];

        foreach ($subscriptions as $subscription) {
            $this->subscriptionsByDomain[$subscription->domain_uuid] = $subscription;

            $domainName = trim((string) $subscription->domain?->domain_name);
            if ($domainName !== '') {
                $this->domainUuidsByName[strtolower($domainName)] = $subscription->domain_uuid;
            }
        }

        $this->loaded = true;
        $this->loadedVersion = $version;
        $this->loadedAt = $now;
    }

    private function currentVersion(): string
    {
        try {
            return (string) Cache::store('redis')->get(self::VERSION_KEY, '0');
        } catch (Throwable $exception) {
            $this->logRedisWarning('Unable to read the call webhook subscription cache version.', $exception);

            return $this->loadedVersion ?: '0';
        }
    }

    private function logRedisWarning(string $message, Throwable $exception): void
    {
        $now = microtime(true);
        if (($now - $this->lastRedisWarningAt) < self::REDIS_WARNING_THROTTLE_SECONDS) {
            return;
        }

        $this->lastRedisWarningAt = $now;
        Log::warning($message, ['error' => $exception->getMessage()]);
    }
}
