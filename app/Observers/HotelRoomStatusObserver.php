<?php

namespace App\Observers;

use App\Models\HotelRoom;
use App\Models\HotelRoomStatus;
use App\Services\PmsOutboundSyncContext;
use App\Services\PmsProviderSettings;
use App\Services\TigerTmsApiClient;
use App\Services\TigerTmsRoomMapper;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HotelRoomStatusObserver
{
    public function created(HotelRoomStatus $status): void
    {
        if ($this->shouldSkip($status->domain_uuid)) {
            return;
        }

        $statusUuid = (string) $status->uuid;

        DB::afterCommit(function () use ($statusUuid) {
            $status = HotelRoomStatus::query()->with('room')->find($statusUuid);

            if (! $status || ! $status->room) {
                return;
            }

            $this->sendCheckIn($status, $status->room);
        });
    }

    public function deleted(HotelRoomStatus $status): void
    {
        if ($this->shouldSkip($status->domain_uuid)) {
            return;
        }

        $domainUuid = (string) $status->domain_uuid;
        $roomUuid = (string) $status->hotel_room_uuid;

        DB::afterCommit(function () use ($domainUuid, $roomUuid) {
            $room = HotelRoom::query()
                ->where('domain_uuid', $domainUuid)
                ->whereKey($roomUuid)
                ->first();

            if (! $room) {
                return;
            }

            $this->sendCheckOut($room);
        });
    }

    private function shouldSkip(?string $domainUuid): bool
    {
        if (blank($domainUuid)) {
            return true;
        }

        if (app(PmsOutboundSyncContext::class)->suppressed()) {
            return true;
        }

        return ! app(PmsProviderSettings::class)->isTigerTms((string) $domainUuid);
    }

    private function sendCheckIn(HotelRoomStatus $status, HotelRoom $room): void
    {
        try {
            app(TigerTmsApiClient::class)->checkIn(
                (string) $room->domain_uuid,
                app(TigerTmsRoomMapper::class)->outbound($room),
                array_filter([
                    'reservationNumber' => (string) $status->uuid,
                    'guests' => [
                        [
                            'guestId' => (string) $status->uuid,
                            'firstname' => $this->guestNamePart($status->guest_first_name),
                            'lastname' => $this->guestNamePart($status->guest_last_name),
                            'language' => $this->defaultGuestLanguage(),
                        ],
                    ],
                    'arrivalDate' => $this->dateOnly($status->arrival_date, (string) $room->domain_uuid),
                    'departureDate' => $this->dateOnly($status->departure_date, (string) $room->domain_uuid),
                ], fn ($value) => $value !== null && $value !== '')
            );
        } catch (Throwable $e) {
            Log::warning('TigerTMS outbound check-in failed', [
                'domain_uuid' => $room->domain_uuid,
                'room_uuid' => $room->uuid,
                'room' => $room->room_name,
                'tigertms_room' => app(TigerTmsRoomMapper::class)->outbound($room),
                'hotel_room_status_uuid' => $status->uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendCheckOut(HotelRoom $room): void
    {
        try {
            app(TigerTmsApiClient::class)->checkOut(
                (string) $room->domain_uuid,
                app(TigerTmsRoomMapper::class)->outbound($room)
            );
        } catch (Throwable $e) {
            Log::warning('TigerTMS outbound check-out failed', [
                'domain_uuid' => $room->domain_uuid,
                'room_uuid' => $room->uuid,
                'room' => $room->room_name,
                'tigertms_room' => app(TigerTmsRoomMapper::class)->outbound($room),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dateOnly(mixed $value, string $domainUuid): ?string
    {
        if (! $value instanceof CarbonInterface) {
            return null;
        }

        $timezone = get_local_time_zone($domainUuid) ?: 'UTC';

        return $value->copy()->setTimezone($timezone)->toDateString();
    }

    private function guestNamePart(?string $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : 'Guest';
    }

    private function defaultGuestLanguage(): string
    {
        $language = trim((string) config('tigertms.default_language', 'en-US'));

        return $language !== '' ? $language : 'en-US';
    }
}
