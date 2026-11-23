<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! userCheckPermission('location_update')) {
            return false;
        }

        abort_unless($this->route('location')->domain_uuid === session('domain_uuid'), 404);

        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:255',
            'description'    => 'nullable|string|max:255',
            'domain_uuid' => ['sometimes', 'required', 'uuid', Rule::in([session('domain_uuid')])],
        ];
    }

    public function messages(): array
    {
        return ValidationMessages::common();
    }

    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'description' => __('Description'),
            'domain_uuid' => __('Account'),
        ];
    }
}
