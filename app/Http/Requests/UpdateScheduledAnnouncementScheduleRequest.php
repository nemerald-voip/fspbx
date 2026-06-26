<?php

namespace App\Http\Requests;

class UpdateScheduledAnnouncementScheduleRequest extends StoreScheduledAnnouncementScheduleRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('scheduled_announcements_update');
    }
}
