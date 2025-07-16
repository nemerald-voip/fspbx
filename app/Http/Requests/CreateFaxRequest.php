<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Foundation\Http\FormRequest;

class CreateFaxRequest extends FormRequest
{
    public function authorize()
    {
        return userCheckPermission('fax_add');
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
            'domain_uuid' => ['required'],
            'accountcode' => ['required'],
            'fax_destination_number' => ['present'],
            'authorized_domains' => ['nullable', 'array'],
            'authorized_emails' => ['nullable', 'array'],
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

        if (!$this->has('domain_uuid')) {
            $this->merge(['domain_uuid' => session('domain_uuid')]);
        }

        if (!$this->has('accountcode')) {
            $this->merge(['accountcode' => session('domain_name')]);
        }

        if (!$this->has('fax_destination_number')) {
            $this->merge(['fax_destination_number' => $this->input('fax_extension')]);
        }

    }
}
