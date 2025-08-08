<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only allow if user is admin or has permission (customize as needed)
        return userCheckPermission('location_update');
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:255',
            'description'    => 'nullable|string|max:255',
        ];
    }

    public function prepareForValidation(): void
    {
    }
}
