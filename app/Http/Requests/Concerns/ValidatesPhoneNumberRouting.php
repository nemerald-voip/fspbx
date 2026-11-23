<?php

namespace App\Http\Requests\Concerns;

use App\Services\CallRoutingOptionsService;
use Illuminate\Validation\Rule;

trait ValidatesPhoneNumberRouting
{
    protected function phoneNumberRoutingRules(): array
    {
        return [
            'routing_options' => ['nullable', 'array'],
            'routing_options.*' => ['required', 'array'],
            'routing_options.*.type' => ['required', 'string', Rule::in(CallRoutingOptionsService::destinationTypes())],
            'routing_options.*.extension' => ['nullable', 'string'],
            'routing_options.*.bridge_uuid' => ['nullable', 'uuid'],
            'routing_options.*.option' => ['nullable', 'uuid'],
            'destination_actions' => ['prohibited'],
        ];
    }

    protected function normalizePhoneNumberRouting(): void
    {
        $options = $this->input('routing_options');
        if (! is_array($options)) {
            return;
        }
        foreach ($options as &$option) {
            if (is_array($option) && is_int($option['extension'] ?? null)) {
                $option['extension'] = (string) $option['extension'];
            }
        }
        unset($option);
        $this->merge(['routing_options' => $options]);
    }
}
