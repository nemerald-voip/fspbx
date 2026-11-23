<?php

namespace App\Services;

use App\Models\HotelRoom;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class HotelPortalAccess
{
    public static function allows(string $permission): bool
    {
        return Str::isUuid(session('domain_uuid')) && userCheckPermission($permission);
    }

    public static function rooms(): Builder
    {
        return HotelRoom::where('domain_uuid', session('domain_uuid'));
    }

    public static function selectedRooms(array $ids): Collection
    {
        $rooms = self::rooms()->whereKey($ids)->get();
        abort_unless($rooms->count() === count($ids), 404);

        return $rooms;
    }
}
