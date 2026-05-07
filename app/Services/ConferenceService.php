<?php

namespace App\Services;

use App\Models\Conferences;
use App\Models\DialplanDetails;
use App\Models\Dialplans;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConferenceService
{
    private const APP_UUID = 'b81412e8-7253-91f4-e48e-42fc2c9a38d9';

    public function save(array $validated, ?Conferences $conference = null): Conferences
    {
        return DB::transaction(function () use ($validated, $conference) {
            $conference ??= new Conferences();
            $isNew = ! $conference->exists;

            $conferenceUuid = $conference->conference_uuid ?: (string) Str::uuid();
            $dialplanUuid = $conference->dialplan_uuid ?: (string) Str::uuid();
            $profile = $validated['conference_profile'] ?? 'default';
            $pinNumber = $this->blankToNull($validated['conference_pin_number'] ?? null);
            $flags = $this->blankToNull($validated['conference_flags'] ?? null);
            $description = $this->blankToNull($validated['conference_description'] ?? null);

            $conference->forceFill([
                'domain_uuid' => session('domain_uuid'),
                'conference_uuid' => $conferenceUuid,
                'dialplan_uuid' => $dialplanUuid,
                'conference_name' => $validated['conference_name'],
                'conference_extension' => $validated['conference_extension'],
                'conference_pin_number' => $pinNumber,
                'conference_profile' => $profile,
                'conference_flags' => $flags,
                'conference_email_address' => userCheckPermission('conference_email_address')
                    ? $this->blankToNull($validated['conference_email_address'] ?? null)
                    : $conference->conference_email_address,
                'conference_account_code' => userCheckPermission('conference_account_code')
                    ? $this->blankToNull($validated['conference_account_code'] ?? null)
                    : $conference->conference_account_code,
                'conference_order' => (int) ($validated['conference_order'] ?? 0),
                'conference_description' => $description,
                'conference_enabled' => $validated['conference_enabled'],
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
                'dialplan_name' => $validated['conference_name'],
                'dialplan_number' => $validated['conference_extension'],
                'dialplan_context' => $context,
                'dialplan_continue' => 'false',
                'dialplan_xml' => $this->dialplanXml(
                    $validated['conference_name'],
                    $dialplanUuid,
                    $conferenceUuid,
                    $validated['conference_extension'],
                    $profile,
                    $pinNumber,
                    $flags
                ),
                'dialplan_order' => 333,
                'dialplan_enabled' => $validated['conference_enabled'],
                'dialplan_description' => $description,
                $isNew || ! $dialplan->exists ? 'insert_date' : 'update_date' => now(),
                $isNew || ! $dialplan->exists ? 'insert_user' : 'update_user' => session('user_uuid'),
            ])->save();

            app(DialplanService::class)->clearDialplanCache($context);

            return $conference;
        });
    }

    public function toggle(Collection $conferences): void
    {
        DB::transaction(function () use ($conferences) {
            foreach ($conferences as $conference) {
                $enabled = $conference->conference_enabled === 'true' ? 'false' : 'true';

                $conference->forceFill([
                    'conference_enabled' => $enabled,
                    'update_date' => now(),
                    'update_user' => session('user_uuid'),
                ])->save();

                if ($conference->dialplan_uuid) {
                    Dialplans::query()
                        ->where('dialplan_uuid', $conference->dialplan_uuid)
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

    public function delete(Collection $conferences): int
    {
        return DB::transaction(function () use ($conferences) {
            $conferenceUuids = $conferences->pluck('conference_uuid');
            $dialplanUuids = $conferences->pluck('dialplan_uuid')->filter()->values();

            if ($dialplanUuids->isNotEmpty()) {
                DialplanDetails::query()
                    ->whereIn('dialplan_uuid', $dialplanUuids)
                    ->delete();

                Dialplans::query()
                    ->whereIn('dialplan_uuid', $dialplanUuids)
                    ->delete();
            }

            $deleted = Conferences::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('conference_uuid', $conferenceUuids)
                ->delete();

            app(DialplanService::class)->clearDialplanCache(session('domain_name'));

            return $deleted;
        });
    }

    public function copy(Collection $conferences): int
    {
        return DB::transaction(function () use ($conferences) {
            $count = 0;

            foreach ($conferences as $conference) {
                $copy = $conference->replicate();
                $copy->conference_uuid = (string) Str::uuid();
                $copy->dialplan_uuid = (string) Str::uuid();
                $copy->conference_description = trim((string) $conference->conference_description . ' (copy)') ?: null;
                $copy->insert_date = now();
                $copy->insert_user = session('user_uuid');
                $copy->update_date = null;
                $copy->update_user = null;
                $copy->save();

                if ($conference->dialplan_uuid) {
                    $dialplan = Dialplans::query()
                        ->where('dialplan_uuid', $conference->dialplan_uuid)
                        ->first();

                    if ($dialplan) {
                        $dialplanCopy = $dialplan->replicate();
                        $dialplanCopy->dialplan_uuid = $copy->dialplan_uuid;
                        $dialplanCopy->dialplan_xml = str_replace(
                            [$conference->conference_uuid, $conference->dialplan_uuid],
                            [$copy->conference_uuid, $copy->dialplan_uuid],
                            (string) $dialplan->dialplan_xml
                        );
                        $dialplanCopy->dialplan_description = trim((string) $dialplan->dialplan_description . ' (copy)') ?: null;
                        $dialplanCopy->insert_date = now();
                        $dialplanCopy->insert_user = session('user_uuid');
                        $dialplanCopy->update_date = null;
                        $dialplanCopy->update_user = null;
                        $dialplanCopy->save();
                    }
                }

                $count++;
            }

            app(DialplanService::class)->clearDialplanCache(session('domain_name'));

            return $count;
        });
    }

    private function dialplanXml(string $name, string $dialplanUuid, string $conferenceUuid, string $extension, string $profile, ?string $pinNumber, ?string $flags): string
    {
        $pin = $pinNumber ? '+' . $pinNumber : '';

        return implode("\n", [
            sprintf('<extension name="%s" continue="" uuid="%s">', $this->xml($name), $this->xml($dialplanUuid)),
            sprintf("\t" . '<condition field="destination_number" expression="^%s$">', $this->xml($extension)),
            "\t\t" . '<action application="answer" data=""/>',
            sprintf("\t\t" . '<action application="set" data="conference_uuid=%s" inline="true"/>', $this->xml($conferenceUuid)),
            sprintf("\t\t" . '<action application="set" data="conference_extension=%s" inline="true"/>', $this->xml($extension)),
            sprintf(
                "\t\t" . '<action application="conference" data="%s@%s@%s%s+flags{\'%s\'}"/>',
                $this->xml($extension),
                $this->xml(session('domain_name')),
                $this->xml($profile),
                $this->xml($pin),
                $this->xml($flags)
            ),
            "\t" . '</condition>',
            '</extension>',
        ]);
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
