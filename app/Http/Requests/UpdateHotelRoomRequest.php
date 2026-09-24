<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Validation\Rule;

class UpdateHotelRoomRequest extends FormRequest
{

    public function authorize(): bool
    {
        // If you gate by permission, swap this with: return $this->user()->can('hotel_rooms_update');
        return true;
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
            'domain_uuid' => 'present',
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
            'domain_uuid' => __('Account'),
        ];
    }

    public function prepareForValidation(): void
    {
        // Default domain
        if (!$this->has('domain_uuid')) {
            $this->merge(['domain_uuid' => session('domain_uuid')]);
        }
    }
}
