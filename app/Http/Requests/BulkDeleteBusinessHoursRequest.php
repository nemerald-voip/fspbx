<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteBusinessHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('business_hours_delete');
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
