<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadMusicOnHoldFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('music_on_hold_add');
    }

    public function rules(): array
    {
        return [
            'music_on_hold_uuid' => ['nullable', 'uuid', 'required_without:music_on_hold_name'],
            'music_on_hold_name' => ['nullable', 'string', 'max:255', 'required_without:music_on_hold_uuid'],
            'domain_uuid' => ['nullable', 'uuid'],
            'music_on_hold_rate' => ['nullable', Rule::in(['8000', '16000'])],
            'file' => ['required', 'file', 'extensions:wav,mp3,ogg'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'domain_uuid' => session('domain_uuid'),
            'music_on_hold_rate' => $this->blankToNull($this->input('music_on_hold_rate')),
            'music_on_hold_name' => $this->blankToNull($this->input('music_on_hold_name')),
            'music_on_hold_uuid' => $this->blankToNull($this->input('music_on_hold_uuid')),
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
}
