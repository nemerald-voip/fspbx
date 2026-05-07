<?php

namespace App\Http\Requests;

class UpdateConferenceRequest extends StoreConferenceRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('conference_edit');
    }

    protected function conferenceUuid(): ?string
    {
        $conference = $this->route('conference');

        return is_object($conference)
            ? $conference->conference_uuid
            : $conference;
    }
}
