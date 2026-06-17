<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduledAnnouncementScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('scheduled_announcements_create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'timezone' => ['nullable', 'timezone'],
            'recording_filename' => [
                'required',
                'string',
                Rule::exists('v_recordings', 'recording_filename')->where('domain_uuid', session('domain_uuid')),
            ],
            'busy_extension_behavior' => ['nullable', 'string', Rule::in(['skip', 'force'])],
            'extension_uuids' => ['required', 'array', 'min:1'],
            'extension_uuids.*' => [
                'uuid',
                Rule::exists('v_extensions', 'extension_uuid')->where('domain_uuid', session('domain_uuid')),
            ],
            'enabled' => ['boolean'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => [
                'nullable',
                'date',
                Rule::when($this->filled('starts_on'), ['after_or_equal:starts_on']),
            ],
            'events' => ['sometimes', 'array'],
            'events.*.time_of_day' => [
                'required',
                'string',
                fn (string $attribute, mixed $value, \Closure $fail) => $this->validateTimeOfDay($attribute, $value, $fail),
            ],
            'events.*.weekdays' => ['required', 'array', 'min:1'],
            'events.*.weekdays.*' => ['integer', 'between:1,7'],
            'exceptions' => ['sometimes', 'array'],
            'exceptions.*.exception_date' => ['required', 'date'],
            'exceptions.*.comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function validatedData(): array
    {
        $data = $this->validated();

        $data['domain_uuid'] = session('domain_uuid');
        $data['busy_extension_behavior'] = $data['busy_extension_behavior'] ?? 'skip';
        $data['extension_uuids'] = array_values(array_unique($data['extension_uuids'] ?? []));
        $data['enabled'] = (bool) ($data['enabled'] ?? true);

        foreach (($data['events'] ?? []) as $index => $event) {
            $data['events'][$index]['time_of_day'] = $this->normalizeTimeOfDay($event['time_of_day']);
        }

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
