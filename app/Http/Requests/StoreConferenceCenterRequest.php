<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Foundation\Http\FormRequest;

class StoreConferenceCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('conference_center_add');
    }

    public function rules(): array
    {
        return [
            'conference_center_name' => ['required', 'string', 'max:255'],
            'conference_center_extension' => ['required', 'string', 'max:255', new UniqueExtension($this->conferenceCenterUuid())],
            'conference_center_greeting' => ['nullable', 'string', 'max:1024'],
            'conference_center_pin_length' => ['required', 'integer', 'min:0', 'max:32'],
            'conference_center_enabled' => ['required', 'in:true,false'],
            'conference_center_description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'conference_center_greeting' => blank($this->input('conference_center_greeting')) ? null : $this->input('conference_center_greeting'),
            'conference_center_pin_length' => $this->input('conference_center_pin_length', 9),
            'conference_center_enabled' => $this->input('conference_center_enabled', 'true'),
        ]);
    }

    protected function conferenceCenterUuid(): ?string
    {
        return null;
    }
}
