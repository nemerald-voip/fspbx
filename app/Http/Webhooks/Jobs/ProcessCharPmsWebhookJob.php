<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\Extensions;
use App\Models\HotelRoom;
use App\Models\HotelRoomStatus;
use App\Services\HotelRoomService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;
use Carbon\Carbon;

class ProcessCharPmsWebhookJob extends SpatieProcessWebhookJob implements ShouldQueue
{
    use Queueable;

    public function handle()
    {
        $payload     = $this->webhookCall->payload;
        logger($this->webhookCall->payload);
        $action      = strtoupper((string) Arr::get($payload, 'action', ''));
        $domainUuid  = (string) Arr::get($payload, '_resolved_domain_uuid', '');
        $ext         = Arr::get($payload, 'extension_id');
        $dst         = Arr::get($payload, 'destination_id');

        /** @var HotelRoomService $svc */
        $svc = app(HotelRoomService::class);

        $findRoomByExt = function (string $domainUuid, string $ext) {
            $extensionUuid = Extensions::query()
                ->where('domain_uuid', $domainUuid)
                ->where('extension', $ext)
                ->value('extension_uuid');
            if (!$extensionUuid) return null;

            return HotelRoom::query()
                ->where('domain_uuid', $domainUuid)
                ->where('extension_uuid', $extensionUuid)
                ->first();
        };

        // ---- Safe parser: tries multiple formats, never throws ----
        $parseDate = function (?string $s): ?Carbon {
            if (!$s) return null;

            // Accept both "YYYY/MM/DDTHH:MM:SS" and "YYYYMMDDHHMMSS"
            $formats = ['Y/m/d\TH:i:s', 'YmdHis', 'Y-m-d\TH:i:s'];
            foreach ($formats as $fmt) {
                try {
                    $dt = Carbon::createFromFormat($fmt, $s);
                    if ($dt !== false) {
                        return $dt;
                    }
                } catch (\Throwable $e) {
                    // try next format
                }
            }

            // As a final guard, accept pure 14 digits if provided
            if (preg_match('/^\d{14}$/', $s)) {
                try {
                    return Carbon::createFromFormat('YmdHis', $s);
                } catch (\Throwable $e) {}
            }

            return null;
        };

        switch ($action) {
            case 'CHKI': { // Check-in
                $room = $findRoomByExt($domainUuid, (string)$ext);
                if (!$room) break;

                $arrival   = $parseDate(Arr::get($payload, 'arrival'));
                $departure = $parseDate(Arr::get($payload, 'departure'));

                $payloadForSvc = [
                    'guest_first_name'   => Arr::get($payload, 'name'),
                    'guest_last_name'    => Arr::get($payload, 'surname'),
                    'arrival_date'       => $arrival?->toDateString(),
                    'departure_date'     => $departure?->toDateString(),
                    'occupancy_status'  => 'Checked in'
                ];

                logger($payloadForSvc);

                $svc->checkIn($room, array_filter($payloadForSvc, fn($v) => $v !== null));
                break;
            }

            case 'UPDATE': { // Update guest/stay data
                $room = $findRoomByExt($domainUuid, (string)$ext);
                if (!$room) break;

                $arrival   = $parseDate(Arr::get($payload, 'arrival'));
                $departure = $parseDate(Arr::get($payload, 'departure'));

                $changes = [
                    'guest_first_name'   => Arr::get($payload, 'name'),
                    'guest_last_name'    => Arr::get($payload, 'surname'),
                    'arrival_date'       => $arrival?->toDateString(),
                    'departure_date'     => $departure?->toDateString(),
                ];

                $status = $room->status()->first();
                if ($status) {
                    $status->fill(array_filter($changes, fn($v) => $v !== null))->save();
                } else {
                    HotelRoomStatus::create([
                        'uuid'            => (string) Str::uuid(),
                        'domain_uuid'     => $room->domain_uuid,
                        'hotel_room_uuid' => $room->uuid,
                        ...array_filter($changes, fn($v) => $v !== null),
                    ]);
                }
                break;
            }

            case 'MOVE': { // Move guest between extensions
                $src   = $findRoomByExt($domainUuid, (string)$ext);
                $dstRm = $findRoomByExt($domainUuid, (string)$dst);
                if (!$src || !$dstRm) break;

                $status = $src->status()->first();
                if ($status) {
                    $status->update(['hotel_room_uuid' => $dstRm->uuid]);
                }
                break;
            }

            case 'CHKO': { // Check-out
                $room = $findRoomByExt($domainUuid, (string)$ext);
                if ($room) {
                    $svc->checkOut($room);
                }
                break;
            }
        }
    }
}
