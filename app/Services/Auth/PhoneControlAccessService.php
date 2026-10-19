<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\Extensions;
use App\Models\User;

class PhoneControlAccessService
{
    public function __construct(private PermissionService $permissions) {}

    public function authorizeExtension(User $user, string $domainUuid, string $extension): void
    {
        if ($this->permissions->userHasPermission($user, 'phone_control_view_all_records', $domainUuid)) {
            return;
        }

        if ($user->extension_uuid && Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->where('extension_uuid', $user->extension_uuid)
            ->where('extension', $extension)
            ->exists()) {
            return;
        }

        throw new ApiException(
            403,
            'invalid_request_error',
            'You do not have access to this extension.',
            'forbidden_extension',
            'extension'
        );
    }
}
