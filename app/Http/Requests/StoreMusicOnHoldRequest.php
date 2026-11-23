<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Validation\Rule;

class StoreMusicOnHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('music_on_hold_add');
    }

    public function rules(): array
    {
        return [
            'domain_uuid' => ['nullable', 'uuid'],
            'music_on_hold_name' => ['required', 'string', 'max:255'],
            'music_on_hold_path' => ['nullable', 'string', 'max:1024'],
            'music_on_hold_rate' => ['nullable', Rule::in(['8000', '16000'])],
            'music_on_hold_shuffle' => ['required', Rule::in(['true', 'false'])],
            'music_on_hold_channels' => ['required', Rule::in(['1', '2'])],
            'music_on_hold_interval' => ['nullable', 'integer', 'min:0'],
            'music_on_hold_timer_name' => ['nullable', 'string', 'max:255'],
            // mod_local_stream accepts up to 100 comma-separated paths.
            'music_on_hold_chime_list' => ['nullable', 'array', 'max:100'],
            'music_on_hold_chime_list.*' => ['bail', 'required', 'string', 'max:1024', 'not_regex:/,/'],
            'music_on_hold_chime_freq' => ['nullable', 'integer', 'min:0'],
            'music_on_hold_chime_max' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $chimes = $this->input('music_on_hold_chime_list');
        if (is_string($chimes)) {
            // Older clients submit the stored comma-separated string.
            $chimes = preg_split('/\s*,\s*/', trim($chimes), -1, PREG_SPLIT_NO_EMPTY);
        }

        $this->merge([
            'domain_uuid' => userCheckPermission('music_on_hold_domain')
                ? $this->blankToNull($this->input('domain_uuid'))
                : session('domain_uuid'),
            'music_on_hold_path' => null,
            'music_on_hold_rate' => $this->blankToNull($this->input('music_on_hold_rate')),
            'music_on_hold_shuffle' => $this->input('music_on_hold_shuffle', 'false'),
            'music_on_hold_channels' => (string) $this->input('music_on_hold_channels', '1'),
            'music_on_hold_interval' => $this->blankToNull($this->input('music_on_hold_interval')),
            'music_on_hold_timer_name' => $this->blankToNull($this->input('music_on_hold_timer_name')) ?? 'soft',
            'music_on_hold_chime_list' => $chimes,
            'music_on_hold_chime_freq' => $this->blankToNull($this->input('music_on_hold_chime_freq')),
            'music_on_hold_chime_max' => $this->blankToNull($this->input('music_on_hold_chime_max')),
        ]);
    }

    private function blankToNull($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return in_array($value, ['', '__global__'], true) ? null : $value;
    }

    public function messages(): array
    {
        return ValidationMessages::common() + [
            'music_on_hold_chime_list.*.not_regex' => __('Chime filenames cannot contain commas.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'domain_uuid' => __('Domain'),
            'music_on_hold_name' => __('Name'),
            'music_on_hold_path' => __('Generated path'),
            'music_on_hold_rate' => __('Rate'),
            'music_on_hold_shuffle' => __('Shuffle'),
            'music_on_hold_channels' => __('Channels'),
            'music_on_hold_interval' => __('Interval'),
            'music_on_hold_timer_name' => __('Timer Name'),
            'music_on_hold_chime_list' => __('Chimes'),
            'music_on_hold_chime_list.*' => __('Chime'),
            'music_on_hold_chime_freq' => __('Chime Frequency'),
            'music_on_hold_chime_max' => __('Chime Maximum'),
        ];
    }
}
