<?php

namespace App\Http\Controllers;

use App\Services\PmsProviderSettings;
use App\Services\TigerTmsSiteMapper;
use App\Services\TigerTmsWebhookNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\WebhookProcessor;

class TigerTmsWebhookController extends Controller
{
    public function __invoke(Request $request, TigerTmsSiteMapper $siteMapper, TigerTmsWebhookNormalizer $normalizer): JsonResponse
    {
        Log::info('TigerTMS webhook received', [
            'request' => $this->safeRequestContext($request),
            'payload' => $request->all(),
        ]);

        $normalized = $normalizer->normalize($request->all());
        $payloadDomainUuid = $siteMapper->inbound($normalized['site'] ?? null);
        $resolvedDomainUuid = $payloadDomainUuid ? strtolower($payloadDomainUuid) : '';
        $siteIsUnprocessable = ($normalized['site'] ?? null) !== null && $payloadDomainUuid === null;
        $providerEnabled = $resolvedDomainUuid !== ''
            && app(PmsProviderSettings::class)->isTigerTms($resolvedDomainUuid);

        $key = 'tigertms-pms:' . ($resolvedDomainUuid !== '' ? $resolvedDomainUuid : 'unknown') . ':' . (string) $request->ip();
        $rate = config('tigertms.rate');
        $max = (int) ($rate['max_attempts'] ?? 120);
        $decay = (int) ($rate['decay_seconds'] ?? 60);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            return response()->json([
                'result' => 'failed',
                'information' => 'Temporarily throttled. Retry after ' . RateLimiter::availableIn($key) . ' seconds.',
            ], 200);
        }
        RateLimiter::hit($key, $decay);

        $request->merge([
            '_tigertms_resolved_domain_uuid' => $resolvedDomainUuid,
            '_tigertms_site' => $normalized['site'] ?? null,
            '_tigertms_site_unprocessable' => $siteIsUnprocessable,
            '_tigertms_provider_enabled' => $providerEnabled,
            '_tigertms_room' => $normalized['room'] ?? null,
            '_tigertms_event' => $normalized['event'] ?? null,
            '_tigertms_action' => $normalized['action'] ?? null,
            '_tigertms_request' => $this->safeRequestContext($request),
        ]);

        Log::info('TigerTMS webhook accepted', [
            'domain_uuid' => $resolvedDomainUuid ?: null,
            'site' => $normalized['site'] ?? null,
            'site_unprocessable' => $siteIsUnprocessable,
            'provider_enabled' => $providerEnabled,
            'room' => $normalized['room'] ?? null,
            'event' => $normalized['event'] ?? null,
            'action' => $normalized['action'] ?? null,
            'request' => $this->safeRequestContext($request),
            'payload' => $request->except(['_tigertms_request']),
        ]);

        $webhookConfig = new WebhookConfig([
            'name' => 'tigertms-pms',
            'signing_secret' => '',
            'signature_header_name' => 'Authorization',
            'signature_validator' => \App\Http\Webhooks\SignatureValidators\AlwaysValidSignatureValidator::class,
            'webhook_profile' => \Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile::class,
            'webhook_response' => \Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo::class,
            'webhook_model' => \App\Models\WhCall::class,
            'process_webhook_job' => \App\Http\Webhooks\Jobs\ProcessTigerTmsWebhookJob::class,
        ]);

        try {
            (new WebhookProcessor($request, $webhookConfig))->process();
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'result' => 'failed',
                'information' => 'Temporary error while queueing. Please retry shortly.',
            ], 200);
        }

        return response()->json(['result' => 'success', 'information' => 'accepted'], 200);
    }

    private function safeRequestContext(Request $request): array
    {
        $headers = collect($request->headers->all())
            ->mapWithKeys(function ($value, $key) {
                $sensitive = in_array(strtolower((string) $key), [
                    'authorization',
                    'cookie',
                    'x-api-key',
                    'x-auth-token',
                    'x-signature',
                    'tigertms-token',
                ], true);

                return [$key => $sensitive ? ['[redacted]'] : $value];
            })
            ->all();

        return [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'headers' => $headers,
        ];
    }
}
