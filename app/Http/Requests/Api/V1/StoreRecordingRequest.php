<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecordingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(PermissionService::class)->userHasPermission(
            $user,
            'recording_upload',
            (string) $this->route('domain_uuid')
        );
    }

    public function rules(): array
    {
        return [
            'recording_name' => ['required', 'string', 'max:255'],
            'recording_description' => ['nullable', 'string'],
            'file' => ['required', 'file', 'max:51200', 'mimes:wav,mp3,m4a'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'recording_name' => [
                'description' => 'The recording display name.',
                'example' => 'Main Menu Greeting',
            ],
            'recording_description' => [
                'description' => 'Optional description.',
                'example' => 'Primary virtual receptionist greeting',
            ],
            'file' => [
                'description' => 'WAV, MP3, or M4A audio file. Maximum size: 50 MB.',
            ],
        ];
    }
}
