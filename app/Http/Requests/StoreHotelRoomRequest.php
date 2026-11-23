<?php

namespace App\Http\Requests;

use App\Services\HotelPortalAccess;
use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Validation\Rule;

class StoreHotelRoomRequest extends FormRequest
{

    public function authorize(): bool
    {
        return HotelPortalAccess::allows('hotel_rooms_create');
    }


    public function rules(): array
    {
        $domainUuid = (string) session('domain_uuid');

        return [
            'room_name' => [
                'required','string','max:32',
            ],

            'extension_uuid' => [
                'nullable','uuid',
                // Limit to same domain
                Rule::exists('v_extensions', 'extension_uuid')
                    ->where(fn ($q) => $q->where('domain_uuid', $domainUuid)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            ...ValidationMessages::common(),
            'extension_uuid.exists' => __('Selected extension does not exist'),
        ];
    }

    public function attributes(): array
    {
        return [
            'room_name' => __('Room Name'),
            'extension_uuid' => __('Extension'),
        ];
    }

}
