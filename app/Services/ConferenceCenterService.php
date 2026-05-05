<?php

namespace App\Services;

use App\Models\ConferenceCenter;
use App\Models\DialplanDetails;
use App\Models\Dialplans;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConferenceCenterService
{
    private const APP_UUID = 'b81412e8-7253-91f4-e48e-42fc2c9a38d9';

    public function save(array $validated, ?ConferenceCenter $conferenceCenter = null): ConferenceCenter
    {
        return DB::transaction(function () use ($validated, $conferenceCenter) {
            $conferenceCenter ??= new ConferenceCenter();
            $isNew = ! $conferenceCenter->exists;

            $conferenceCenterUuid = $conferenceCenter->conference_center_uuid ?: (string) Str::uuid();
            $dialplanUuid = $conferenceCenter->dialplan_uuid ?: (string) Str::uuid();

            $conferenceCenter->forceFill([
                'domain_uuid' => session('domain_uuid'),
                'conference_center_uuid' => $conferenceCenterUuid,
                'dialplan_uuid' => $dialplanUuid,
                'conference_center_name' => $validated['conference_center_name'],
                'conference_center_extension' => $validated['conference_center_extension'],
                'conference_center_greeting' => $this->blankToNull($validated['conference_center_greeting'] ?? null),
                'conference_center_pin_length' => (string) $validated['conference_center_pin_length'],
                'conference_center_enabled' => $validated['conference_center_enabled'],
                'conference_center_description' => $this->blankToNull($validated['conference_center_description'] ?? null),
                $isNew ? 'insert_date' : 'update_date' => now(),
                $isNew ? 'insert_user' : 'update_user' => session('user_uuid'),
            ])->save();

            $dialplan = Dialplans::query()
                ->where('dialplan_uuid', $dialplanUuid)
                ->first() ?? new Dialplans();

            $context = session('domain_name');
            $dialplan->forceFill([
                'domain_uuid' => session('domain_uuid'),
                'dialplan_uuid' => $dialplanUuid,
                'app_uuid' => self::APP_UUID,
                'dialplan_name' => $validated['conference_center_name'],
                'dialplan_number' => $validated['conference_center_extension'],
                'dialplan_context' => $context,
                'dialplan_continue' => 'false',
                'dialplan_xml' => $this->dialplanXml(
                    $validated['conference_center_name'],
                    $dialplanUuid,
                    $validated['conference_center_extension'],
                    (int) $validated['conference_center_pin_length']
                ),
                'dialplan_order' => 333,
                'dialplan_enabled' => $validated['conference_center_enabled'],
                'dialplan_description' => $this->blankToNull($validated['conference_center_description'] ?? null),
                $isNew || ! $dialplan->exists ? 'insert_date' : 'update_date' => now(),
                $isNew || ! $dialplan->exists ? 'insert_user' : 'update_user' => session('user_uuid'),
            ])->save();

            app(DialplanService::class)->clearDialplanCache($context);

            return $conferenceCenter;
        });
    }

    public function toggle(Collection $conferenceCenters): void
    {
        DB::transaction(function () use ($conferenceCenters) {
            foreach ($conferenceCenters as $conferenceCenter) {
                $enabled = $conferenceCenter->conference_center_enabled === 'true' ? 'false' : 'true';

                $conferenceCenter->forceFill([
                    'conference_center_enabled' => $enabled,
                    'update_date' => now(),
                    'update_user' => session('user_uuid'),
                ])->save();

                if ($conferenceCenter->dialplan_uuid) {
                    Dialplans::query()
                        ->where('dialplan_uuid', $conferenceCenter->dialplan_uuid)
                        ->update([
                            'dialplan_enabled' => $enabled,
                            'update_date' => now(),
                            'update_user' => session('user_uuid'),
                        ]);
                }
            }
        });

        app(DialplanService::class)->clearDialplanCache(session('domain_name'));
    }

    public function delete(Collection $conferenceCenters): int
    {
        return DB::transaction(function () use ($conferenceCenters) {
            $dialplanUuids = $conferenceCenters->pluck('dialplan_uuid')->filter()->values();

            if ($dialplanUuids->isNotEmpty()) {
                DialplanDetails::query()
                    ->whereIn('dialplan_uuid', $dialplanUuids)
                    ->delete();

                Dialplans::query()
                    ->whereIn('dialplan_uuid', $dialplanUuids)
                    ->delete();
            }

            $deleted = ConferenceCenter::query()
                ->whereIn('conference_center_uuid', $conferenceCenters->pluck('conference_center_uuid'))
                ->delete();

            app(DialplanService::class)->clearDialplanCache(session('domain_name'));

            return $deleted;
        });
    }

    private function dialplanXml(string $name, string $dialplanUuid, string $extension, int $pinLength): string
    {
        $lines = [
            sprintf(
                '<extension name="%s" continue="" uuid="%s">',
                $this->xml($name),
                $this->xml($dialplanUuid)
            ),
        ];

        if ($pinLength > 1 && $pinLength < 4) {
            $lines[] = sprintf(
                "\t" . '<condition field="destination_number" expression="^(%s)(\d{%s})$" break="on-true">',
                $this->xml($extension),
                $this->xml((string) $pinLength)
            );
            $lines[] = "\t\t" . '<action application="set" data="destination_number=$1"/>';
            $lines[] = "\t\t" . '<action application="set" data="pin_number=$2"/>';
            $lines[] = "\t\t" . '<action application="lua" data="app.lua conference_center"/>';
            $lines[] = "\t" . '</condition>';
        }

        $lines[] = sprintf(
            "\t" . '<condition field="destination_number" expression="^%s$">',
            $this->xml($extension)
        );
        $lines[] = "\t\t" . '<action application="lua" data="app.lua conference_center"/>';
        $lines[] = "\t" . '</condition>';
        $lines[] = '</extension>';

        return implode("\n", $lines);
    }

    private function xml(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
