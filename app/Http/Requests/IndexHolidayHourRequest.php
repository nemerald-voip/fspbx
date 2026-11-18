<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexHolidayHourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('business_hours_holidays_list_view');
    }

    public function rules(): array
    {
        return ['uuid' => ['required', 'uuid']];
    }
}
