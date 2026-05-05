<?php

namespace App\Http\Requests;

class UpdateConferenceCenterRequest extends StoreConferenceCenterRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('conference_center_edit');
    }

    protected function conferenceCenterUuid(): ?string
    {
        $conferenceCenter = $this->route('conference_center');

        return is_object($conferenceCenter)
            ? $conferenceCenter->conference_center_uuid
            : $conferenceCenter;
    }
}
