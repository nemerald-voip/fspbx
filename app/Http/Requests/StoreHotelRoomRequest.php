<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHotelRoomRequest extends FormRequest
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
            'extension_uuid.exists' => 'Selected extension does not exist',
        ];
    }

    public function attributes(): array
    {
        return [
            'room_name' => 'room name',
            'extension_uuid' => 'extension',
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
