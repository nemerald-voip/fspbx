<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HotelRoomCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // tighten if you have policies/guards
    }

    public function rules(): array
    {
        return [
            // NOTE: payload 'uuid' is the HotelRoom UUID
            'uuid'               => ['required', 'uuid', 'exists:hotel_rooms,uuid'],
            'occupancy_status'   => ['nullable', 'string', 'max:50'],
            'housekeeping_status'=> ['nullable', 'uuid'],
            'guest_first_name'   => ['nullable', 'string', 'max:120'],
            'guest_last_name'    => ['nullable', 'string', 'max:120'],
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
    }
}
