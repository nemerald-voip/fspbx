<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
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
            'music_on_hold_path' => ['required', 'string', 'max:1024'],
            'music_on_hold_rate' => ['nullable', Rule::in(['8000', '16000', '32000', '48000'])],
            'music_on_hold_shuffle' => ['required', Rule::in(['true', 'false'])],
            'music_on_hold_channels' => ['required', Rule::in(['1', '2'])],
            'music_on_hold_interval' => ['nullable', 'integer', 'min:0'],
            'music_on_hold_timer_name' => ['nullable', 'string', 'max:255'],
            'music_on_hold_chime_list' => ['nullable', 'string', 'max:1024'],
            'music_on_hold_chime_freq' => ['nullable', 'integer', 'min:0'],
            'music_on_hold_chime_max' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'domain_uuid' => userCheckPermission('music_on_hold_domain') ? $this->blankToNull($this->input('domain_uuid')) : session('domain_uuid'),
            'music_on_hold_rate' => $this->blankToNull($this->input('music_on_hold_rate')),
            'music_on_hold_shuffle' => $this->input('music_on_hold_shuffle', 'false'),
            'music_on_hold_channels' => (string) $this->input('music_on_hold_channels', '1'),
            'music_on_hold_interval' => $this->blankToNull($this->input('music_on_hold_interval')),
            'music_on_hold_timer_name' => $this->blankToNull($this->input('music_on_hold_timer_name')) ?? 'soft',
            'music_on_hold_chime_list' => $this->blankToNull($this->input('music_on_hold_chime_list')),
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

        return $value === '' ? null : $value;
    }
}
