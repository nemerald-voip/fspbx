<?php

namespace App\Http\Requests\Api\V1;

use App\Exceptions\ApiException;
use App\Models\Domain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SendMessageRequest extends FormRequest
{
    public function isGroup(): bool
    {
        return $this->routeIs('api.v1.messages.group');
    }

    public function authorize(): bool
    {
        $domain = (string) $this->route('domain_uuid');
        if (! Str::isUuid($domain)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }
        if (! Domain::whereKey($domain)->exists()) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        try {
            $body = json_decode($this->getContent(), flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid JSON request body.', 'invalid_json');
        }
        if (! $this->isJson() || ! is_object($body)) {
            throw new ApiException(400, 'invalid_request_error', 'Send a JSON object.', 'invalid_json');
        }
        $keys = $this->headers->all('Idempotency-Key');
        if (count($keys) > 1 || ($keys && ! preg_match('/^[A-Za-z0-9_-]{1,255}$/D', $keys[0]))) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid Idempotency-Key header.', 'invalid_parameter', 'Idempotency-Key');
        }
    }

    public function rules(): array
    {
        $phoneNumber = ['required', 'string', 'regex:/^\+[1-9][0-9]{1,14}$/D'];

        return [
            'from' => $phoneNumber,
            'to' => $this->isGroup() ? ['required', 'array', 'min:2', 'max:100'] : $phoneNumber,
            ...($this->isGroup() ? ['to.*' => $phoneNumber] : []),
            'text' => ['sometimes', 'nullable', 'string', 'max:1600'],
            'media_urls' => ['sometimes', 'array', 'max:10'],
            'media_urls.*' => ['required', 'string', 'max:8192', 'distinct', 'url:https'],
            'type' => ['sometimes', Rule::in(['SMS', 'MMS'])],
        ];
    }

    public function messages(): array
    {
        $format = 'Use E.164 format: + followed by the country code and phone number, for example +12025550100.';

        return [
            'from.regex' => $format,
            'to.regex' => $format,
            'to.*.regex' => $format,
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (array_diff(array_keys($this->all()), ['from', 'to', 'text', 'media_urls', 'type']) as $field) {
                $validator->errors()->add($field, 'This option is not supported.');
            }
            if ($validator->errors()->isNotEmpty()) return;
            foreach (['media_urls', ...($this->isGroup() ? ['to'] : [])] as $field) {
                if ($this->has($field) && !array_is_list($this->input($field))) {
                    $validator->errors()->add($field, 'Provide a JSON array, not an object.');
                }
            }
            if (trim((string) $this->input('text')) === '' && ! $this->input('media_urls', [])) {
                $validator->errors()->add('text', 'Provide text or at least one media URL.');
            }
            $type = $this->isGroup() || $this->input('media_urls', []) ? 'MMS' : 'SMS';
            if ($this->has('type') && $this->input('type') !== $type) {
                $validator->errors()->add('type', 'The message type does not match its content or recipients.');
            }
        });
    }

    public function bodyParameters(): array
    {
        return [
            'from' => ['description' => 'Your enabled sending number in this account\'s Message Settings. E.164 format is required: + followed by the country code and phone number, without spaces or punctuation. Member assignments are not required.', 'example' => '+12125550123'],
            'to' => ['description' => 'Recipient number, or an array of external recipient numbers for group_mms. Every number must use E.164 format: + followed by the country code and phone number, without spaces or punctuation.', 'example' => $this->isGroup() ? ['+12125550124', '+12125550125'] : '+12125550124'],
            'text' => ['description' => 'Up to 1,600 characters. Text or media is required.', 'example' => 'Your appointment is confirmed.'],
            'media_urls' => ['description' => 'Direct public HTTPS links to attachments, without sign-in or redirects. Maximum 10; ClickSend, Commio and Fibernetics allow 1, VoIP.ms allows 3. The default total download limit is 50 MiB.', 'example' => []],
            'type' => ['description' => 'Optional SMS or MMS. Omit to select automatically. Text to one recipient is SMS; attachments and all groups are MMS. If supplied, the type must match the content and recipients.', 'example' => 'SMS'],
        ];
    }
}
