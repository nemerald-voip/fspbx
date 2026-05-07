<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Foundation\Http\FormRequest;

class CreateNewFaxRequest extends FormRequest
{
    public function authorize()
    {
        return userCheckPermission('fax_send');
    }

    public function rules()
    {
        return [
            'recipient'     => ['required', 'string', 'max:60'],
            'send_confirmation' => ['string'],
            'fax_message' => ['nullable','string'],
            'sender_fax_number' => ['required', 'string', 'max:60'],
            'cover_letter' => ['nullable'],
            'files'             => ['required', 'array', 'min:1'],
            'files.*'           => [
                'file',
                'mimes:pdf,doc,docx,rtf,xls,xlsx,csv,txt,tif,tiff,jpg,jpeg', // allowed file types
                'max:20480', // max size in KB (e.g., 20MB)
            ],
        ];
    }


    public function messages()
    {
        return [
            // 'directory_first_name.required' => 'The first name field is required.',
            'files.required' => 'You must select at least one file.',
            // You can add more custom messages here as needed
        ];
    }

    public function prepareForValidation()
    {
        $recipient = $this->input('recipient');

        $this->merge([
            'recipient' => is_string($recipient)
                ? preg_replace('/\D+/', '', trim($recipient))
                : $recipient,
            'sender_fax_number' => is_string($this->input('sender_fax_number'))
                ? trim($this->input('sender_fax_number'))
                : $this->input('sender_fax_number'),
            'fax_message' => is_string($this->input('fax_message'))
                ? trim($this->input('fax_message'))
                : $this->input('fax_message'),
        ]);

    }
}
