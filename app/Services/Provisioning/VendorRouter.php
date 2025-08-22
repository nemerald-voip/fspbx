<?php
// app/Services/Provisioning/VendorRouter.php

namespace App\Services\Provisioning;

use App\Models\Devices;

class VendorRouter
{
    /**
     * Extract a lookup token from the filename stem.
     * - Normalize to lowercase, keep only [a-z0-9]
     * - If the result contains NO digits (e.g., "default"), return null → skip
     */
    public static function tokenFromId(string $id): ?string
    {
        $token = strtolower(preg_replace('/[^a-z0-9]/i', '', $id));
        if ($token === '' || !preg_match('/\d/', $token)) {
            // e.g., "default", "polycom", etc. → do not attempt a device lookup
            return null;
        }
        return $token;
    }

    /** Map extension to MIME */
    public static function contentTypeFromExt(string $ext): string
    {
        return strtolower($ext) === 'xml' ? 'application/xml' : 'text/plain';
    }

    /**
     * Find device by token in EITHER device_address OR serial_number.
     * Assumes device_address is already stored normalized (lowercase 12-hex).
     */
    public static function findDeviceByToken(string $token): ?Devices
    {
        if ($token === '') return null;

        return Devices::where(function ($q) use ($token) {
                $q->where('device_address', $token)
                  ->orWhere('serial_number', $token);
            })
            ->first();
    }
}
