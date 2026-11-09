<?php

namespace App\Services\Messaging;

use App\Models\Domain;
use App\Models\MessageWebhookSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MessageWebhookSettings
{
    public function available(): bool
    {
        return Schema::hasTable('message_webhook_settings')
            && Schema::hasColumn('v_sms_destinations', 'inbound_webhook_url')
            && Schema::hasColumn('v_sms_destinations', 'inbound_delivery');
    }

    public function account(string $domainUuid): ?MessageWebhookSetting
    {
        return Schema::hasTable('message_webhook_settings')
            ? MessageWebhookSetting::where('domain_uuid', $domainUuid)->first() : null;
    }

    public function props(string $domainUuid, bool $canManage = false): array
    {
        $setting = $this->account($domainUuid);

        return [
            'available' => $this->available(),
            'endpoint_url' => $setting?->endpoint_url,
            'signing_secret' => $canManage ? $setting?->signing_secret : null,
        ];
    }

    public function save(string $domainUuid, ?string $url): MessageWebhookSetting
    {
        return $this->persist($domainUuid, $url, true);
    }

    public function ensureSecret(string $domainUuid): MessageWebhookSetting
    {
        return $this->persist($domainUuid, null, false);
    }

    private function persist(string $domainUuid, ?string $url, bool $updateUrl): MessageWebhookSetting
    {
        if (! $this->available()) {
            throw ValidationException::withMessages(['endpoint_url' => [__('Messaging webhook setup is unavailable. Contact your administrator.')]]);
        }

        return DB::transaction(function () use ($domainUuid, $url, $updateUrl) {
            // Serialize initial secret creation for account and number editors.
            Domain::whereKey($domainUuid)->lockForUpdate()->firstOrFail();
            $setting = MessageWebhookSetting::firstOrNew(['domain_uuid' => $domainUuid]);
            if (! $setting->exists) {
                $setting->signing_secret = Str::random(64);
            }
            if ($updateUrl) {
                $setting->endpoint_url = filled($url) ? trim($url) : null;
            }
            $setting->save();

            return $setting;
        });
    }

    /**
     * Records where each listed number's incoming messages go, for display only.
     * Unlike resolve(), an incomplete webhook configuration is shown as chosen instead of throwing.
     */
    public function describe(iterable $numbers): void
    {
        $numbers = collect($numbers);
        $accountUrls = Schema::hasTable('message_webhook_settings')
            ? MessageWebhookSetting::whereIn('domain_uuid', $numbers->pluck('domain_uuid')->unique()->values())
                ->whereNotNull('endpoint_url')->pluck('endpoint_url', 'domain_uuid')
            : collect();

        foreach ($numbers as $number) {
            $mode = $number->inbound_delivery ?? 'inherit';
            $accountUrl = $accountUrls->get($number->domain_uuid);
            $number->delivery = match (true) {
                $mode === 'webhook' => ['type' => 'custom', 'url' => $number->inbound_webhook_url],
                $mode === 'inherit' && filled($accountUrl) => ['type' => 'account', 'url' => $accountUrl],
                default => ['type' => 'members'],
            };
        }
    }

    public function resolve(Model $number): array
    {
        $mode = $number->inbound_delivery ?? 'inherit';
        if ($mode === 'members') {
            return ['method' => 'members'];
        }
        $account = $this->account((string) $number->domain_uuid);
        $url = $mode === 'webhook' ? $number->inbound_webhook_url : $account?->endpoint_url;
        if ($mode !== 'webhook' && ! filled($url)) {
            return ['method' => 'members'];
        }
        if (! filled($url) || ! $account || ! filled($account->signing_secret)) {
            // Never fall back to internal delivery for a configured webhook.
            throw new \RuntimeException('The messaging webhook configuration is incomplete.');
        }

        return ['method' => 'webhook', 'endpoint_url' => $url];
    }
}
