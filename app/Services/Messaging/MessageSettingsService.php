<?php

namespace App\Services\Messaging;

use App\Models\Extensions;
use App\Models\MessageSetting;
use App\Services\PhoneNumberService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use libphonenumber\PhoneNumberFormat;

class MessageSettingsService
{
    public function __construct(
        private MessageParticipantService $participants,
        private PhoneNumberService $phoneNumbers,
    ) {}

    public static function carriers(): array
    {
        return [
            'apidaze' => 'Apidaze',
            'bandwidth' => 'Bandwidth',
            'bulkvs' => 'BulkVS',
            'clicksend' => 'ClickSend',
            'thinq' => 'Commio (ThinQ)',
            'fibernetics' => 'Fibernetics',
            'sinch' => 'Sinch',
            'telnyx' => 'Telnyx',
            'twilio' => 'Twilio',
            'voipms' => 'VoIP.MS',
            'voxutel' => 'Voxutel',
        ];
    }

    public function query(string $domainUuid): Builder
    {
        return MessageSetting::query()->where('domain_uuid', $domainUuid);
    }

    public function normalizeDestination(string $number, string $domainUuid): string
    {
        return $this->phoneNumbers->formatForDomain($number, $domainUuid, PhoneNumberFormat::E164);
    }

    public function create(string $domainUuid, array $data): MessageSetting
    {
        return DB::transaction(function () use ($domainUuid, $data) {
            $setting = new MessageSetting();
            $setting->domain_uuid = $domainUuid;
            $setting->enabled = 'true';
            $this->saveChanges($setting, $data);

            return $setting->refresh();
        });
    }

    public function update(MessageSetting $setting, array $data): MessageSetting
    {
        return DB::transaction(function () use ($setting, $data) {
            $setting = $this->query($setting->domain_uuid)->whereKey($setting->getKey())->lockForUpdate()->firstOrFail();
            $this->saveChanges($setting, $data);

            return $setting->refresh();
        });
    }

    /** The caller supplies the accounts it is authorized to manage. */
    public function bulkUpdate(array $domainUuids, array $settingUuids, array $data): void
    {
        DB::transaction(function () use ($domainUuids, $settingUuids, $data) {
            $settings = MessageSetting::whereIn('domain_uuid', $domainUuids)->whereKey($settingUuids)
                ->orderBy('sms_destination_uuid')->lockForUpdate()->get();
            foreach ($settings as $setting) {
                $this->saveChanges($setting, $data);
            }
        });
    }

    /** Delete settings and their access lists within the authorized accounts. */
    public function delete(array $domainUuids, array $settingUuids): int
    {
        return DB::transaction(function () use ($domainUuids, $settingUuids) {
            $settings = MessageSetting::whereIn('domain_uuid', $domainUuids)->whereKey($settingUuids)
                ->orderBy('sms_destination_uuid')->lockForUpdate()->get();
            foreach ($settings as $setting) {
                DB::table('sms_destination_members')->where('domain_uuid', $setting->domain_uuid)
                    ->where('sms_destination_uuid', $setting->sms_destination_uuid)->delete();
                $setting->delete();
            }

            return $settings->count();
        });
    }

    private function saveChanges(MessageSetting $setting, array $data): void
    {
        $hasMembers = array_key_exists('allowed_extension_uuids', $data);
        $members = $data['allowed_extension_uuids'] ?? [];
        $legacyAssignment = array_key_exists('chatplan_detail_data', $data);
        $attributes = Arr::only($data, ['destination', 'carrier', 'enabled', 'email', 'description', 'chatplan_detail_data']);
        if (isset($attributes['destination']) && is_string($attributes['destination'])) {
            $attributes['destination'] = $this->normalizeDestination($attributes['destination'], $setting->domain_uuid);
        }
        if (isset($attributes['enabled']) && is_bool($attributes['enabled'])) {
            $attributes['enabled'] = $attributes['enabled'] ? 'true' : 'false';
        }
        $setting->fill($attributes)->save();

        if (! $hasMembers && $legacyAssignment) {
            $members = Extensions::without('advSettings')->where('domain_uuid', $setting->domain_uuid)
                ->where('extension', $setting->chatplan_detail_data)->pluck('extension_uuid')->all();
        }
        if ($hasMembers || $legacyAssignment) {
            $this->participants->assign($setting, $members);
        }
    }
}
