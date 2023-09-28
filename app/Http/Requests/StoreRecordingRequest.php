<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreRecordingRequest extends FormRequest
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
            'greeting_name' => [
                'required',
                'string',
            ],
            'greeting_description' => [
                'nullable',
                'string'
            ],
            'greeting_filename' => [
                'required_if:greeting_recorded_file,==,nullable',
                'nullable',
                'max:10000',
                'mimes:wav'
            ],
            'greeting_recorded_file' => [
                'nullable',
                'string'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'greeting_name.required' => 'Greeting name is required',
            'greeting_filename.required' => 'Filename is required',
            'greeting_filename.mimes' => 'Only wav files allowed'
        ];
    }

    public function prepareForValidation()
    {
        if ($this->get('greeting_filename') == 'undefined') {
            $this->merge([
                'greeting_filename' => null
            ]);
        };
    }
}
