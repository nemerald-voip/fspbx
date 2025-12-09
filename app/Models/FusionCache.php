<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use App\Models\DefaultSettings;
use App\Services\FreeswitchEslService;

class FusionCache extends Model
{
    protected static ?string $cacheType = null;
    protected static ?string $cacheLocation = null;

    /**
     * Get and cache the cache method (memcache/file).
     */
    protected static function cacheType(): ?string
    {
        if (static::$cacheType !== null) {
            return static::$cacheType;
        }

        static::$cacheType = DefaultSettings::where('default_setting_category', 'cache')
            ->where('default_setting_subcategory', 'method')
            ->value('default_setting_value');

        return static::$cacheType;
    }

    /**
     * Get and cache the cache location (for file cache).
     */
    protected static function cacheLocation(): ?string
    {
        if (static::$cacheLocation !== null) {
            return static::$cacheLocation;
        }

        static::$cacheLocation = DefaultSettings::where('default_setting_category', 'cache')
            ->where('default_setting_subcategory', 'location')
            ->value('default_setting_value');

        return static::$cacheLocation;
    }

    /**
     * Small helper to get a connected ESL service or null.
     */
    protected static function esl(): ?FreeswitchEslService
    {
        $service = new FreeswitchEslService();

        if (!$service->isConnected()) {
            // Optional: logger('FusionCache: unable to connect to FreeSWITCH ESL');
            return null;
        }

        return $service;
    }

    /**
     * Delete a specific item from the cache.
     *
     * @param string $key cache id
     */
    public static function clear(string $key): bool
    {
        $cacheType = static::cacheType();

        if (!$cacheType) {
            return false;
        }

        $esl = static::esl();
        if (!$esl) {
            return false;
        }

        if ($cacheType === 'memcache') {
            // memcache delete <key>
            $esl->executeCommand('memcache delete ' . $key);
            return true;
        }

        if ($cacheType === 'file') {
            // change the delimiter for file cache
            $key = str_replace(':', '.', $key);

            // cache delete <key> in FreeSWITCH
            $esl->executeCommand('cache delete ' . $key);

            $cacheLocation = static::cacheLocation();

            if ($cacheLocation) {
                // Delete cache file(s) on disk
                $files = glob($cacheLocation . '/' . $key) ?: [];
                if (!empty($files)) {
                    File::delete($files);
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Flush all cache entries (memcache or file).
     */
    public static function flushAll(): bool
    {
        $cacheType = static::cacheType();

        if (!$cacheType) {
            return false;
        }

        $esl = static::esl();
        if (!$esl) {
            return false;
        }

        if ($cacheType === 'memcache') {
            // memcache flush
            $esl->executeCommand('memcache flush');
            return true;
        }

        if ($cacheType === 'file') {
            // cache flush in FreeSWITCH
            $esl->executeCommand('cache flush');

            $cacheLocation = static::cacheLocation();

            if ($cacheLocation && File::isDirectory($cacheLocation)) {
                File::cleanDirectory($cacheLocation);
            }

            return true;
        }

        return false;
    }
}
