<?php
namespace App\Services\Inerfaces;

use App\Models\HotelRoom;

interface PmsAdapter {
    public function onReservationCreated(array $payload): void;
    public function onCheckIn(HotelRoom $room, array $payload): void;
    public function onCheckOut(HotelRoom $room): void;
    public function onDndChanged(HotelRoom $room, bool $dnd): void;
    public function postMinibarCharge(HotelRoom $room, array $items): void;
}
