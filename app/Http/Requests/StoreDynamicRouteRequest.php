<?php

namespace App\Http\Requests;

use App\Models\DynamicRoute;
use App\Rules\UniqueExtension;
use App\Services\DynamicRouteService;
use App\Services\PhoneNumberService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDynamicRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('dynamic_route_create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'extension' => ['required', 'string', 'max:32', 'regex:/^\d+$/', new UniqueExtension($this->dynamicRouteUuid())],
            'source' => ['required', Rule::in([DynamicRoute::SOURCE_CALLER_DESTINATION])],
            'enabled' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_destination_type' => ['required', Rule::in(DynamicRouteService::DESTINATION_TYPES)],
            'default_destination_target' => ['nullable'],
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.match_value' => ['required', 'string', 'max:255'],
            'rules.*.destination_type' => ['required', Rule::in(DynamicRouteService::DESTINATION_TYPES)],
            'rules.*.destination_target' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateDestination(
                $validator,
                (string) $this->input('default_destination_type'),
                $this->input('default_destination_target'),
                'default_destination_target'
            );

            $matches = [];
            $phoneNumbers = app(PhoneNumberService::class);
            foreach ($this->input('rules', []) as $index => $rule) {
                $this->validateDestination(
                    $validator,
                    (string) ($rule['destination_type'] ?? ''),
                    $rule['destination_target'] ?? null,
                    "rules.{$index}.destination_target"
                );

                $match = trim((string) ($rule['match_value'] ?? ''));
                $canonical = $match === ''
                    ? ''
                    : $phoneNumbers->dialplanMatchForDomain(
                        $match,
                        session('domain_uuid')
                    )['canonical'];

                if ($canonical !== '' && in_array($canonical, $matches, true)) {
                    $validator->errors()->add("rules.{$index}.match_value", __('Each match value must be unique.'));
                }
                $matches[] = $canonical;
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $rules = collect($this->input('rules', []))
            ->map(function ($rule) {
                $rule['match_value'] = trim((string) ($rule['match_value'] ?? ''));

                return $rule;
            })
            ->values()
            ->all();

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'extension' => trim((string) $this->input('extension')),
            'source' => $this->input('source', DynamicRoute::SOURCE_CALLER_DESTINATION),
            'enabled' => filter_var($this->input('enabled', true), FILTER_VALIDATE_BOOLEAN),
            'rules' => $rules,
        ]);
    }

    protected function dynamicRouteUuid(): ?string
    {
        return null;
    }

    private function validateDestination(Validator $validator, string $type, mixed $target, string $key): void
    {
        if (in_array($type, DynamicRouteService::DESTINATION_TYPES_WITHOUT_TARGET, true)) {
            return;
        }

        if (is_array($target)) {
            $target = $target['bridge_uuid'] ?? $target['extension'] ?? $target['value'] ?? null;
        }

        if (! filled($target)) {
            $validator->errors()->add($key, __('Choose a destination.'));
        }
    }
}
