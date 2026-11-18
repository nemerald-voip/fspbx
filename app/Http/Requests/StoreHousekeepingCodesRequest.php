<?php

namespace App\Http\Requests;

use App\Services\HotelPortalAccess;
use App\Support\Localization\ValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class StoreHousekeepingCodesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return HotelPortalAccess::allows('hotel_housekeeping_manage');
    }

    public function rules(): array
    {
        return [
            'housekeeping_options' => ['present', 'array'],
            'housekeeping_options.*.code' => ['required', 'integer', 'between:0,99', 'distinct'],
            'housekeeping_options.*.label' => ['required', 'string', 'max:64'],
            'housekeeping_options.*.enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ValidationMessages::common();
    }

    public function attributes(): array
    {
        return [
            'housekeeping_options' => __('Housekeeping Codes'),
            'housekeeping_options.*.code' => __('Status code'),
            'housekeeping_options.*.label' => __('Label'),
            'housekeeping_options.*.enabled' => __('Enabled'),
        ];
    }
}
