<?php

namespace App\Services\CallTranscription;

use RuntimeException;
use Illuminate\Support\Facades\Cache;
use App\Services\CallRecordingUrlService;
use App\Services\CallTranscriptionConfigService;
use App\Services\CallTranscription\TranscriptionProviderRegistry;

class CallTranscriptionService
{
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
        logger('transcribeCdr');

        $effective = $this->effectiveCached($domainUuid);

        return [];

        if (empty($effective['enabled'])) {
            throw new RuntimeException('Call transcription is disabled by policy.');
        }

        $providerKey  = $effective['provider_key']    ?? null;
        $providerCfg  = (array)($effective['provider_config'] ?? []);
        if (!$providerKey) {
            throw new RuntimeException('No transcription provider selected.');
        }

        $provider = $this->registry->make($providerKey, $providerCfg);

        // Resolve recording URL (signed, expiring)
        $urls = $this->recordingUrlService->urlsForCdr($xmlCdrUuid, 600);
        $audioUrl = $urls['audio_url'] ?? null;
        if (!$audioUrl) {
            throw new RuntimeException("Recording URL not available for CDR {$xmlCdrUuid}");
        }

        return $provider->transcribe($audioUrl, $overrides);
    }


    /** Build provider instance for read paths (policy.enabled not required) */
    private function providerForScope(?string $domainUuid)
    {
        $effective = $this->effectiveCached($domainUuid);
        $providerKey = $effective['provider_key'] ?? null;
        $providerCfg = (array)($effective['provider_config'] ?? []);
        if (!$providerKey) {
            throw new RuntimeException('No transcription provider selected.');
        }
        return $this->registry->make($providerKey, $providerCfg);
    }

    /** Cached effective policy+config  */
    private function effectiveCached(?string $domainUuid): array
    {
        $cacheKey = 'call-transcription:config:' . ($domainUuid ?: 'system');

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($domainUuid) {
            logger('getting updated config');
            // Your service should return:
            // ['enabled'=>bool,'provider_key'=>'assemblyai','provider_uuid'=>'...','provider_config'=>array]
            return $this->configService->effective($domainUuid);
        });
    }

}
