<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use App\Rules\UniqueExtension;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessHoursRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'custom_hours'              => ['present'],
            'name' => [
                'required',
                'string',
            ],

            'extension' => [
                'required',
                'numeric',
                new UniqueExtension(),
            ],

            'timezone' => [
                'required',
                'string',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            // top-level slots array
            'time_slots'                 => ['sometimes', 'required', 'array'],
            'time_slots.*.weekdays'      => ['required', 'array'],
            'time_slots.*.weekdays.*'    => ['integer', 'in:1,2,3,4,5,6,7'],

            // times must be 12-hour format with am/pm
            'time_slots.*.time_from'     => ['required', 'date_format:h:i a'],
            'time_slots.*.time_to'       => [
                'required',
                'date_format:h:i a',
                // ensure end is after start
                function ($attribute, $value, $fail) {
                    // attribute comes in as "time_slots.0.time_to"
                    $parts = explode('.', $attribute);
                    $idx   = $parts[1];
                    $from  = $this->input("time_slots.{$idx}.time_from");

                    try {
                        $start = Carbon::createFromFormat('h:i a', $from);
                        $end   = Carbon::createFromFormat('h:i a', $value);
                    } catch (\Exception $e) {
                        return $fail("Invalid time format in slot #{$idx}.");
                    }

                    if ($end->lte($start)) {
                        $fail("End time must be after the start time in slot #{$idx}.");
                    }
                },
            ],


            'time_slots.*.action' => [
                'sometimes',
                'required',
            ],

            'time_slots.*.target' => [
                'sometimes',
                function ($attribute, $value, $fail) {
                    $action = $this->input('time_slots.*.action');

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

            'after_hours_action' => [
                'sometimes',
                'required',
            ],

            'after_hours_target' => [
                'sometimes',
                function ($attribute, $value, $fail) {
                    $action = $this->input('after_hours_action');

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

        ];
    }

    public function messages(): array
    {
        return [
            // 'ring_group_extension.ring_group_unique' => 'This number is already used',
            'time_slots.*.time_from.required' => 'The from field is required',
            'time_slots.*.time_to.required' => 'The to field is required',
            'time_slots.*.weekdays.required' => 'Please select at least one day',
            'time_slots.*.action.required' => 'The action field is required',
        ];
    }

    public function prepareForValidation(): void
    {
        // logger($this);


    }
}
