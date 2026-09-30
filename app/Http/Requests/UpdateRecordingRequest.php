<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Support\Facades\Auth;

class UpdateRecordingRequest extends FormRequest
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
            ]
        ];
    }

    public function messages(): array
    {
        return [
            ...ValidationMessages::common(),
            'greeting_name.required' => __('Greeting name is required')
        ];
    }

    public function attributes(): array
    {
        return [
            'greeting_name' => __('Name'),
            'greeting_description' => __('Description'),
        ];
    }
}
