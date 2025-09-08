<?php

namespace App\Services;

use App\Models\HotelRoom;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\HotelRoomStatus;
use App\Models\HotelReservation;
use Illuminate\Support\Facades\DB;
use App\Services\FreeswitchEslService;

class HotelRoomService
{
    // public function __construct(private FreeswitchEslService $esl) {}

    /**
     * Create a new status row for a guest check-in (no updates).
     *
     * @param  HotelRoom $room
     * @param  array     $payload  (validated request data)
     */
    public function checkIn(HotelRoom $room, array $payload): HotelRoomStatus
    {
        return DB::transaction(function () use ($room, $payload) {
            // only fields that truly belong to HotelRoomStatus (exclude 'uuid')
            $data = Arr::only($payload, [
                'occupancy_status',
                'housekeeping_status',
                'guest_first_name',
                'guest_last_name',
                'arrival_date',
                'departure_date',
            ]);

            $room->status()->delete();
    
            return HotelRoomStatus::create([
                'uuid'            => (string) Str::uuid(),   // new row every time
                'domain_uuid'     => $room->domain_uuid,     // derive (no session reliance)
                'hotel_room_uuid' => $room->uuid,
                ...$data,
            ]);
        });
    }

    /**
     * Create a new status row for a guest check-out (no updates).
     * Opinionated defaults: mark vacant & set departure_date=now() if not provided elsewhere.
     */
    public function checkOut(HotelRoom $room): bool
    {
        return DB::transaction(function () use ($room) {
            // lock the current status row (if any) to avoid race conditions
            $current = $room->status()->lockForUpdate()->first();
    
            if (!$current) {
                return false; // idempotent: nothing to delete
            }
    
            $current->delete();
    
            try {
                // Example ESL hook if you want to reflect DND/BLF, etc.
                // $this->esl->executeCommand("bgapi lua hotel_checkout.lua {$room->extension_uuid}");
            } catch (\Throwable $e) {
                logger('HotelRoomService@checkOut ESL error: '.$e->getMessage());
            }
    
            return true;
        });
    }
}
