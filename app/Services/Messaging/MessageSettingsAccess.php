<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\DB;

class MessageSettingsAccess
{
    public static function domains(): array
    {
        return collect(session('domains', []))->pluck('domain_uuid')
            ->push(session('domain_uuid'))->filter()->unique()->values()->all();
    }

    public static function canManage(?string $domainUuid = null): bool
    {
        $domainUuid ??= session('domain_uuid');
        if (!auth()->check() || !in_array($domainUuid, self::domains(), true)) {
            return false;
        }
        // The permission may be added or revoked while this login is still open.
        // Use the same group and account scope as Reload Permissions, without
        // trusting the permission list cached at login.
        $groups = collect(session('user.groups', []))->pluck('group_uuid')->filter()->values();
        if ($groups->isEmpty()) {
            return false;
        }
        return DB::table('v_group_permissions')->whereIn('group_uuid', $groups)
            ->where('permission_name', 'message_settings_manage')
            ->where('permission_assigned', 'true')
            ->where(fn ($query) => $query->where('domain_uuid', $domainUuid)->orWhereNull('domain_uuid'))
            ->exists();
    }
}
