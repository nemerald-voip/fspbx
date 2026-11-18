<?php

namespace App\Services;

use App\Models\{Destinations, Dialplans, Domain, Faxes};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Account checks for native phone-number management; API v1 authorizes its route account. */
class PhoneNumberAccessService
{
    public function currentDomain(): string
    {
        $domain = session('domain_uuid');
        abort_unless(is_string($domain) && Str::isUuid($domain), 403);

        return $domain;
    }

    public function query(): Builder
    {
        $domain = $this->currentDomain();

        return Destinations::query()->whereNotNull('domain_uuid')
            ->when(! userCheckPermission('destination_all'), fn ($query) => $query->where('domain_uuid', $domain));
    }

    public function targetDomain(?string $requested, ?Destinations $number = null): string
    {
        $current = $this->currentDomain();
        $source = $number ? (string) $number->domain_uuid : $current;
        abort_unless($source !== '' && ($source === $current || userCheckPermission('destination_all')), 404);
        if ($requested === null || $requested === $source) {
            return $source;
        }
        abort_unless(userCheckPermission('destination_all') && userCheckPermission('destination_domain'), 403);
        return $requested;
    }

    public function selection(array $ids): Collection
    {
        $numbers = $this->query()->whereIn('destination_uuid', $ids)->orderBy('destination_uuid')->get();
        abort_unless($numbers->count() === count($ids), 404);

        return $numbers;
    }

    public function checkDialplan(Destinations $number): ?Dialplans
    {
        // A corrupt or stale pointer must never authorize another account's dialplan.
        $plan = $number->dialplan_uuid ? Dialplans::whereKey($number->dialplan_uuid)->first() : null;
        abort_if($plan && $plan->domain_uuid !== $number->domain_uuid, 404);

        return $plan;
    }

    public function validateWrite(array $data, ?Destinations $number = null): array
    {
        // Raw dialplan actions are never a native write input, even when empty.
        unset($data['destination_actions']);
        $domain = $this->targetDomain($data['domain_uuid'] ?? null, $number);
        $moving = $number && $domain !== $number->domain_uuid;
        $data['domain_uuid'] = $domain;
        $fax = array_key_exists('fax_uuid', $data) ? $data['fax_uuid'] : $number?->fax_uuid;
        if ($fax === 'NULL') {
            $data['fax_uuid'] = $fax = null;
        }
        if ($fax && ! Faxes::where('domain_uuid', $domain)->whereKey($fax)->exists()) {
            throw ValidationException::withMessages(['fax_uuid' => __('Select a valid routing target in this account.')]);
        }
        if ($number) {
            $this->checkDialplan($number);
        }

        if ($moving && ! array_key_exists('routing_options', $data)) {
            $data['routing_options'] = $number->routing_options ?? [];
        }
        if (array_key_exists('routing_options', $data)) {
            $data['destination_actions'] = json_encode($this->routingActions($data['routing_options'] ?? [], $domain));
            unset($data['routing_options']);
        }

        // Legacy caller-ID conditions also contain executable routing actions. Rebuild
        // those actions through the same account-scoped registry used by the editor.
        if ($moving && ! array_key_exists('destination_conditions', $data) && $number->destination_conditions) {
            $data['destination_conditions'] = json_decode($number->destination_conditions, true);
        }
        if (array_key_exists('destination_conditions', $data)) {
            $service = new CallRoutingOptionsService($domain);
            $conditions = [];
            foreach ($data['destination_conditions'] ?? [] as $index => $condition) {
                $raw = $condition['condition_target']['targetValue'] ?? null;
                if (is_string($raw) && preg_match('/^(transfer|lua|playback):(.*)$/', $raw, $matches)) {
                    $action = ['destination_app' => $matches[1], 'destination_data' => trim($matches[2])];
                } else {
                    $action = ['destination_app' => $condition['condition_app'] ?? null, 'destination_data' => $condition['condition_data'] ?? null];
                }
                $options = $service->reverseEngineerDestinationActions(json_encode([$action]));
                if (empty($options[0]['type'])) {
                    throw ValidationException::withMessages(['destination_conditions.'.$index => __('Select a valid routing target in this account.')]);
                }
                $built = $this->routingActions($options, $domain)[0];
                $conditions[] = [
                    'condition_field' => $condition['condition_field'], 'condition_expression' => $condition['condition_expression'],
                    'condition_app' => $built['destination_app'], 'condition_data' => $built['destination_data'],
                ];
            }
            $data['destination_conditions'] = $conditions ? json_encode($conditions) : null;
        }

        return $data;
    }

    public function routingActions(array $options, string $domain): array
    {
        foreach ($options as &$option) {
            if (is_array($option) && is_int($option['extension'] ?? null)) {
                $option['extension'] = (string) $option['extension'];
            }
        }
        unset($option);
        $service = new CallRoutingOptionsService($domain);
        $domainName = Domain::whereKey($domain)->value('domain_name');
        abort_unless($domainName, 404);
        $actions = [];
        foreach ($options as $index => $option) {
            $type = $option['type'] ?? '';
            $value = $type === 'bridges' ? ($option['bridge_uuid'] ?? $option['option'] ?? $option['extension'] ?? '') : ($option['extension'] ?? '');
            $target = CallRoutingOptionsService::requiresTarget($type) ? $service->findTarget($type, $value, true) : null;
            try {
                $actions[] = $service->actionForTarget($type, $target, $domainName);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['routing_options.'.$index.'.extension' => $e->errors()['target']]);
            }
        }

        return $actions;
    }
}
