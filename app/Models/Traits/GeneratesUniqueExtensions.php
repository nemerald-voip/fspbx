<?php

namespace App\Models\Traits;

use App\Models\BusinessHour;
use App\Models\CallCenterQueues;
use App\Models\CallFlows;
use App\Models\ConferenceCenter;
use App\Models\Conferences;
use App\Models\Dialplans;
use App\Models\Extensions;
use App\Models\Faxes;
use App\Models\IvrMenus;
use App\Models\RingGroups;
use App\Models\Voicemails;

trait GeneratesUniqueExtensions
{
    protected function firstAvailableExtensionInRange(int $rangeStart, int $rangeEnd, ?string $domainUuid = null): ?string
    {
        $usedExtensions = $this->usedExtensionNumbers($domainUuid);

        for ($extension = $rangeStart; $extension <= $rangeEnd; $extension++) {
            if (! isset($usedExtensions[(string) $extension])) {
                return (string) $extension;
            }
        }

        return null;
    }

    protected function nextAvailableExtensionAfter(int $start, ?string $domainUuid = null, int $maxAttempts = 1000): ?string
    {
        $usedExtensions = $this->usedExtensionNumbers($domainUuid);

        for ($extension = $start; $extension < $start + $maxAttempts; $extension++) {
            if (! isset($usedExtensions[(string) $extension])) {
                return (string) $extension;
            }
        }

        return null;
    }

    protected function usedExtensionNumbers(?string $domainUuid = null)
    {
        $domainUuid ??= session('domain_uuid');
        $usedExtensions = collect();

        foreach ($this->extensionSources() as [$modelClass, $extensionColumn]) {
            $model = new $modelClass;
            $query = $modelClass::query()
                ->where('domain_uuid', $domainUuid);

            if ($this instanceof $modelClass && $this->exists) {
                $query->where($model->getKeyName(), '!=', $this->getKey());
            }

            $usedExtensions = $usedExtensions->merge($query->pluck($extensionColumn));
        }

        $usedExtensions = $usedExtensions->merge(
            Dialplans::where('domain_uuid', $domainUuid)
                ->where('dialplan_number', 'not like', '*%')
                ->pluck('dialplan_number')
        );

        return $usedExtensions
            ->filter(fn ($value) => ctype_digit((string) $value))
            ->map(fn ($value) => (string) (int) $value)
            ->unique()
            ->flip();
    }

    protected function extensionSources(): array
    {
        return [
            [Extensions::class, 'extension'],
            [Voicemails::class, 'voicemail_id'],
            [RingGroups::class, 'ring_group_extension'],
            [CallCenterQueues::class, 'queue_extension'],
            [Faxes::class, 'fax_extension'],
            [IvrMenus::class, 'ivr_menu_extension'],
            [CallFlows::class, 'call_flow_extension'],
            [ConferenceCenter::class, 'conference_center_extension'],
            [Conferences::class, 'conference_extension'],
            [BusinessHour::class, 'extension'],
        ];
    }
}
