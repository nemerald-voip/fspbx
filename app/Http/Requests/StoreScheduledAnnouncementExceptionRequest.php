<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
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

    public function messages(): array
    {
        return ValidationMessages::common() + [
            'timezone' => __('The :attribute must be a valid timezone.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'scheduled_announcement_schedule_uuid' => __('Schedule'),
            'exception_date' => __('Date'),
            'comment' => __('Comment'),
        ];
    }
}
