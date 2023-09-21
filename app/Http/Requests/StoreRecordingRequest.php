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
                'required',
                'max:10000',
                'mimes:wav'
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
}
