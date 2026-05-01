<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use App\Rules\ValidVoicemailPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use libphonenumber\PhoneNumberFormat;

class UpdateVoicemailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $currentUuid = $this->route('voicemail');

        return [
            'domain_uuid' => ['sometimes', 'uuid'],
            'voicemail_enabled' => ['required', 'in:true,false'],

            'voicemail_id' => ['sometimes', 'numeric', new UniqueExtension($currentUuid)],
            'voicemail_password' => ['nullable', 'numeric', new ValidVoicemailPassword],
            'voicemail_mail_to' => ['nullable', 'email:rfc'],
            'voicemail_sms_to' => ['nullable', 'regex:/^\+?[1-9]\d{1,14}$/'],
            'greeting_id' => ['sometimes', 'string'],

            'voicemail_tutorial' => ['sometimes', 'in:true,false'],
            'voicemail_transcription_enabled' => ['sometimes', 'in:true,false'],
            'voicemail_local_after_email' => ['sometimes', 'in:true,false'],
            'voicemail_recording_instructions' => ['sometimes', 'in:true,false'],

            'voicemail_file' => ['sometimes', 'nullable', Rule::in(['attach', 'link', ''])],
            'voicemail_alternate_greet_id' => ['nullable', 'numeric'],
            'voicemail_description' => ['nullable', 'string', 'max:100'],
            'voicemail_copies' => ['nullable', 'array'],
            'voicemail_copies.*' => ['uuid'],
            'extension' => ['nullable', 'uuid'],

            'vm_notify_profile' => ['nullable', 'array'],
            'vm_notify_profile.enabled' => ['nullable', 'boolean'],
            'vm_notify_profile.name' => ['nullable', 'string', 'max:255'],
            'vm_notify_profile.description' => ['nullable', 'string'],
            'vm_notify_profile.outbound_cid_mode' => ['nullable', 'string', 'in:default,mailbox'],
            'vm_notify_profile.caller_id_number' => ['nullable', 'string', 'max:255'],
            'vm_notify_profile.caller_id_name' => ['nullable', 'string', 'max:255'],
            'vm_notify_profile.retry_count' => ['nullable', 'integer', 'min:0'],
            'vm_notify_profile.retry_delay_minutes' => ['nullable', 'integer', 'min:0'],
            'vm_notify_profile.priority_delay_minutes' => ['nullable', 'integer', 'min:0'],
            'vm_notify_profile.email_success' => ['nullable', 'array'],
            'vm_notify_profile.email_success.*' => ['nullable', 'email:rfc'],
            'vm_notify_profile.email_fail' => ['nullable', 'array'],
            'vm_notify_profile.email_fail.*' => ['nullable', 'email:rfc'],
            'vm_notify_profile.email_attach' => ['nullable', 'boolean'],

            'vm_notify_profile.recipients' => ['nullable', 'array'],
            'vm_notify_profile.recipients.*.vm_notify_profile_recipient_uuid' => ['nullable', 'uuid'],
            'vm_notify_profile.recipients.*.recipient_type' => ['nullable', 'string', 'in:extension,external_number'],
            'vm_notify_profile.recipients.*.extension_uuid' => ['nullable', 'uuid'],
            'vm_notify_profile.recipients.*.phone_number' => ['nullable', 'regex:/^\+?[1-9]\d{1,14}$/'],
            'vm_notify_profile.recipients.*.display_name' => ['nullable', 'string', 'max:255'],
            'vm_notify_profile.recipients.*.priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'vm_notify_profile.recipients.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'vm_notify_profile.recipients.*.enabled' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        $domain_uuid = session('domain_uuid');

        if (!$this->has('domain_uuid')) {
            $merge['domain_uuid'] = $domain_uuid;
        }

        if ($this->has('voicemail_mail_to')) {
            $merge['voicemail_mail_to'] = $this->voicemail_mail_to
                ? strtolower(trim($this->voicemail_mail_to))
                : null;
        }

        if ($this->has('voicemail_description') && $this->voicemail_description) {
            $merge['voicemail_description'] = $this->sanitizeInput($this->voicemail_description);
        }

        if ($this->has('greeting_id') && $this->greeting_id === 'NULL') {
            $merge['greeting_id'] = '-1';
        }

        if ($this->has('voicemail_file')) {
            $merge['voicemail_file'] = in_array($this->input('voicemail_file'), ['attach', 'link'], true)
                ? $this->input('voicemail_file')
                : '';
        }

        if ($this->has('voicemail_sms_to') && !blank($this->input('voicemail_sms_to'))) {
            $countryCode = get_domain_setting('country', $domain_uuid) ?? 'US';

            try {
                $merge['voicemail_sms_to'] = formatPhoneNumber(
                    $this->input('voicemail_sms_to'),
                    $countryCode,
                    PhoneNumberFormat::E164
                );
            } catch (\Throwable $e) {
                // Leave original value as-is so validation can fail naturally
            }
        }

        if ($this->has('vm_notify_profile') && is_array($this->input('vm_notify_profile'))) {
            $profile = $this->input('vm_notify_profile');

            if (array_key_exists('enabled', $profile)) {
                $profile['enabled'] = $this->toBoolean($profile['enabled']);
            }

            if (array_key_exists('email_attach', $profile)) {
                $profile['email_attach'] = $this->toBoolean($profile['email_attach']);
            }

            foreach (['name', 'description', 'caller_id_name'] as $field) {
                if (array_key_exists($field, $profile)) {
                    $profile[$field] = blank($profile[$field])
                        ? null
                        : $this->sanitizeInput((string) $profile[$field]);
                }
            }

            if (array_key_exists('caller_id_number', $profile) && !blank($profile['caller_id_number'])) {
                $countryCode = get_domain_setting('country', $domain_uuid) ?? 'US';

                try {
                    $profile['caller_id_number'] = formatPhoneNumber(
                        $profile['caller_id_number'],
                        $countryCode,
                        PhoneNumberFormat::E164
                    );
                } catch (\Throwable $e) {
                    // Leave original value as-is so validation can fail naturally
                }
            }

            foreach (['email_success', 'email_fail'] as $field) {
                if (array_key_exists($field, $profile) && is_array($profile[$field])) {
                    $profile[$field] = collect($profile[$field])
                        ->map(fn ($email) => blank($email) ? null : strtolower(trim($email)))
                        ->filter()
                        ->values()
                        ->all();
                }
            }

            if (array_key_exists('recipients', $profile) && is_array($profile['recipients'])) {
                $countryCode = get_domain_setting('country', $domain_uuid) ?? 'US';

                $profile['recipients'] = collect($profile['recipients'])
                    ->map(function ($recipient, $index) use ($countryCode) {
                        $recipientType = $recipient['recipient_type'] ?? null;
                        $phoneNumber = $recipient['phone_number'] ?? null;

                        if ($recipientType === 'external_number' && !blank($phoneNumber)) {
                            try {
                                $phoneNumber = formatPhoneNumber(
                                    $phoneNumber,
                                    $countryCode,
                                    PhoneNumberFormat::E164
                                );
                            } catch (\Throwable $e) {
                                // Leave original value as-is so validation can fail naturally
                            }
                        }

                        return [
                            'vm_notify_profile_recipient_uuid' => $recipient['vm_notify_profile_recipient_uuid'] ?? null,
                            'recipient_type' => $recipientType,
                            'extension_uuid' => blank($recipient['extension_uuid'] ?? null) ? null : $recipient['extension_uuid'],
                            'phone_number' => blank($phoneNumber) ? null : trim((string) $phoneNumber),
                            'display_name' => blank($recipient['display_name'] ?? null)
                                ? null
                                : $this->sanitizeInput((string) $recipient['display_name']),
                            'priority' => blank($recipient['priority'] ?? null) ? 0 : (int) $recipient['priority'],
                            'sort_order' => blank($recipient['sort_order'] ?? null) ? $index : (int) $recipient['sort_order'],
                            'enabled' => $this->toBoolean($recipient['enabled'] ?? true),
                        ];
                    })
                    ->values()
                    ->all();
            }

            $merge['vm_notify_profile'] = $profile;
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $profile = $this->input('vm_notify_profile');

            if (!is_array($profile)) {
                return;
            }

            $enabled = $this->toBoolean($profile['enabled'] ?? false);
            $recipients = $profile['recipients'] ?? [];

            if (!is_array($recipients)) {
                $recipients = [];
            }

            $validRecipientCount = 0;

            foreach ($recipients as $index => $recipient) {
                $recipientType = $recipient['recipient_type'] ?? null;

                if (blank($recipientType)) {
                    continue;
                }

                if ($recipientType === 'extension') {
                    if (blank($recipient['extension_uuid'] ?? null)) {
                        $validator->errors()->add(
                            "vm_notify_profile.recipients.$index.extension_uuid",
                            'The recipient extension field is required.'
                        );
                        continue;
                    }

                    $validRecipientCount++;
                    continue;
                }

                if ($recipientType === 'external_number') {
                    if (blank($recipient['phone_number'] ?? null)) {
                        $validator->errors()->add(
                            "vm_notify_profile.recipients.$index.phone_number",
                            'The recipient phone number field is required.'
                        );
                        continue;
                    }

                    $validRecipientCount++;
                }
            }

            if ($enabled && $validRecipientCount === 0) {
                $validator->errors()->add(
                    'vm_notify_profile.recipients',
                    'At least one recipient is required when voicemail escalation is enabled.'
                );
            }
        });
    }

    protected function sanitizeInput(string $input): string
    {
        $input = trim($input);
        $input = strip_tags($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        $input = preg_replace('/[^\x20-\x7E]/', '', $input);

        return $input;
    }

    public function attributes(): array
    {
        return [
            'voicemail_id' => 'voicemail extension',
            'voicemail_password' => 'voicemail password',
            'greeting_id' => 'greeting',
            'voicemail_mail_to' => 'email address',
            'voicemail_enabled' => 'enabled',
            'voicemail_description' => 'description',
            'voicemail_alternate_greet_id' => 'value',

            'vm_notify_profile.name' => 'escalation rule name',
            'vm_notify_profile.description' => 'escalation description',
            'vm_notify_profile.outbound_cid_mode' => 'outbound caller ID mode',
            'vm_notify_profile.caller_id_number' => 'caller ID number',
            'vm_notify_profile.caller_id_name' => 'caller ID name',
            'vm_notify_profile.retry_count' => 'retry count',
            'vm_notify_profile.retry_delay_minutes' => 'retry delay',
            'vm_notify_profile.priority_delay_minutes' => 'priority delay',
            'vm_notify_profile.email_success.*' => 'success notification email',
            'vm_notify_profile.email_fail.*' => 'failure notification email',
            'vm_notify_profile.recipients.*.recipient_type' => 'recipient type',
            'vm_notify_profile.recipients.*.extension_uuid' => 'recipient extension',
            'vm_notify_profile.recipients.*.phone_number' => 'recipient phone number',
            'vm_notify_profile.recipients.*.priority' => 'recipient priority',
        ];
    }

    protected function toBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }

        return (bool) $value;
    }
}
