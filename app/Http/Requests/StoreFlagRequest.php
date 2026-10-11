<?php

namespace App\Http\Requests;

use App\Models\Flag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('flags_create');
    }

    public function rules(): array
    {
        $domainUuid = session('domain_uuid');

        return [
            'name' => ['required', 'string', 'max:255'],
            'flag_number' => [
                'required',
                'regex:/^\d{1,10}$/',
                Rule::unique('flags', 'flag_number')
                    ->where('domain_uuid', $domainUuid)
                    ->ignore($this->flagUuid(), 'uuid'),
            ],
            'type' => ['required', Rule::in([Flag::TYPE_MANUAL, Flag::TYPE_SCHEDULED])],
            'timezone' => ['nullable', 'required_if:type,' . Flag::TYPE_SCHEDULED, 'timezone:all'],
            'enabled' => ['boolean'],
            'active' => ['boolean'],
            'pin_number' => ['nullable', 'regex:/^\d{1,20}$/'],
            'blf_enabled' => ['boolean'],
            'description' => ['nullable', 'string', 'max:255'],

            'allowed_extensions' => ['nullable', 'array'],
            'allowed_extensions.*' => [
                'uuid',
                Rule::exists('v_extensions', 'extension_uuid')->where('domain_uuid', $domainUuid),
            ],

            'weekly_rules' => ['nullable', 'array'],
            'weekly_rules.*.weekdays' => ['required', 'array', 'min:1'],
            'weekly_rules.*.weekdays.*' => ['integer', 'between:1,7'],
            'weekly_rules.*.time_from' => ['required', 'date_format:H:i'],
            'weekly_rules.*.time_to' => ['required', 'date_format:H:i', 'different:weekly_rules.*.time_from'],

            'date_rules' => ['nullable', 'array'],
            'date_rules.*.start_date' => ['required', 'date_format:Y-m-d'],
            'date_rules.*.end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_rules.*.start_date'],
            'date_rules.*.time_from' => ['nullable', 'date_format:H:i'],
            'date_rules.*.time_to' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'flag_number.regex' => __('The flag number must contain only digits.'),
            'flag_number.unique' => __('Another flag already uses this number.'),
            'timezone.required_if' => __('Choose a time zone for a scheduled flag.'),
            'pin_number.regex' => __('The PIN must contain only digits.'),
            'weekly_rules.*.weekdays.required' => __('Please select at least one day'),
            'weekly_rules.*.weekdays.min' => __('Please select at least one day'),
            'weekly_rules.*.time_from.required' => __('The from field is required'),
            'weekly_rules.*.time_to.required' => __('The to field is required'),
            'weekly_rules.*.time_to.different' => __('The start and end times must be different.'),
            'date_rules.*.start_date.required' => __('The start date is required.'),
            'date_rules.*.end_date.after_or_equal' => __('The end date must be on or after the start date.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('type') !== Flag::TYPE_SCHEDULED) {
                return;
            }

            if (empty($this->input('weekly_rules')) && empty($this->input('date_rules'))) {
                $validator->errors()->add('weekly_rules', __('Add at least one weekly time or date range.'));
            }

            foreach ((array) $this->input('date_rules', []) as $index => $row) {
                $startDate = $row['start_date'] ?? null;
                $endDate = ($row['end_date'] ?? null) ?: $startDate;
                $from = $row['time_from'] ?? null;
                $to = $row['time_to'] ?? null;

                if ($startDate && $startDate === $endDate && $from && $to && $to <= $from) {
                    $validator->errors()->add("date_rules.{$index}.time_to", __('The end time must be after the start time.'));
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'flag_number' => trim((string) $this->input('flag_number')),
            'pin_number' => filled($this->input('pin_number')) ? trim((string) $this->input('pin_number')) : null,
            'weekly_rules' => array_values((array) $this->input('weekly_rules', [])),
            'date_rules' => array_values(array_map(function ($row) {
                $row = (array) $row;
                foreach (['end_date', 'time_from', 'time_to'] as $key) {
                    if (array_key_exists($key, $row) && blank($row[$key])) {
                        $row[$key] = null;
                    }
                }

                return $row;
            }, (array) $this->input('date_rules', []))),
        ]);
    }

    protected function flagUuid(): ?string
    {
        return null;
    }
}
