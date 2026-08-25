<?php

namespace App\Services;

use App\Jobs\QueueAiProviderToolSyncs;
use App\Models\AiProviderIntegration;
use App\Models\SipProfiles;
use RuntimeException;
use Throwable;

class AiProviderIntegrationService
{
    public const RETELL_CIDRS = [
        '18.98.16.120/30',
        '3.42.144.0/23',
        '153.57.128.0/18',
        '143.223.88.0/21',
        '161.115.160.0/19',
    ];

    public function __construct(private readonly FreeswitchGlobalVariableResolver $variables)
    {
    }

    public function retell(): AiProviderIntegration
    {
        return $this->integration('retell');
    }

    public function integration(string $provider): AiProviderIntegration
    {
        if ($provider !== 'retell') {
            throw new RuntimeException("Unsupported AI provider: {$provider}");
        }

        return AiProviderIntegration::query()->firstOrCreate(
            ['provider' => $provider],
            ['provider_cidrs' => self::RETELL_CIDRS, 'enabled' => false]
        );
    }

    public function save(array $validated): AiProviderIntegration
    {
        $integration = $this->retell();
        $accessControls = app(AccessControlService::class);
        $values = [
            'public_sip_host' => $this->normalizeHost($validated['public_sip_host']),
            'provider_cidrs' => $accessControls->normalizeCidrs($validated['provider_cidrs'])->all(),
            'enabled' => (bool) $validated['enabled'],
        ];

        if (filled($validated['api_key'] ?? null)) {
            $values['api_key'] = trim($validated['api_key']);
        }

        $integration->forceFill($values)->save();

        $accessControls->syncManagedProviderCidrs('retell', $integration->provider_cidrs);
        $accessControls->sync();

        if ($integration->enabled && $integration->hasApiKey()) {
            try {
                QueueAiProviderToolSyncs::dispatch(false, 'provider-integration-save')->afterCommit();
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $integration->refresh();
    }

    public function terminationUri(AiProviderIntegration $integration): string
    {
        if (! $integration->enabled || ! $integration->hasApiKey() || blank($integration->public_sip_host)) {
            throw new RuntimeException('Complete and enable the Retell integration before provisioning agents.');
        }

        return $this->normalizeHost($integration->public_sip_host) . ':' . $this->externalSipPort();
    }

    public function externalSipPort(): int
    {
        $profile = SipProfiles::query()
            ->whereRaw('lower(sip_profile_name) = ?', ['external'])
            ->where('sip_profile_enabled', 'true')
            ->first();

        if (! $profile) {
            throw new RuntimeException('The enabled external SIP profile was not found.');
        }

        $port = $profile->settings()
            ->where('sip_profile_setting_name', 'sip-port')
            ->where('sip_profile_setting_enabled', 'true')
            ->value('sip_profile_setting_value');

        $port = $this->variables->resolve($port);

        if (! is_numeric($port) || (int) $port < 1 || (int) $port > 65535) {
            throw new RuntimeException('The external SIP profile has no valid enabled SIP port.');
        }

        return (int) $port;
    }

    private function normalizeHost(string $host): string
    {
        return rtrim(trim($host), '.');
    }
}
