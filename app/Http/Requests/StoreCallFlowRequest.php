<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCallFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('call_flow_add');
    }

    public function rules(): array
    {
        return [
            'call_flow_name' => ['required', 'string', 'max:255'],
            'call_flow_extension' => ['required', 'string', 'max:255', new UniqueExtension($this->callFlowUuid())],
            'call_flow_feature_code' => ['nullable', 'string', 'max:255'],
            'call_flow_status' => ['required', 'in:true,false'],
            'call_flow_pin_number' => ['nullable', 'string', 'max:255'],
            'call_flow_label' => ['nullable', 'string', 'max:255'],
            'call_flow_sound' => ['nullable', 'string', 'max:255'],
            'call_flow_action' => ['nullable', 'string', 'max:255'],
            'call_flow_target' => ['nullable'],
            'call_flow_destination' => ['nullable', 'string', 'max:1024'],
            'call_flow_alternate_label' => ['nullable', 'string', 'max:255'],
            'call_flow_alternate_sound' => ['nullable', 'string', 'max:255'],
            'call_flow_alternate_action' => ['nullable', 'string', 'max:255'],
            'call_flow_alternate_target' => ['nullable'],
            'call_flow_alternate_destination' => ['nullable', 'string', 'max:1024'],
            'call_flow_enabled' => ['required', 'in:true,false'],
            'call_flow_group' => ['nullable', 'string', 'max:255'],
            'call_flow_description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateRoute(
                $validator,
                'call_flow_action',
                'call_flow_target',
                'call_flow_destination',
                true
            );

            $this->validateRoute(
                $validator,
                'call_flow_alternate_action',
                'call_flow_alternate_target',
                'call_flow_alternate_destination',
                false
            );
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('call_flow_group')) {
            $group = trim((string) $this->input('call_flow_group'));

            $this->merge([
                'call_flow_group' => $group !== '' ? $group : null,
            ]);
        }
    }

    protected function callFlowUuid(): ?string
    {
        return null;
    }

    protected function validateRoute(
        Validator $validator,
        string $actionKey,
        string $targetKey,
        string $legacyDestinationKey,
        bool $required
    ): void {
        $action = $this->input($actionKey);
        $target = $this->input($targetKey);
        $legacyDestination = $this->input($legacyDestinationKey);

        if (!filled($action) && !filled($legacyDestination)) {
            if ($required) {
                $validator->errors()->add($actionKey, 'Choose a default destination.');
            }

            return;
        }

        if (filled($action) && $this->requiresTarget($action) && !$this->hasRoutingTarget($target)) {
            $validator->errors()->add($targetKey, 'Choose a destination.');
        }
    }

    protected function requiresTarget(string $action): bool
    {
        return !in_array($action, [
            'check_voicemail',
            'company_directory',
            'hangup',
        ], true);
    }

    protected function hasRoutingTarget(mixed $target): bool
    {
        if (is_array($target)) {
            $target = $target['extension'] ?? $target['value'] ?? null;
        }

        return trim((string) $target) !== '';
    }
}
