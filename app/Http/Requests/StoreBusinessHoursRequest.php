<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use App\Rules\UniqueExtension;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessHoursRequest extends FormRequest
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

            'extension' => [
                'required',
                'numeric',
                new UniqueExtension(),
            ],

            // top-level slots array
            'time_slots'                 => ['sometimes','required', 'array'],
            'time_slots.*.weekdays'      => ['required', 'array'],
            'time_slots.*.weekdays.*'    => ['integer', 'in:0,1,2,3,4,5,6'],

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

        ];
    }

    public function messages(): array
    {
        return [
            // 'ring_group_extension.ring_group_unique' => 'This number is already used',

        ];
    }

    public function prepareForValidation(): void
    {
        // logger($this);


    }

}
