<?php

namespace App\Services;

use App\Models\Dialplans;
use App\Models\Extensions;
use App\Models\FusionCache;
use App\Models\PagingGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Paging groups are one-way pages through lua/paging_group.lua: members'
 * phones auto-answer and either join a muted conference with the caller (live)
 * or play what the caller recorded after they hang up (recorded).
 */
class PagingGroupService
{
    public const DIALPLAN_APP_UUID = 'c6f1d4a2-5b7e-4e0f-9a3d-8b2c1e6f7a94';
    public const DIALPLAN_ORDER = 101;

    public function save(array $data, ?PagingGroup $group, string $domainUuid, string $domainName): PagingGroup
    {
        $group = DB::transaction(function () use ($data, $group, $domainUuid) {
            $group ??= new PagingGroup(['domain_uuid' => $domainUuid, 'dialplan_uuid' => (string) Str::uuid()]);
            $group->fill([
                'name' => $data['name'],
                'extension' => $data['extension'],
                'busy_mode' => $data['busy_mode'],
                'mode' => $data['mode'],
                'intro_beep' => (bool) ($data['intro_beep'] ?? false),
                'intro_recording' => $data['intro_recording'] ?? null,
                'enabled' => (bool) ($data['enabled'] ?? true),
                'description' => $data['description'] ?? null,
            ]);
            $group->dialplan_uuid ??= (string) Str::uuid();
            $group->save();

            $group->members()->sync($this->accountExtensionUuids($data['members'] ?? [], $domainUuid));
            $group->allowedCallers()->sync($this->accountExtensionUuids($data['allowed_callers'] ?? [], $domainUuid));

            return $group;
        });

        $this->writeDialplan($group, $domainName);

        return $group;
    }

    public function delete(PagingGroup $group, string $domainName): void
    {
        DB::transaction(function () use ($group) {
            if ($group->dialplan_uuid) {
                Dialplans::query()->where('dialplan_uuid', $group->dialplan_uuid)->where('domain_uuid', $group->domain_uuid)->delete();
            }
            $group->delete();
        });

        $this->reloadDialplan($domainName);
    }

    public function writeDialplan(PagingGroup $group, string $domainName): void
    {
        $xml = self::dialplanXml(
            $group,
            $this->extensionNumbers($group->members()->pluck('v_extensions.extension_uuid')->all(), $group->domain_uuid),
            $this->extensionNumbers($group->allowedCallers()->pluck('v_extensions.extension_uuid')->all(), $group->domain_uuid),
        );

        $dialplan = Dialplans::query()->where('dialplan_uuid', $group->dialplan_uuid)->first() ?? new Dialplans([
            'dialplan_uuid' => $group->dialplan_uuid,
            'domain_uuid' => $group->domain_uuid,
            'app_uuid' => self::DIALPLAN_APP_UUID,
            'dialplan_context' => $domainName,
            'dialplan_continue' => 'false',
            'dialplan_order' => self::DIALPLAN_ORDER,
            'insert_date' => now(),
            'insert_user' => auth()->user()?->user_uuid,
        ]);

        $dialplan->fill([
            'dialplan_name' => $group->name,
            'dialplan_number' => $group->extension,
            'dialplan_xml' => $xml,
            'dialplan_enabled' => $group->enabled,
            'dialplan_description' => $group->description,
        ]);
        if ($dialplan->exists) {
            $dialplan->update_date = now();
            $dialplan->update_user = auth()->user()?->user_uuid;
        }
        $dialplan->save();

        $this->reloadDialplan($domainName);
    }

    /**
     * @param string[] $members extension numbers that are paged
     * @param string[] $callers extension numbers allowed to page; empty allows anyone in the account
     */
    public static function dialplanXml(PagingGroup $group, array $members, array $callers): string
    {
        $attr = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1);
        $pattern = fn (array $values) => implode('|', array_map(fn ($value) => preg_quote((string) $value, '/'), $values));

        // Recordings Manager files live in the account's recordings folder.
        $intro = $group->intro_recording ? '${recordings_dir}/${domain_name}/' . $group->intro_recording : '';

        $actions = implode("\n", [
            "\t\t" . '<action application="set" data="paging_group_uuid=' . $attr($group->uuid) . '"/>',
            "\t\t" . '<action application="set" data="paging_destinations=' . $attr(implode(',', $members)) . '"/>',
            "\t\t" . '<action application="set" data="paging_mode=' . ($group->mode === PagingGroup::MODE_RECORDED ? 'recorded' : 'live') . '"/>',
            "\t\t" . '<action application="set" data="paging_busy=' . ($group->busy_mode === PagingGroup::BUSY_FORCE ? 'force' : 'skip') . '"/>',
            "\t\t" . '<action application="set" data="paging_beep=' . ($group->intro_beep ? 'true' : 'false') . '"/>',
            "\t\t" . '<action application="set" data="paging_intro=' . $attr($intro) . '"/>',
            "\t\t" . '<action application="lua" data="lua/paging_group.lua"/>',
        ]);

        $xml = '<extension name="' . $attr($group->name) . '" continue="false" uuid="' . $attr($group->dialplan_uuid) . '">' . "\n";

        if ($callers) {
            // sip_auth_username is only set after the caller's digest authentication,
            // so it identifies the calling extension and can't be spoofed.
            $xml .= "\t" . '<condition field="destination_number" expression="^' . $attr($pattern([$group->extension])) . '$"/>' . "\n"
                . "\t" . '<condition field="${sip_auth_username}" expression="^(' . $attr($pattern($callers)) . ')$">' . "\n"
                . $actions . "\n"
                . "\t\t" . '<anti-action application="answer" data=""/>' . "\n"
                . "\t\t" . '<anti-action application="sleep" data="500"/>' . "\n"
                . "\t\t" . '<anti-action application="playback" data="ivr/ivr-not_have_permission.wav"/>' . "\n"
                . "\t\t" . '<anti-action application="hangup" data="CALL_REJECTED"/>' . "\n"
                . "\t</condition>\n";
        } else {
            $xml .= "\t" . '<condition field="destination_number" expression="^' . $attr($pattern([$group->extension])) . '$">' . "\n"
                . $actions . "\n"
                . "\t</condition>\n";
        }

        return $xml . '</extension>';
    }

    /**
     * Keep only extensions that belong to the account.
     *
     * @return string[]
     */
    private function accountExtensionUuids(array $uuids, string $domainUuid): array
    {
        return Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->whereIn('extension_uuid', array_filter($uuids, fn ($uuid) => Str::isUuid((string) $uuid)))
            ->pluck('extension_uuid')
            ->all();
    }

    /**
     * @return string[]
     */
    private function extensionNumbers(array $uuids, string $domainUuid): array
    {
        if (!$uuids) {
            return [];
        }

        return Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->whereIn('extension_uuid', $uuids)
            ->orderBy('extension')
            ->pluck('extension')
            ->all();
    }

    private function reloadDialplan(string $domainName): void
    {
        FusionCache::clear('dialplan:' . $domainName);
        app(FreeswitchEslService::class)->executeCommand('bgapi reloadxml');
    }
}
