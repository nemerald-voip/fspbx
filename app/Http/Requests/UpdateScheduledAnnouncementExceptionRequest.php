<?php

namespace App\Http\Requests;

class UpdateScheduledAnnouncementExceptionRequest extends StoreScheduledAnnouncementExceptionRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('scheduled_announcements_update');
    }
}
