<?php

namespace App\Http\Requests;

use App\Services\HotelPortalAccess;
use App\Services\PmsProviderSettings;
use App\Support\Localization\ValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHotelPmsProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return HotelPortalAccess::allows('hotel_pms_provider_manage') && userCheckPermission('account_settings_list_view');
    }

    public function rules(): array
    {
        return ['pms_provider' => ['required', 'string', Rule::in([PmsProviderSettings::CHARPMS, PmsProviderSettings::TIGERTMS])]];
    }

    public function messages(): array
    {
        return ValidationMessages::common();
    }

    public function attributes(): array
    {
        return ['pms_provider' => __('Provider')];
    }
}
