<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExtensionRequest extends FormRequest
{
    public function authorize()
    {
        return userCheckPermission('extension_edit');
    }

    public function rules()
    {
        return [
            'directory_first_name'     => ['required', 'string', 'max:60'],
            'directory_last_name'      => ['nullable', 'string', 'max:60'],
            'effective_caller_id_name' => ['present'],
            'effective_caller_id_number' => ['present'],
            'extension' => [
                'required',
                'numeric',
                new UniqueExtension($this->input('extension_uuid') ?? null),
            ],            
            'voicemail_mail_to'               => ['nullable', 'email', 'max:255'],
            'description'              => ['nullable', 'string'],
            'suspended'                => ['boolean'],
            'enabled'                  => ['required', 'in:true,false,1,0'],
            'directory_visible'        => ['required', 'in:true,false,1,0'],
            'directory_exten_visible'  => ['required', 'in:true,false,1,0'],
            'outbound_caller_id_number' => ['nullable', 'string'],
            'emergency_caller_id_number' => ['nullable', 'string'],
        ];
    }

    public function messages()
    {
        return [
            'directory_first_name.required' => 'The first name field is required.',
            // You can add more custom messages here as needed
        ];
    }

    public function prepareForValidation()
    {
        $first = $this->input('directory_first_name', '');
        $last = $this->input('directory_last_name', '');

        $fullName = trim($first . ' ' . $last); // Will work even if $last is empty

        $this->merge([
            'effective_caller_id_name' => $fullName,
        ]);

        $this->merge([
            'effective_caller_id_number' => $this->extension,
        ]);
    }
}
