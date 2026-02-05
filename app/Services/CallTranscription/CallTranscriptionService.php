<?php

namespace App\Services\CallTranscription;

use RuntimeException;
use Illuminate\Support\Facades\Cache;
use App\Services\CallRecordingUrlService;
use App\Services\CallTranscriptionConfigService;
use App\Services\CallTranscription\TranscriptionProviderRegistry;

class CallTranscriptionService
{
    public array $payload;

    public function __construct(
        private TranscriptionProviderRegistry $registry,
        private CallRecordingUrlService $recordingUrlService,
        private CallTranscriptionConfigService $configService,
    ) {}

    /**
     * Start a transcription for a given CDR UUID within optional domain scope.
     */
    public function transcribeCdr(string $xmlCdrUuid, ?string $domainUuid = null, array $overrides = []): array
    {
        try {

            $config = $this->transcriptionConfigCached($domainUuid);

            if (empty($config['enabled'])) {
                return [];
            }

            $providerKey  = $config['provider_key']    ?? null;
            $providerCfg  = (array)($config['provider_config'] ?? []);
            if (!$providerKey) {
                return [];
            }

            $provider = $this->registry->make($providerKey, $providerCfg);

            // Resolve recording URL (signed, expiring)
            $urls = $this->recordingUrlService->urlsForCdr($xmlCdrUuid, 600);
            $audioUrl = $urls['audio_url'] ?? null;
            if (!$audioUrl) {
                throw new RuntimeException("Recording URL not available for CDR {$xmlCdrUuid}");
            }

            $response =  $provider->transcribe($audioUrl, $overrides);
            $this->payload = $provider->payload;

            return $response;
        } catch (\Exception $e) {
            logger($e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }

    /** Build provider instance for read paths */
    public function providerForScope(?string $domainUuid)
    {
        $config = $this->transcriptionConfigCached($domainUuid);
        $providerKey = $config['provider_key'] ?? null;
        $providerCfg = (array)($config['provider_config'] ?? []);
        if (!$providerKey) {
            throw new RuntimeException('No transcription provider selected.');
        }
        return $this->registry->make($providerKey, $providerCfg);
    }

    /** Cached policy+config  */
    private function transcriptionConfigCached(?string $domainUuid): array
    {
        $cacheKey = 'call-transcription:config:' . ($domainUuid ?: 'system');

        return Cache::tags('ct-config')
            ->remember($cacheKey, now()->addHours(24), function () use ($domainUuid) {
                // Service should return:
                // ['enabled'=>bool,'provider_key'=>'assemblyai','provider_uuid'=>'...','provider_config'=>array]
                return $this->configService->effective($domainUuid);
            });
    }

    public function getCachedConfig(?string $domainUuid = null): array
    {
        return $this->transcriptionConfigCached($domainUuid);
    }

    public function currentProviderKey(?string $domainUuid): ?string
    {
        $cfg = $this->transcriptionConfigCached($domainUuid);
        return $cfg['provider_key'] ?? null;
    }

    public function shouldAutoTranscribe(?string $domainUuid): bool
    {
        $cfg = $this->transcriptionConfigCached($domainUuid);
        return array_key_exists('auto_transcribe', $cfg)
            ? (bool) $cfg['auto_transcribe']
            : false;
    }

    public function emailDeliveryConfig(?string $domainUuid): array
    {
        $cfg = $this->transcriptionConfigCached($domainUuid);

        $enabled = (bool) ($cfg['email_transcription'] ?? false);
        $email   = isset($cfg['email']) ? trim((string) $cfg['email']) : '';

        return [
            'enabled' => $enabled,
            'email'   => ($email !== '') ? $email : null,
        ];
    }
}
