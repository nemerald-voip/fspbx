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
                'required_without:greeting_recorded_file',
                'nullable',
                'max:10000',
                'mimes:wav'
            ],
            'greeting_recorded_file' => [
                'required_without:greeting_filename',
                'nullable',
                'string'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'greeting_name.required' => 'Greeting name is required',
            'greeting_filename.required_without' => 'Filename is required',
            'greeting_recorded_file.required_without' => 'Recording is required',
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
        $this->merge([
            'greeting_name' => 'Recording '.date('d/m/y H:i A'),
            'greeting_description' => null
        ]);
    }
}
