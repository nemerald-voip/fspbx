<?php

namespace App\Http\Requests;

use App\Services\HotelPortalAccess;

class UpdateHotelRoomRequest extends StoreHotelRoomRequest
{
    public function authorize(): bool
    {
        return HotelPortalAccess::allows('hotel_rooms_update');
    }
}
