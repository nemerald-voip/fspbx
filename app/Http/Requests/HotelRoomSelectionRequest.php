<?php

namespace App\Http\Requests;

use App\Services\HotelPortalAccess;
use Illuminate\Foundation\Http\FormRequest;

class HotelRoomSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ($this->route()->getName()) {
            'hotel-rooms.bulk.delete' => HotelPortalAccess::allows('hotel_rooms_delete'),
            'hotel-room-status.bulk.delete' => HotelPortalAccess::allows('hotel_room_status_update'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
