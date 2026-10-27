<?php

namespace App\Http\Requests\Api\V1;

use App\Exceptions\ApiException;
use App\Models\Domain;
use App\Services\Messaging\MessageSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use JsonException;

class StoreMessageSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The API middleware checks token permissions and access to this account.
        $uuid = (string) $this->route('domain_uuid');
        if (! Str::isUuid($uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }
        if (! Domain::whereKey($uuid)->exists()) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'destination' => ['required', 'string', 'regex:/^\+[1-9][0-9]{1,14}$/D'],
            'carrier' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(MessageSettingsService::carriers()))],
            'allowed_extension_uuids' => ['sometimes', 'array'],
            'allowed_extension_uuids.*' => ['required', 'uuid', 'distinct', Rule::exists('v_extensions', 'extension_uuid')
                ->where('domain_uuid', (string) $this->route('domain_uuid'))],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Laravel otherwise decodes malformed JSON as empty input, making PATCH a silent no-op.
        if ($this->isJson() && $this->getContent() !== '') {
            try {
                json_decode($this->getContent(), flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new ApiException(400, 'invalid_request_error', 'Invalid JSON request body. Remove trailing commas and check the JSON syntax.', 'invalid_json');
            }
        }

        $domainUuid = (string) $this->route('domain_uuid');
        if (Str::isUuid($domainUuid) && is_string($this->input('destination'))) {
            $this->merge(['destination' => app(MessageSettingsService::class)->normalizeDestination($this->input('destination'), $domainUuid)]);
        }
        if ($this->has('enabled') && $this->input('enabled') !== null && $this->input('enabled') !== '') {
            $value = filter_var($this->input('enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null) {
                $this->merge(['enabled' => $value]);
            }
        }
    }

    public function messages(): array
    {
        return ['destination.regex' => 'Enter a valid phone number for the configured country, or include + and the country code.'];
    }

    public function bodyParameters(): array
    {
        return [
            'destination' => ['description' => 'The messaging phone number, automatically converted to E.164 using the account country or the country in Default Settings. If neither is configured, US is used. Numbers starting with + use their supplied country code. Required on creation; omit on update to keep the current number.', 'example' => '+12125550123'],
            'carrier' => ['description' => 'Message provider identifier. Use thinq for Commio (ThinQ) and voipms for VoIP.MS. May be null. Omit on update to preserve the provider.', 'example' => 'voipms'],
            'allowed_extension_uuids' => ['description' => 'Extensions in this account that may send and receive messages using this number and see each other\'s replies. Defaults to an empty list. On update, replaces the entire list; omit to preserve access or send [] to remove all extension access.', 'example' => ['d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b']],
            'allowed_extension_uuids.*' => ['description' => 'An extension UUID from this account. Use the Extensions API to find extension UUIDs.', 'example' => 'd2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b'],
            'email' => ['description' => 'Optional email address for incoming messages. Omit on update to preserve it; send null to clear it.', 'example' => 'messages@example.com'],
            'description' => ['description' => 'Optional description for administrators. Omit on update to preserve it; send null to clear it.', 'example' => 'Customer support'],
            'enabled' => ['description' => 'Whether messaging is enabled for this number. Defaults to true on creation. Omit on update to preserve its status.', 'example' => true],
        ];
    }
}
