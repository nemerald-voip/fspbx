<?php

namespace App\Services\Provisioning;

class VendorContext {
    public string $vendor = 'generic';
    public ?string $mac = null;        // AABBCCDDEEFF
    public string $filename = '';      // final segment
    public array  $segments = [];      // full path split
    public bool   $isSensitiveConfig = false; // cfg/xml (likely contains creds)
}

class VendorRouter
{
    public function analyze(string $path, string $ua): VendorContext
    {
        $ctx = new VendorContext;
        $ctx->segments = array_values(array_filter(explode('/', $path)));
        $ctx->filename = end($ctx->segments) ?: 'index';

        // Vendor hint from UA / path
        if (stripos($ua, 'Polycom') !== false || stripos($ua, 'VVX') !== false) {
            $ctx->vendor = 'polycom';
        } elseif (preg_match('#^(\d{2})/#', $path) && stripos($ua, 'DAG') !== false) {
            // Dinstar family prefix like "83/"
            $ctx->vendor = 'dinstar';
        }

        // Try to extract a MAC from path or UA (12 hex)
        if (preg_match('/([0-9A-Fa-f]{12})/', $path, $m)) {
            $ctx->mac = strtoupper($m[1]);
        } elseif (preg_match('/([0-9A-Fa-f]{12})/', $ua, $m)) {
            $ctx->mac = strtoupper($m[1]);
        }

        $ctx->isSensitiveConfig = (bool) preg_match('/\.(cfg|xml)$/i', $ctx->filename);
        return $ctx;
    }
}
