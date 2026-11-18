<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BusinessHoursItemOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission($this->filled('item_uuid')
            ? 'business_hours_update'
            : 'business_hours_create');
    }

    public function rules(): array
    {
        return ['item_uuid' => ['nullable', 'uuid']];
    }
}
