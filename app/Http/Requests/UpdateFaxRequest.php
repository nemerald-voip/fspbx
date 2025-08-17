<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFaxRequest extends FormRequest
{
    public function authorize()
    {
        return userCheckPermission('fax_edit');
    }

    public function rules()
    {
        return [
            'fax_name'     => ['required', 'string', 'max:60'],
            'fax_caller_id_name' => ['present'],
            'fax_caller_id_number' => ['required'],
            'fax_extension' => [
                'required',
                'numeric',
                new UniqueExtension($this->input('fax_uuid') ?? null),
            ],
            'fax_description'              => ['nullable', 'string'],
            'fax_forward_number'              => ['nullable', 'string'],
            'fax_prefix'              => ['nullable', 'numeric'],
            'fax_toll_allow'              => ['nullable', 'string'],
            'fax_send_channels'              => ['nullable', 'numeric'],
            'fax_email' => ['nullable', 'string'],
            'authorized_domains' => ['nullable', 'array'],
            'authorized_emails' => ['nullable', 'array'],
            'locations' => 'nullable|array',
        ];
    }


    public function messages()
    {
        return [
            // 'directory_first_name.required' => 'The first name field is required.',
            'fax_send_channels.numeric' => 'The number of channels value must be a number.',
            'fax_prefix.numeric' => 'The prefix must be a number.',
            'fax_caller_id_number.required' => 'The caller id number field is required.',

            // You can add more custom messages here as needed
        ];
    }

    public function prepareForValidation()
    {

        // logger($this);

    }
}
