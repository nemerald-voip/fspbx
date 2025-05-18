<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHolidayHourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $types = [
            'us_holiday',
            'ca_holiday',
            'single_date',
            'date_range',
            'recurring_pattern',
        ];

        return [
            'business_hour_uuid' => 'present',
            // which kind of holiday
            'holiday_type'     => ['required', Rule::in($types)],

            'description'     => ['required', 'string'],

            // Date fields
            'start_date'       => ['nullable', 'date', 'required_if:holiday_type,single_date,date_range'],
            'end_date'         => [
                'nullable',
                'date',
                'required_if:holiday_type,date_range',
                'after_or_equal:start_date',
            ],

            // Time-of-day slices (optional)
            'start_time'       => ['nullable', 'date_format:H:i', 'required_with:end_time'],
            'end_time'         => ['nullable', 'date_format:H:i', 'required_with:start_time'],

            // Month/day/week‐of‐month for US holidays & recurring patterns
            'mon'              => ['nullable', 'string'],
            'mday'             => ['nullable', 'string'],
            'wday'             => ['nullable', 'string'],
            'mweek'            => ['nullable', 'string'],
            'week'            => ['nullable', 'string'],

            // routing action + target
            'action' => [
                'required',
            ],

            'target' => [
                'sometimes',
                function ($attribute, $value, $fail) {
                    $action = $this->input('action');

                    // if an action *needs* a target (i.e. it is NOT one of these),
                    // then failback_target cannot be empty
                    if (
                        $action
                        && ! in_array($action, [
                            'company_directory',
                            'check_voicemail',
                            'hangup',
                        ], true)
                        && empty($value)
                    ) {
                        $fail('A target must be provided when action is selected.');
                    }
                },
            ],

            // free‐form note
            'note'             => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            // 'ring_group_extension.ring_group_unique' => 'This number is already used',
            'start_date.required_if' => 'The date field is required',
            'end_date.required_if' => 'The date field is required',

        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $type = $this->input('holiday_type');
            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date');
            $startTime = $this->input('start_time');
            $endTime = $this->input('end_time');

            // US holiday must be fixed‐date (mday) OR floating (wday + mweek)
            if ($type === 'us_holiday' || $type === 'ca_holiday') {
                $hasFixed    = filled($this->input('mday'));
                $hasFloating = filled($this->input('wday')) && filled($this->input('mweek'));

                if (! $hasFixed && ! $hasFloating) {
                    $validator->errors()->add(
                        'us_holiday',
                        'You must select a holiday from the list.'
                    );
                }
            }


            if ($type === 'recurring_pattern') {
                // list of possible recurrence columns
                $fields = ['mon', 'mday', 'wday', 'mweek', 'week'];

                // check if any of them is non-null / non-empty
                $hasAny = collect($fields)
                    ->contains(fn($f) => filled($this->input($f)));

                if (! $hasAny) {
                    $validator->errors()->add(
                        'holiday_type',
                        'To define a recurring pattern you must fill at least one of the condition fields in the form.'
                    );
                }
            }

            // single_date: force end_date = start_date
            if ($type === 'single_date' && $this->filled('start_date')) {
                if ($this->input('end_date') !== $this->input('start_date')) {
                    $validator->errors()->add(
                        'end_date',
                        'For a single-date holiday, end_date must equal start_date.'
                    );
                }
            }

            // Only check if both times and dates are present
            if ($startDate && $endDate && $startTime && $endTime) {
                // Only apply the after-or-equal rule if start_date == end_date
                if ($startDate === $endDate) {
                    // Compare as times (string compare is fine for HH:MM 24hr format)
                    if ($endTime < $startTime) {
                        $validator->errors()->add(
                            'end_time',
                            'The end time must be a time after or equal to start time.'
                        );
                    }
                }
            }
        });
    }


    protected function prepareForValidation(): void
    {
        if ($this->input('holiday_type') === 'single_date') {
            $this->merge([
                'end_date' => $this->input('start_date'),
            ]);
        }
    }
}
