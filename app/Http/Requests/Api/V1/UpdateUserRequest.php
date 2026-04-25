<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // API uses route middleware for permissions + domain scope
        return true;
    }

    public function rules(): array
    {
        $userUuid = (string) $this->route('user_uuid');

        return [
            'first_name'      => ['sometimes', 'string', 'max:100'],
            'last_name'       => ['sometimes', 'string', 'max:100'],
            'user_email'      => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('v_users', 'user_email')->ignore($userUuid, 'user_uuid'),
            ],
            'username'        => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('v_users', 'username')->ignore($userUuid, 'user_uuid'),
            ],
            'password'        => ['sometimes', 'string', 'min:12'],
            'is_domain_admin' => ['sometimes', 'boolean'],
            'user_enabled'    => ['sometimes', 'boolean'],
            'language'        => ['sometimes', 'nullable', 'string', 'max:10'],
            'time_zone'       => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_email.unique' => 'A user with this email already exists.',
            'username.unique'   => 'A user with this username already exists.',
            'password.min'      => 'The password must be at least 12 characters long.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'first_name'      => ['description' => 'First name of the user.',           'example' => 'Ada'],
            'last_name'       => ['description' => 'Last name of the user.',            'example' => 'Lovelace'],
            'user_email'      => ['description' => 'Login email address.',              'example' => 'ada.lovelace@example.com'],
            'username'        => ['description' => 'Login username.',                   'example' => 'ada_lovelace'],
            'password'        => ['description' => 'New password (min 12 chars). Hashed before storage.', 'example' => 'correcthorsebatterystaple'],
            'is_domain_admin' => [
                'description' => 'When true, ensure user is in the domain `admin` group. When false, remove them from it.',
                'example'     => false,
            ],
            'user_enabled'    => ['description' => 'Whether the user can log in.', 'example' => true],
            'language'        => ['description' => 'Preferred UI language code.', 'example' => 'en-us'],
            'time_zone'       => ['description' => 'IANA time zone name.',        'example' => 'Europe/London'],
        ];
    }
}
