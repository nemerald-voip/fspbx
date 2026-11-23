<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DuplicateBusinessHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('business_hours_create');
    }

    public function rules(): array
    {
        return ['uuid' => ['required', 'uuid']];
    }
}
