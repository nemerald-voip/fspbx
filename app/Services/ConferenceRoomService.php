<?php

namespace App\Services;

use App\Models\ConferenceCenter;
use App\Models\ConferenceRoom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConferenceRoomService
{
    public function save(array $validated, ?ConferenceRoom $conferenceRoom = null): ConferenceRoom
    {
        return DB::transaction(function () use ($validated, $conferenceRoom) {
            $conferenceRoom ??= new ConferenceRoom();
            $isNew = ! $conferenceRoom->exists;

            $conferenceRoomUuid = $conferenceRoom->conference_room_uuid ?: (string) Str::uuid();

            $conferenceRoom->forceFill([
                'domain_uuid' => session('domain_uuid'),
                'conference_room_uuid' => $conferenceRoomUuid,
                'conference_center_uuid' => $validated['conference_center_uuid'],
                'conference_room_name' => $validated['conference_room_name'],
                'profile' => $validated['profile'] ?? 'default',
                'record' => $validated['record'],
                'moderator_pin' => $this->blankToNull($validated['moderator_pin'] ?? null),
                'participant_pin' => $this->blankToNull($validated['participant_pin'] ?? null),
                'max_members' => (int) ($validated['max_members'] ?? 0),
                'start_datetime' => $this->blankToNull($validated['start_datetime'] ?? null),
                'stop_datetime' => $this->blankToNull($validated['stop_datetime'] ?? null),
                'wait_mod' => $validated['wait_mod'],
                'moderator_endconf' => $validated['moderator_endconf'],
                'announce_name' => $validated['announce_name'],
                'announce_recording' => $validated['announce_recording'],
                'announce_count' => $validated['announce_count'],
                'sounds' => $validated['sounds'],
                'mute' => $validated['mute'],
                'email_address' => userCheckPermission('conference_room_email_address')
                    ? $this->blankToNull($validated['email_address'] ?? null)
                    : $conferenceRoom->email_address,
                'account_code' => userCheckPermission('conference_room_account_code')
                    ? $this->blankToNull($validated['account_code'] ?? null)
                    : $conferenceRoom->account_code,
                'enabled' => $validated['enabled'],
                'description' => $this->blankToNull($validated['description'] ?? null),
                'created' => $isNew ? now() : $conferenceRoom->created,
                'created_by' => $isNew ? session('user_uuid') : $conferenceRoom->created_by,
            ])->save();

            return $conferenceRoom;
        });
    }

    public function delete(Collection $conferenceRooms): int
    {
        return DB::transaction(function () use ($conferenceRooms) {
            $roomUuids = $conferenceRooms->pluck('conference_room_uuid');

            return ConferenceRoom::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('conference_room_uuid', $roomUuids)
                ->delete();
        });
    }

    public function toggle(Collection $conferenceRooms, string $field): void
    {
        DB::transaction(function () use ($conferenceRooms, $field) {
            foreach ($conferenceRooms as $conferenceRoom) {
                $conferenceRoom->forceFill([
                    $field => $conferenceRoom->{$field} === 'true' ? 'false' : 'true',
                ])->save();
            }
        });
    }

    public function generatePin(?string $conferenceCenterUuid, ?string $currentRoomUuid = null): ?string
    {
        $length = (int) ConferenceCenter::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->when($conferenceCenterUuid, fn ($query) => $query->where('conference_center_uuid', $conferenceCenterUuid))
            ->orderBy('conference_center_name')
            ->value('conference_center_pin_length');

        if ($length <= 0) {
            return null;
        }

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $pin = '';
            for ($i = 0; $i < $length; $i++) {
                $pin .= (string) random_int(0, 9);
            }

            $exists = ConferenceRoom::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->when($currentRoomUuid, fn ($query) => $query->where('conference_room_uuid', '!=', $currentRoomUuid))
                ->where(function ($query) use ($pin) {
                    $query->where('moderator_pin', $pin)
                        ->orWhere('participant_pin', $pin);
                })
                ->exists();

            if (! $exists) {
                return $pin;
            }
        }

        return null;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
