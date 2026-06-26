<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduledAnnouncementExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('scheduled_announcements_create');
    }

    public function rules(): array
    {
        return [
            'scheduled_announcement_schedule_uuid' => [
                'required',
                'uuid',
                Rule::exists('scheduled_announcement_schedules', 'scheduled_announcement_schedule_uuid')
                    ->where('domain_uuid', session('domain_uuid')),
            ],
            'exception_date' => ['required', 'date'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function validatedData(): array
    {
        $data = $this->validated();
        $data['domain_uuid'] = session('domain_uuid');

        return $data;
    }
}
