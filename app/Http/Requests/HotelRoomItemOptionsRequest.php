<?php

namespace App\Http\Requests;

use App\Services\HotelPortalAccess;
use Illuminate\Foundation\Http\FormRequest;

class HotelRoomItemOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->routeIs('hotel-room-status.item.options')) {
            return HotelPortalAccess::allows('hotel_room_status_update') && userCheckPermission('hotel_room_status_list_view');
        }

        return HotelPortalAccess::allows($this->filled('item_uuid') ? 'hotel_rooms_update' : 'hotel_rooms_create');
    }

    public function rules(): array
    {
        return ['item_uuid' => ['nullable', 'uuid']];
    }
}
