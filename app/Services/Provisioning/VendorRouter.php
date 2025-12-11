<?php
// app/Services/Provisioning/VendorRouter.php

namespace App\Services\Provisioning;

use App\Models\Devices;

class VendorRouter
{
    /**
     * Extract a lookup token from a filename/path or stem.
     * Accepts:
     * - Polycom "phone<MAC>.cfg" → "<mac>" (e.g., phone0004f2abcdef.cfg → 0004f2abcdef)
     * - Polycom "[MODEL]-<MAC>.cfg" where MODEL is SPIP/VVX/SSIP + 3–4 digits (or SSDuo)
     *   e.g., VVX600-0004f27a9446.cfg → 0004f27a9446
     * - Bare MACs in any common format (00:04:F2:AB:CD:EF, 00-04-..., 0004.f2ab.cdef, 0004f2abcdef)
     * - Other IDs with digits (e.g., "y000000000065") → returns normalized token
     * - Strings with no digits (e.g., "default", "polycom") → null
     */
    public static function tokenFromId(string $id): ?string
    {
        // 1) If a full path/filename was passed, reduce to basename and strip extension.
        $base = strtolower(basename($id));
        $stem = preg_replace('/\.[^.]+$/', '', $base); // remove last extension, if any

        // 2) Polycom model-MAC (e.g., vvx600-0004f27a9446, spip321-<mac>, ssip7000-<mac>, ssduo-<mac>)
        if (preg_match('/^(?:spip|vvx|ssip|edgee)\d{3,4}-([0-9a-f]{12})$/', $stem, $m) || preg_match('/^ssduo-([0-9a-f]{12})$/', $stem, $m)) {
            return $m[1];
        }

        // 2b) Generic cfg<MAC> (e.g., "cfg200a0d30064a")
        if (preg_match('/^cfg([0-9a-f]{12})$/', $stem, $m)) {
            return $m[1];
        }

        // 3) Normalize: remove common separators, keep only [a-z0-9]
        $compact = preg_replace('/[^a-z0-9]/', '', $stem); // already lowercase

        // 4) Polycom "phone<MAC>" (e.g., "phone0004f2abcdef")
        if (preg_match('/^phone([0-9a-f]{12})$/', $compact, $m)) {
            return $m[1]; // just the MAC
        }

        // 5) Bare MAC (12 hex chars) after normalization
        if (preg_match('/^([0-9a-f]{12})$/', $compact, $m)) {
            return $m[1];
        }

        // 6) Any other token that still contains at least one digit (e.g., yealink "y000000000065")
        if ($compact !== '' && preg_match('/\d/', $compact)) {
            return $compact;
        }

        // 7) No digits → skip lookup
        return null;
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
