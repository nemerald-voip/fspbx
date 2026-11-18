<?php

namespace App\Http\Requests;

use App\Services\HotelPortalAccess;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;

class HotelRoomCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return HotelPortalAccess::allows('hotel_room_status_update');
    }

    public function rules(): array
    {
        return [
            // NOTE: payload 'uuid' is the HotelRoom UUID
            'uuid'               => ['required', 'uuid'],
            'occupancy_status'   => ['nullable', 'string', 'max:50'],
            'housekeeping_status'=> ['nullable', 'uuid', Rule::exists('hotel_housekeeping_definitions', 'uuid')
                ->where('domain_uuid', session('domain_uuid'))->where('enabled', true)],
            'guest_first_name'   => ['nullable', 'string', 'max:120'],
            'guest_last_name'    => ['nullable', 'string', 'max:120'],
            'extension_name'    => ['nullable', 'string', 'max:120'],
            'arrival_date'       => ['nullable', 'date'],
            'departure_date'     => ['nullable', 'date', 'after_or_equal:arrival_date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'guest_first_name' => $this->filled('guest_first_name') ? trim((string) $this->input('guest_first_name')) : null,
            'guest_last_name'  => $this->filled('guest_last_name')  ? trim((string) $this->input('guest_last_name'))  : null,
        ]);

        if (!$this->has('extension_name')) {
            $this->merge(['extension_name' => trim($this->input('guest_first_name') . ' ' . $this->input('guest_last_name')) ]);
        }
    }

    public function messages(): array
    {
        return ValidationMessages::common();
    }

    public function attributes(): array
    {
        return [
            'uuid' => __('Unique ID'),
            'occupancy_status' => __('Occupancy'),
            'housekeeping_status' => __('Room Status'),
            'guest_first_name' => __('First Name'),
            'guest_last_name' => __('Last Name'),
            'extension_name' => __('Extension'),
            'arrival_date' => __('Arrival Date'),
            'departure_date' => __('Expected Departure Date'),
        ];
    }
}
