<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduledAnnouncementEventRequest extends FormRequest
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
            'time_of_day' => [
                'required',
                'string',
                fn (string $attribute, mixed $value, \Closure $fail) => $this->validateTimeOfDay($attribute, $value, $fail),
            ],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
        ];
    }

    public function validatedData(): array
    {
        $data = $this->validated();

        $data['domain_uuid'] = session('domain_uuid');
        $data['time_of_day'] = $this->normalizeTimeOfDay($data['time_of_day']);
        $data['weekdays'] = array_values(array_unique(array_map('intval', $data['weekdays'] ?? [])));

        return $data;
    }

    private function validateTimeOfDay(string $attribute, mixed $value, \Closure $fail): void
    {
        if ($this->normalizeTimeOfDay($value) === null) {
            $fail('The time field must be a valid time.');
        }
    }

    private function normalizeTimeOfDay(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        foreach (['H:i', 'H:i:s', 'h:i A', 'h:i a', 'g:i A', 'g:i a'] as $format) {
            try {
                return Carbon::createFromFormat($format, trim($value))->format('H:i');
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
