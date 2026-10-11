<?php

namespace App\Services;

use App\Models\Flag;
use App\Models\FlagOverride;
use App\Models\FlagRule;
use Illuminate\Support\Facades\DB;

class FlagService
{
    public function __construct(private FlagStateService $state)
    {
    }

    public function save(array $validated, ?Flag $flag, string $domainUuid): Flag
    {
        return DB::transaction(function () use ($validated, $flag, $domainUuid) {
            $flag ??= new Flag(['domain_uuid' => $domainUuid]);
            $manual = $validated['type'] === Flag::TYPE_MANUAL;

            $flag->fill([
                'domain_uuid' => $domainUuid,
                'name' => $validated['name'],
                'flag_number' => (string) $validated['flag_number'],
                'type' => $validated['type'],
                'timezone' => $validated['timezone'] ?? null,
                'active' => $manual ? (bool) ($validated['active'] ?? false) : false,
                'pin_number' => $manual ? ($validated['pin_number'] ?? null) : null,
                'blf_enabled' => (bool) ($validated['blf_enabled'] ?? false),
                'enabled' => (bool) ($validated['enabled'] ?? true),
                'description' => $validated['description'] ?? null,
            ])->save();

            $flag->rules()->delete();
            if (!$manual) {
                foreach ($this->buildRules($validated) as $rule) {
                    $flag->rules()->create($rule);
                }
            }

            $flag->allowedExtensions()->sync($manual ? ($validated['allowed_extensions'] ?? []) : []);

            return $flag->fresh(['rules']);
        });
    }

    public function buildRules(array $validated): array
    {
        $rules = [];

        foreach ($validated['weekly_rules'] ?? [] as $row) {
            $rules[] = [
                'rule_type' => FlagRule::TYPE_WEEKLY,
                'weekdays' => collect($row['weekdays'] ?? [])->map(fn ($day) => (int) $day)->unique()->sort()->implode(','),
                'start_time' => $row['time_from'],
                'end_time' => $row['time_to'],
            ];
        }

        foreach ($validated['date_rules'] ?? [] as $row) {
            $rules[] = [
                'rule_type' => FlagRule::TYPE_DATE_RANGE,
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'] ?? $row['start_date'],
                'start_time' => $row['time_from'] ?? null,
                'end_time' => $row['time_to'] ?? null,
            ];
        }

        return $rules;
    }

    /**
     * Shape a saved flag for the VueForm editor.
     */
    public function formData(Flag $flag): array
    {
        $rules = $flag->rules->sortBy('created_at')->values();

        return [
            'uuid' => $flag->uuid,
            'name' => $flag->name,
            'flag_number' => $flag->flag_number,
            'type' => $flag->type,
            'timezone' => $flag->timezone,
            'active' => (bool) $flag->active,
            'pin_number' => $flag->pin_number,
            'blf_enabled' => (bool) $flag->blf_enabled,
            'enabled' => (bool) $flag->enabled,
            'description' => $flag->description,
            'allowed_extensions' => $flag->allowedExtensions->pluck('extension_uuid')->values()->all(),
            'weekly_rules' => $rules->where('rule_type', FlagRule::TYPE_WEEKLY)->map(fn ($rule) => [
                'weekdays' => array_map('strval', FlagStateService::parseWeekdays($rule->weekdays)),
                'time_from' => substr((string) $rule->start_time, 0, 5),
                'time_to' => substr((string) $rule->end_time, 0, 5),
            ])->values()->all(),
            'date_rules' => $rules->where('rule_type', FlagRule::TYPE_DATE_RANGE)->map(fn ($rule) => [
                'start_date' => substr((string) $rule->start_date, 0, 10),
                'end_date' => substr((string) $rule->end_date, 0, 10),
                'time_from' => $rule->start_time ? substr((string) $rule->start_time, 0, 5) : null,
                'time_to' => $rule->end_time ? substr((string) $rule->end_time, 0, 5) : null,
            ])->values()->all(),
        ];
    }

    /**
     * Replace the flag overrides for a ring group or virtual receptionist.
     * Row order is the priority: the first active flag wins.
     */
    public function syncOverrides(string $ownerType, string $ownerUuid, string $domainUuid, array $rows, string $domainName): void
    {
        DB::transaction(fn () => $this->replaceOverrides($ownerType, $ownerUuid, $domainUuid, $rows, $domainName));
    }

    private function replaceOverrides(string $ownerType, string $ownerUuid, string $domainUuid, array $rows, string $domainName): void
    {
        FlagOverride::query()
            ->where('owner_type', $ownerType)
            ->where('owner_uuid', $ownerUuid)
            ->where('domain_uuid', $domainUuid)
            ->delete();

        foreach (array_values($rows) as $index => $row) {
            $target = $row['target'] ?? null;
            if (is_array($target)) {
                $target = $target['bridge_uuid'] ?? $target['extension'] ?? $target['value'] ?? null;
            }

            $destination = buildDestinationAction(['type' => $row['action'], 'extension' => $target], $domainName);

            FlagOverride::query()->create([
                'domain_uuid' => $domainUuid,
                'owner_type' => $ownerType,
                'owner_uuid' => $ownerUuid,
                'flag_uuid' => $row['flag_uuid'],
                'override_app' => $destination['destination_app'],
                'override_data' => $destination['destination_data'],
                'sort_order' => $index,
            ]);
        }
    }

    public function copyOverrides(string $ownerType, string $fromUuid, string $toUuid, string $domainUuid): void
    {
        $overrides = FlagOverride::query()
            ->where('owner_type', $ownerType)
            ->where('owner_uuid', $fromUuid)
            ->where('domain_uuid', $domainUuid)
            ->get();

        foreach ($overrides as $override) {
            $copy = $override->replicate();
            $copy->uuid = null;
            $copy->owner_uuid = $toUuid;
            $copy->save();
        }
    }

    public function deleteOverrides(string $ownerType, array $ownerUuids, string $domainUuid): void
    {
        FlagOverride::query()
            ->where('owner_type', $ownerType)
            ->whereIn('owner_uuid', $ownerUuids)
            ->where('domain_uuid', $domainUuid)
            ->delete();
    }

    /**
     * Form rows for saved overrides.
     */
    public function overrideFormRows(iterable $overrides, ?string $domainUuid): array
    {
        $routing = new CallRoutingOptionsService($domainUuid);
        $rows = [];

        foreach ($overrides as $override) {
            $details = $routing->reverseEngineerFlagAction(trim("{$override->override_app} {$override->override_data}"));
            $hasTarget = filled($details['extension'] ?? null) || filled($details['option'] ?? null);

            $rows[] = [
                'flag_uuid' => $override->flag_uuid,
                'action' => $details['type'] ?? null,
                'target' => $hasTarget ? [
                    'value' => $details['option'] ?? $details['extension'] ?? null,
                    'extension' => $details['extension'] ?? null,
                    'name' => $details['name'] ?? $details['extension'] ?? null,
                    'bridge_uuid' => ($details['type'] ?? null) === 'bridges' ? ($details['option'] ?? null) : null,
                ] : null,
            ];
        }

        return $rows;
    }

    /**
     * Validation rules shared by the ring group and virtual receptionist requests.
     */
    public static function overrideRules(): array
    {
        $domainUuid = session('domain_uuid');

        return [
            'flag_overrides' => ['sometimes', 'nullable', 'array'],
            'flag_overrides.*.flag_uuid' => [
                'required',
                'uuid',
                'distinct',
                \Illuminate\Validation\Rule::exists('flags', 'uuid')->where('domain_uuid', $domainUuid),
            ],
            'flag_overrides.*.action' => [
                'required',
                \Illuminate\Validation\Rule::in(CallRoutingOptionsService::destinationTypes()),
            ],
            'flag_overrides.*.target' => [new \App\Rules\RoutingTarget('action', byDestination: true)],
        ];
    }

    public static function overrideMessages(): array
    {
        return [
            'flag_overrides.*.flag_uuid.required' => __('Choose a flag.'),
            'flag_overrides.*.flag_uuid.distinct' => __('Each flag can only be used once.'),
            'flag_overrides.*.action.required' => __('Choose where calls go while the flag is active.'),
        ];
    }

    /**
     * Flags offered on ring group and virtual receptionist forms.
     */
    public function flagOptions(string $domainUuid): array
    {
        return Flag::query()
            ->where('domain_uuid', $domainUuid)
            ->orderBy('name')
            ->get(['uuid', 'name', 'flag_number', 'type'])
            ->map(fn (Flag $flag) => [
                'value' => $flag->uuid,
                'label' => $flag->name . ' (' . ($flag->isManual() ? __('Manual') : __('Scheduled')) . ')',
            ])
            ->all();
    }

    /**
     * Push the current lamp state to phones watching flag<number>@domain.
     */
    public function notifyBlf(Flag $flag, ?string $domainName = null, ?string $previousNumber = null): void
    {
        $domainName ??= DB::table('v_domains')->where('domain_uuid', $flag->domain_uuid)->value('domain_name');

        if (!filled($domainName)) {
            return;
        }

        $esl = new FreeswitchEslService();

        // A renumbered flag leaves the old lamp lit unless it is cleared.
        if (filled($previousNumber) && $previousNumber !== $flag->flag_number) {
            $esl->executeCommand($this->notifyCommand($previousNumber, $domainName, false), false);
        }

        $active = $flag->blf_enabled && $this->state->isActive($flag);
        $esl->executeCommand($this->notifyCommand($flag->flag_number, $domainName, $active));
    }

    public function clearBlf(string $flagNumber, string $domainName): void
    {
        (new FreeswitchEslService())->executeCommand($this->notifyCommand($flagNumber, $domainName, false));
    }

    private function notifyCommand(string $flagNumber, string $domainName, bool $active): string
    {
        return sprintf(
            'bgapi luarun lua/flag_notify.lua %s %s %s',
            preg_replace('/\D/', '', $flagNumber),
            preg_replace('/[^A-Za-z0-9.\-_]/', '', $domainName),
            $active ? 'true' : 'false'
        );
    }
}
