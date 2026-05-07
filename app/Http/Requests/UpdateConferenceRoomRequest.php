<?php

namespace App\Http\Requests;

class UpdateConferenceRoomRequest extends StoreConferenceRoomRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('conference_room_edit');
    }

    protected function conferenceRoomUuid(): ?string
    {
        $conferenceRoom = $this->route('conference_room');

        return is_object($conferenceRoom)
            ? $conferenceRoom->conference_room_uuid
            : $conferenceRoom;
    }
}
