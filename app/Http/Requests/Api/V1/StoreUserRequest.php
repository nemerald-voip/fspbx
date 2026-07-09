<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // API uses route middleware for permissions + domain scope
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'user_email'      => ['required', 'email', 'max:255', 'unique:v_users,user_email'],
            'username'        => ['sometimes', 'nullable', 'string', 'max:255', 'unique:v_users,username'],
            'password'        => ['required', 'string', 'min:12'],
            'is_domain_admin' => ['sometimes', 'boolean'],
            'user_enabled'    => ['sometimes', 'boolean'],
            'language'        => ['sometimes', 'nullable', 'string', 'max:10'],
            'time_zone'       => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    public function prepareForValidation(): void
    {
        // Derive username from first_name + last_name if not supplied,
        // matching app/Http/Controllers/UsersController@store behavior.
        if (! $this->filled('username') && $this->filled('first_name')) {
            $username = Str::slug((string) $this->input('first_name'), '_');
            if ($this->filled('last_name')) {
                $username .= '_' . Str::slug((string) $this->input('last_name'), '_');
            }
            $this->merge(['username' => $username]);
        }
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
            'first_name' => [
                'description' => 'First name of the user.',
                'example'     => 'Ada',
            ],
            'last_name' => [
                'description' => 'Last name of the user.',
                'example'     => 'Lovelace',
            ],
            'user_email' => [
                'description' => 'Login email address. Must be globally unique.',
                'example'     => 'ada.lovelace@example.com',
            ],
            'username' => [
                'description' => 'Optional login username. If omitted it is derived from first_name + last_name.',
                'example'     => 'ada_lovelace',
            ],
            'password' => [
                'description' => 'Initial password. Minimum 12 characters. Stored hashed via bcrypt.',
                'example'     => 'correcthorsebatterystaple',
            ],
            'is_domain_admin' => [
                'description' => 'When true, the user is added to the `admin` group for the URL domain — making them a domain administrator. Default: false.',
                'example'     => true,
            ],
            'user_enabled' => [
                'description' => 'Whether the user can log in. Defaults to true.',
                'example'     => true,
            ],
            'language' => [
                'description' => 'Preferred UI language code.',
                'example'     => 'en-us',
            ],
            'time_zone' => [
                'description' => 'IANA time zone name.',
                'example'     => 'Europe/London',
            ],
        ];
    }
}
