<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreDeviceKeyTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && userCheckPermission('device_key_template_create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'enabled' => ['required', Rule::in(['true', 'false'])],
            'keys' => ['nullable', 'array'],
            'keys.*.key_area' => ['nullable', 'string', Rule::in(['main', 'side', 'multi_purpose', 'expansion'])],
            'keys.*.key_index' => ['required', 'integer', 'min:1'],
            'keys.*.key_type' => ['nullable', 'string', 'max:50'],
            'keys.*.key_value' => ['nullable', 'string', 'max:64'],
            'keys.*.key_label' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $keys = $this->input('keys');

            if (! is_array($keys)) {
                return;
            }

            $indexes = [];
            foreach ($keys as $i => $key) {
                $idx = $key['key_index'] ?? null;
                $area = $key['key_area'] ?? 'main';

                if ($idx === null) {
                    continue;
                }

                $composite = $area . ':' . $idx;
                if (isset($indexes[$composite])) {
                    $validator->errors()->add("keys.{$i}.key_index", 'Duplicate key.');
                    continue;
                }

                $indexes[$composite] = true;
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('enabled')) {
            $this->merge(['enabled' => 'true']);
        }
    }
}
