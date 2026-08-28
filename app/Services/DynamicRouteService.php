<?php

namespace App\Services;

use App\Models\Dialplans;
use App\Models\DynamicRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DynamicRouteService
{
    public function __construct(
        private readonly PhoneNumberService $phoneNumbers,
    ) {}

    public const DESTINATION_TYPES_WITHOUT_TARGET = [
        'check_voicemail',
        'company_directory',
        'hangup',
    ];

    public const DESTINATION_TYPES = [
        'extensions',
        'voicemails',
        'ring_groups',
        'ivrs',
        'business_hours',
        'time_conditions',
        'contact_centers',
        'bridges',
        'faxes',
        'call_flows',
        'recordings',
        'conferences',
        'conference_centers',
        'ai_agents',
        'check_voicemail',
        'company_directory',
        'hangup',
    ];

    public function save(array $validated, ?DynamicRoute $dynamicRoute = null): DynamicRoute
    {
        return DB::transaction(function () use ($validated, $dynamicRoute) {
            $dynamicRoute ??= new DynamicRoute();
            $dynamicRoute->forceFill([
                'domain_uuid' => $dynamicRoute->domain_uuid ?: session('domain_uuid'),
                'dialplan_uuid' => $dynamicRoute->dialplan_uuid ?: (string) Str::uuid(),
                'name' => trim($validated['name']),
                'extension' => trim($validated['extension']),
                'source' => $validated['source'],
                'context' => $dynamicRoute->context ?: $this->domainName(),
                'default_destination_type' => $validated['default_destination_type'],
                'default_destination_value' => $this->targetValue($validated['default_destination_target'] ?? null),
                'default_destination_label' => $this->targetLabel($validated['default_destination_target'] ?? null),
                'enabled' => $this->booleanValue($validated['enabled'] ?? true),
                'description' => $this->blankToNull($validated['description'] ?? null),
            ])->save();

            $dynamicRoute->rules()->delete();

            foreach (array_values($validated['rules']) as $order => $rule) {
                $match = $this->phoneNumbers->dialplanMatchForDomain(
                    $rule['match_value'],
                    $dynamicRoute->domain_uuid
                );

                $dynamicRoute->rules()->create([
                    'match_value' => $match['canonical'],
                    'destination_type' => $rule['destination_type'],
                    'destination_value' => $this->targetValue($rule['destination_target'] ?? null),
                    'destination_label' => $this->targetLabel($rule['destination_target'] ?? null),
                    'rule_order' => $order,
                ]);
            }

            $dynamicRoute->load('rules');
            $this->syncDialplan($dynamicRoute);

            return $dynamicRoute;
        });
    }

    public function toggle(Collection $dynamicRoutes): void
    {
        DB::transaction(function () use ($dynamicRoutes) {
            foreach ($dynamicRoutes as $dynamicRoute) {
                $dynamicRoute->enabled = ! $dynamicRoute->enabled;
                $dynamicRoute->save();
                $this->syncDialplan($dynamicRoute->loadMissing('rules'));
            }
        });
    }

    public function delete(Collection $dynamicRoutes): int
    {
        return DB::transaction(function () use ($dynamicRoutes) {
            $deleted = 0;

            foreach ($dynamicRoutes as $dynamicRoute) {
                $context = $dynamicRoute->context;
                $dialplan = Dialplans::query()->whereKey($dynamicRoute->dialplan_uuid)->first();

                if ($dialplan) {
                    $dialplan->dialplan_details()->delete();
                    $dialplan->delete();
                }

                $dynamicRoute->rules()->delete();
                $dynamicRoute->delete();
                $deleted++;

                DB::afterCommit(fn () => app(DialplanService::class)->clearDialplanCache($context));
            }

            return $deleted;
        });
    }

    public function buildDetails(DynamicRoute $dynamicRoute): array
    {
        $details = [];
        $group = 0;
        $destinationExpression = '^' . preg_quote((string) $dynamicRoute->extension, '/') . '$';
        $sourceField = $this->sourceField($dynamicRoute->source);

        // Fail closed before evaluating any DID rules. A condition using
        // break="never" would allow a failed extension check to continue into
        // the rule conditions and could re-enter this route after a transfer.
        $details[] = $this->detail('condition', 'destination_number', $destinationExpression, $group, 10);
        $group += 10;

        foreach ($dynamicRoute->rules as $rule) {
            $match = $this->phoneNumbers->dialplanMatchForDomain(
                $rule->match_value,
                $dynamicRoute->domain_uuid
            );
            $action = $this->destinationAction(
                $rule->destination_type,
                $rule->destination_value,
                $dynamicRoute->context
            );

            $details[] = $this->detail(
                'condition',
                $sourceField,
                $match['expression'],
                $group,
                10,
                'on-true'
            );
            $details[] = $this->detail('action', 'set', 'dynamic_route_uuid=' . $dynamicRoute->dynamic_route_uuid, $group, 20, null, 'true');
            $details[] = $this->detail('action', $action['application'], $action['data'], $group, 30);
            $group += 10;
        }

        $default = $this->destinationAction(
            $dynamicRoute->default_destination_type,
            $dynamicRoute->default_destination_value,
            $dynamicRoute->context
        );

        // An action-only group is rendered by DialplanService as an absolute
        // condition and therefore runs only after every DID rule has failed.
        $details[] = $this->detail('action', 'set', 'dynamic_route_uuid=' . $dynamicRoute->dynamic_route_uuid, $group, 10, null, 'true');
        $details[] = $this->detail('action', $default['application'], $default['data'], $group, 20);

        return $details;
    }

    public function destinationAction(string $type, mixed $target, string $domainName): array
    {
        if (! in_array($type, self::DESTINATION_TYPES, true)) {
            throw ValidationException::withMessages([
                'destination_type' => [__('Choose a supported destination type.')],
            ]);
        }

        $target = $this->targetValue($target);
        $option = [
            'type' => $type,
            'extension' => $target,
            'option' => $target,
            'bridge_uuid' => $type === 'bridges' ? $target : null,
        ];
        $action = buildDestinationAction($option, $domainName);

        if (blank($action['destination_app'] ?? null)) {
            throw ValidationException::withMessages([
                'destination_type' => [__('Could not build the selected destination.')],
            ]);
        }

        return [
            'application' => $action['destination_app'],
            'data' => (string) ($action['destination_data'] ?? ''),
        ];
    }

    private function syncDialplan(DynamicRoute $dynamicRoute): void
    {
        $dialplan = Dialplans::query()->whereKey($dynamicRoute->dialplan_uuid)->first();

        app(DialplanService::class)->save([
            'editor_mode' => 'builder',
            'domain_uuid' => $dynamicRoute->domain_uuid,
            'dialplan_name' => 'Dynamic Route: ' . $dynamicRoute->name,
            'dialplan_number' => $dynamicRoute->extension,
            'dialplan_destination' => 'true',
            'dialplan_context' => $dynamicRoute->context,
            'dialplan_continue' => 'false',
            'dialplan_order' => 235,
            'dialplan_enabled' => $dynamicRoute->enabled ? 'true' : 'false',
            'dialplan_description' => $dynamicRoute->description,
            'dialplan_details' => $this->buildDetails($dynamicRoute),
        ], $dialplan ?? new Dialplans(['dialplan_uuid' => $dynamicRoute->dialplan_uuid]), preserveName: true);
    }

    private function detail(
        string $tag,
        string $type,
        string $data,
        int $group,
        int $order,
        ?string $break = null,
        ?string $inline = null
    ): array {
        return [
            'dialplan_detail_tag' => $tag,
            'dialplan_detail_type' => $type,
            'dialplan_detail_data' => $data,
            'dialplan_detail_break' => $break,
            'dialplan_detail_inline' => $inline,
            'dialplan_detail_group' => $group,
            'dialplan_detail_order' => $order,
            'dialplan_detail_enabled' => 'true',
        ];
    }

    private function sourceField(string $source): string
    {
        return match ($source) {
            DynamicRoute::SOURCE_CALLER_DESTINATION => '${caller_destination}',
            default => throw ValidationException::withMessages([
                'source' => [__('Choose a supported lookup source.')],
            ]),
        };
    }

    private function targetValue(mixed $target): ?string
    {
        if (is_array($target)) {
            $target = $target['bridge_uuid'] ?? $target['extension'] ?? $target['value'] ?? null;
        }

        return $this->blankToNull($target);
    }

    private function targetLabel(mixed $target): ?string
    {
        if (! is_array($target)) {
            return $this->blankToNull($target);
        }

        return $this->blankToNull($target['name'] ?? $target['label'] ?? $this->targetValue($target));
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function booleanValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function domainName(): string
    {
        return session('domain_name') ?: (string) DB::table('v_domains')
            ->where('domain_uuid', session('domain_uuid'))
            ->value('domain_name');
    }
}
