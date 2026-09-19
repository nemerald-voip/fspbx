<?php

namespace App\Http\Requests;

use App\Models\MusicStreams;
use App\Rules\StreamLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStreamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission($this->isMethod('PUT') ? 'stream_edit' : 'stream_add');
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['stream_name', 'stream_location', 'stream_description'] as $field) {
            if (is_string($this->input($field))) {
                $values[$field] = trim($this->input($field));
            }
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        $stream = $this->route('stream');
        // Existing custom FreeSWITCH locations remain editable without rewriting them.
        $unchanged = $stream instanceof MusicStreams
            && $this->input('stream_location') === $stream->stream_location;

        return [
            'stream_name' => ['required', 'string', 'max:255'],
            'stream_location' => ['required', 'string', 'max:255', ...($unchanged ? [] : [new StreamLocation()])],
            'stream_enabled' => ['required', Rule::in(['true', 'false'])],
            'stream_description' => ['nullable', 'string', 'max:4096'],
            'domain_uuid' => ['sometimes', 'nullable', Rule::in([session('domain_uuid')])],
        ];
    }
}
