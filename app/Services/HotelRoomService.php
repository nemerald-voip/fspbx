<?php
namespace App\Services;

use App\Models\HotelRoom;
use App\Models\HotelReservation;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Facades\DB;

class HotelRoomService {
    public function __construct(private FreeswitchEslService $esl) {}

    public function checkIn(HotelRoom $room, array $payload): void {
        DB::transaction(function() use ($room, $payload) {
            $room->update([
                'occupancy_status' => 'occupied',
                'guest_first_name' => $payload['guest_first_name'],
                'guest_last_name'  => $payload['guest_last_name'],
                'dnd' => false, 'message_waiting'=>false,
            ]);

            // Create/attach reservation if provided
            if (!empty($payload['reservation_id'])) {
                HotelReservation::where('id',$payload['reservation_id'])
                    ->where('domain_uuid',$room->domain_uuid)
                    ->update(['hotel_room_id'=>$room->id,'checked_in_at'=>now()]);
            } else {
                HotelReservation::create([
                    'domain_uuid'=>$room->domain_uuid,
                    'hotel_room_id'=>$room->id,
                    'guest_first_name'=>$payload['guest_first_name'],
                    'guest_last_name'=>$payload['guest_last_name'],
                    'arrival_date'=>now()->toDateString(),
                    'departure_date'=>$payload['departure_date'],
                    'checked_in_at'=>now(),
                ]);
            }
        });

        // PBX side effects (adjust to your dialplan):
        if ($room->extension_uuid) {
            // example: set caller-id name to "LAST, FIRST (Rm 123)"
            $name = sprintf('%s, %s (Rm %s)',
                $room->guest_last_name, $room->guest_first_name, $room->room_number);

            $this->esl->executeCommand('uuid_setvar_multi', [
                'uuid' => $room->extension_uuid, // or set vars by directory auth
                'data' => "effective_caller_id_name=$name"
            ]);

            // clear voicemail + MWI, reset PIN (make your own helper)
            // $this->resetVoicemail($room);

            // set class-of-service / dialplan vars for guests if you use CoS
            // $this->applyClassOfService($room,'guest');
        }
    }

    public function checkOut(HotelRoom $room): void {
        DB::transaction(function() use ($room) {
            // Close active reservation
            HotelReservation::where('hotel_room_id',$room->id)
                ->whereNull('checked_out_at')
                ->update(['checked_out_at'=>now()]);

            $room->update([
                'occupancy_status'=>'vacant',
                'housekeeping_status'=>'dirty', // typical
                'guest_first_name'=>null,
                'guest_last_name'=>null,
                'dnd'=>false,
                'message_waiting'=>false,
            ]);
        });

        if ($room->extension_uuid) {
            // revert caller-id name to room label, remove guest CoS, clear VM
            // $this->applyClassOfService($room,'default');
            // $this->clearCallerId($room);
        }
    }

    public function applyDndToExtension(HotelRoom $room): void {
        if (!$room->extension_uuid) return;
        // Depending on your design, you can set a user/dir var or a channel var.
        // Common pattern: set directory param "call_screen_dnd" or custom var that dialplan reads.
        $flag = $room->dnd ? 'true' : 'false';
        $this->esl->executeCommand('bgapi', [
            'command' => "fsctl loglevel notice" // placeholder; replace with your DND sync action
        ]);
    }

}
