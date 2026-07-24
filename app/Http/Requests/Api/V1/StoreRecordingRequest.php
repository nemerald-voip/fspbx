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
            'file' => ['required', 'file', 'max:51200', 'mimetypes:audio/wav,audio/x-wav,audio/mpeg,audio/mp4,audio/x-m4a,audio/ogg,audio/flac,audio/x-flac,video/mp4'],
        ];
    }
}
