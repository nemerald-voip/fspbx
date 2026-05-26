<?php

namespace App\Http\Requests;

use App\Models\Voicemails;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCallBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('call_block_add');
    }

    public function rules(): array
    {
        return [
            'call_block_direction' => ['required', Rule::in(['inbound', 'outbound'])],
            'extension_uuid' => ['nullable', 'uuid'],
            'call_block_name' => ['nullable', 'string', 'max:255'],
            'call_block_country_code' => ['nullable', 'string', 'max:6'],
            'call_block_number' => ['nullable', 'string', 'max:255'],
            'call_block_action' => ['required', 'string', 'max:512'],
            'call_block_voicemail' => ['nullable', 'string', 'max:255'],
            'call_block_enabled' => ['required', Rule::in(['true', 'false'])],
            'call_block_description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'extension_uuid' => blank($this->input('extension_uuid')) ? null : $this->input('extension_uuid'),
            'call_block_name' => blank($this->input('call_block_name')) ? null : trim((string) $this->input('call_block_name')),
            'call_block_country_code' => blank($this->input('call_block_country_code')) ? null : preg_replace('/\D+/', '', (string) $this->input('call_block_country_code')),
            'call_block_number' => $this->normalizeCallerIdNumber($this->input('call_block_number')),
            'call_block_voicemail' => blank($this->input('call_block_voicemail')) ? null : trim((string) $this->input('call_block_voicemail')),
            'call_block_enabled' => $this->input('call_block_enabled', 'true'),
            'call_block_description' => blank($this->input('call_block_description')) ? null : trim((string) $this->input('call_block_description')),
        ]);
    }

    private function normalizeCallerIdNumber(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $raw = (string) $value;
        $normalized = preg_replace('/[^\d+]+/', '', $raw);
        $normalized = ltrim($normalized, '+');

        if (str_contains($raw, '+')) {
            $normalized = '+' . $normalized;
        }

        return $normalized === '' ? null : $normalized;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! userCheckPermission('call_block_view_all_records')) {
                $userExtensionUuid = optional($this->user())->extension_uuid;

                if (! $userExtensionUuid) {
                    $validator->errors()->add('extension_uuid', 'Your user account is not assigned to an extension.');
                    return;
                }

                if ($this->input('extension_uuid') !== $userExtensionUuid) {
                    $validator->errors()->add('extension_uuid', 'You can only manage call blocks for your own extension.');
                }
            }

            [$app, $data] = $this->parsedAction();
            if (! in_array($app, ['reject', 'busy', 'voicemail'], true)) {
                $validator->errors()->add('call_block_action', 'Select a valid call block action.');
            }

            if ($app === 'voicemail') {
                if (! userCheckPermission('call_block_voicemail')) {
                    $validator->errors()->add('call_block_action', 'You do not have permission to send blocked calls to voicemail.');
                }

                if (blank($data)) {
                    $validator->errors()->add('call_block_voicemail', 'Select a voicemail mailbox.');
                }

                if (! blank($data) && ! Voicemails::query()
                    ->where('domain_uuid', session('domain_uuid'))
                    ->where('voicemail_id', $data)
                    ->where('voicemail_enabled', 'true')
                    ->exists()) {
                    $validator->errors()->add('call_block_voicemail', 'Select a valid voicemail mailbox.');
                }
            }
        });
    }

    public function parsedAction(): array
    {
        $value = (string) $this->input('call_block_action');
        $parts = explode(':', $value, 2);
        $app = $parts[0] ?? '';
        $data = $parts[1] ?? null;

        if ($app === 'voicemail') {
            $data = $this->input('call_block_voicemail', $data);
        }

        return [$app, $data];
    }
}
