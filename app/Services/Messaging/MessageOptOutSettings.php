<?php

namespace App\Services\Messaging;

use App\Models\DefaultSettings;
use App\Models\DomainSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MessageOptOutSettings
{
    public function props(?string $domainUuid = null): array
    {
        $default = DefaultSettings::where('default_setting_category', 'messaging')
            ->where('default_setting_subcategory', 'opt_out_scope')->where('default_setting_name', 'text')
            ->where('default_setting_enabled', 'true')->value('default_setting_value');
        $default = in_array($default, ['disabled', 'number', 'account'], true) ? $default : 'disabled';
        $override = $domainUuid ? $this->query($domainUuid)->where('domain_setting_enabled', 'true')
            ->value('domain_setting_value') : null;

        $effective = in_array($override, ['disabled', 'number', 'account'], true) ? $override : $default;
        $defaultKeywords = $this->keywordsValue(DefaultSettings::where('default_setting_category', 'messaging')
            ->where('default_setting_subcategory', 'opt_out_keywords')->where('default_setting_name', 'text')
            ->where('default_setting_enabled', 'true')->value('default_setting_value'));
        $keywordOverride = $domainUuid ? $this->query($domainUuid, 'opt_out_keywords')->where('domain_setting_enabled', 'true')
            ->value('domain_setting_value') : null;
        return [
            'opt_out_scope' => $domainUuid ? ($override ?: 'inherit') : $default,
            'opt_out_effective_scope' => $effective,
            'opt_out_default_scope' => $default,
            'opt_out_keywords' => $keywordOverride !== null ? $this->keywordsValue($keywordOverride) : $defaultKeywords,
            'opt_out_default_keywords' => $defaultKeywords,
            'opt_out_available' => $effective !== 'disabled'
                && (!$domainUuid || !filled(app(MessageWebhookSettings::class)->account($domainUuid)?->endpoint_url)),
        ];
    }

    public function save(?string $domainUuid, string $scope): void
    {
        DB::transaction(function () use ($domainUuid, $scope) {
            $previous = $this->props($domainUuid)['opt_out_effective_scope'];
            if ($domainUuid && $scope === 'inherit') {
                $this->query($domainUuid)->delete();
            } else {
                $prefix = $domainUuid ? 'domain_setting_' : 'default_setting_';
                $attributes = [$prefix.'category' => 'messaging', $prefix.'subcategory' => 'opt_out_scope', $prefix.'name' => 'text'];
                if ($domainUuid) $attributes['domain_uuid'] = $domainUuid;
                $model = $domainUuid ? DomainSettings::class : DefaultSettings::class;
                $model::updateOrCreate($attributes, [$prefix.'value' => $scope, $prefix.'enabled' => 'true']);
            }
            if ($previous !== 'number' && $this->props($domainUuid)['opt_out_effective_scope'] === 'number'
                && Schema::hasColumn('v_sms_destinations', 'opt_out_enabled')) {
                $numbers = DB::table('v_sms_destinations');
                if ($domainUuid) {
                    $numbers->where('domain_uuid', $domainUuid);
                } else {
                    // System changes only initialize accounts inheriting the system setting.
                    $numbers->whereNotIn('domain_uuid', DomainSettings::where('domain_setting_category', 'messaging')
                        ->where('domain_setting_subcategory', 'opt_out_scope')->where('domain_setting_name', 'text')
                        ->where('domain_setting_enabled', 'true')->whereIn('domain_setting_value', ['disabled', 'number', 'account'])
                        ->whereNotNull('domain_uuid')->select('domain_uuid'));
                }
                $numbers->update(['opt_out_enabled' => true]);
            }
        });
    }

    public function normalizeKeywords(array $keywords): array
    {
        return array_values(array_unique(['STOP', ...array_filter(array_map(
            fn ($keyword) => mb_strtoupper(trim($keyword), 'UTF-8'), $keywords,
        ), fn ($keyword) => $keyword !== '')]));
    }

    public function saveKeywords(?string $domainUuid, array $keywords): void
    {
        $keywords = $this->normalizeKeywords($keywords);
        if ($domainUuid && $keywords === $this->props()['opt_out_default_keywords']) {
            $this->query($domainUuid, 'opt_out_keywords')->delete();
            return;
        }
        $prefix = $domainUuid ? 'domain_setting_' : 'default_setting_';
        $attributes = [$prefix.'category' => 'messaging', $prefix.'subcategory' => 'opt_out_keywords', $prefix.'name' => 'text'];
        if ($domainUuid) $attributes['domain_uuid'] = $domainUuid;
        $model = $domainUuid ? DomainSettings::class : DefaultSettings::class;
        $model::updateOrCreate($attributes, [$prefix.'value' => json_encode($keywords, JSON_UNESCAPED_UNICODE), $prefix.'enabled' => 'true']);
    }

    private function keywordsValue(?string $value): array
    {
        $values = json_decode($value ?? '', true);
        return $this->normalizeKeywords(is_array($values) ? array_values(array_filter($values, 'is_string')) : []);
    }

    private function query(string $domainUuid, string $subcategory = 'opt_out_scope')
    {
        return DomainSettings::where('domain_uuid', $domainUuid)->where('domain_setting_category', 'messaging')
            ->where('domain_setting_subcategory', $subcategory)->where('domain_setting_name', 'text');
    }
}
