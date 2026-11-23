<?php

namespace App\Http\Requests;

use App\Services\HotelPortalAccess;
use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Validation\Rule;

class BulkStoreHotelRoomsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return HotelPortalAccess::allows('hotel_rooms_create');
    }

    public function rules(): array
    {
        $domainUuid = (string) session('domain_uuid');

        return [
            'extensions' => ['required', 'array', 'min:1'],
            'extensions.*' => [
                'required', 'uuid', 'distinct',
                // must exist in v_extensions in the SAME domain
                Rule::exists('v_extensions', 'extension_uuid')
                    ->where(fn ($q) => $q->where('domain_uuid', $domainUuid)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            ...ValidationMessages::common(),
            'extensions.required' => __('Please select at least one extension.'),
            'extensions.*.exists' => __('One or more selected extensions do not exist in this domain.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'extensions' => __('Extensions'),
            'extensions.*' => __('Extension'),
        ];
    }

}
