<?php

namespace App\Http\Requests;

class UpdateScheduledAnnouncementEventRequest extends StoreScheduledAnnouncementEventRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('scheduled_announcements_update');
    }
}
