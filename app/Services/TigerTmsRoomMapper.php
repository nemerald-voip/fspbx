<?php

namespace App\Services;

use App\Models\HotelRoom;

class TigerTmsRoomMapper
{
    public function outbound(HotelRoom $room): string
    {
        return $this->normalize((string) $room->room_name);
    }

    public function normalize(string $room): string
    {
        $room = trim($room);

        if (preg_match('/^(room|rm)\s+(.+)$/i', $room, $matches)) {
            return trim($matches[2]);
        }

        return $room;
    }
}
