<?php

namespace App\Http\Requests;

use App\Models\Destinations;
use App\Services\CallRoutingOptionsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportPhoneNumbersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('destination_import');
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');
        if (! is_array($items)) {
            return;
        }
        foreach ($items as &$row) {
            foreach (['destination_number', 'destination_prefix', 'routing_extension'] as $field) {
                if (is_array($row) && is_int($row[$field] ?? null)) {
                    $row[$field] = (string) $row[$field];
                }
            }
        }
        unset($row);
        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'array'],
            'items.*.destination_number' => ['required', 'string'],
            'items.*.destination_prefix' => ['nullable', 'string'],
            'items.*.destination_description' => ['nullable', 'string'],
            'items.*.routing_type' => ['nullable', 'string', Rule::in(CallRoutingOptionsService::destinationTypes())],
            'items.*.routing_extension' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $numbers = collect($this->input('items'))->pluck('destination_number');
            $duplicates = $numbers->duplicates()->unique();
            if ($duplicates->isNotEmpty()) {
                $validator->errors()->add('server', __('The import list contains duplicate numbers: :numbers', ['numbers' => $duplicates->implode(', ')]));
                return;
            }
            $existing = Destinations::whereIn('destination_number', $numbers)->pluck('destination_number');
            if ($existing->isNotEmpty()) {
                $validator->errors()->add('server', __('The following numbers already exist in the system: :numbers', ['numbers' => $existing->implode(', ')]));
            }
        });
    }
}
