<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class FusionCache extends Model
{

    /**
     * Delete a specific item from the cache
     * @var string $key		cache id
     */
    static function clear($key)
    {

        $cacheType = DefaultSettings::where('default_setting_category', 'cache')
            ->where('default_setting_subcategory', 'method')
            ->pluck('default_setting_value')
            ->first();

        //cache method memcache 
        if ($cacheType == "memcache") {
            // connect to Freeswitch 
            $freeswitchSettings = FreeswitchSettings::first();
            $fp = event_socket_create(
                $freeswitchSettings['event_socket_ip_address'],
                $freeswitchSettings['event_socket_port'],
                $freeswitchSettings['event_socket_password']
            );
            if ($fp === false) {
                return false;
            }

            //send a custom event
            $event = "sendevent CUSTOM\n";
            $event .= "Event-Name: CUSTOM\n";
            $event .= "Event-Subclass: fusion::memcache\n";
            $event .= "API-Command: memcache\n";
            $event .= "API-Command-Argument: delete " . $key . "\n";
            event_socket_request($fp, $event);

            //run the memcache
            $command = "memcache delete " . $key;
            $result = event_socket_request($fp, 'bgapi ' . $command);

            //close event socket
            fclose($fp);
        }

        //cache method file
        if ($cacheType == "file") {
            //change the delimiter
            $key = str_replace(":", ".", $key);

            // connect to Freeswitch 
            $freeswitchSettings = FreeswitchSettings::first();
            $fp = event_socket_create(
                $freeswitchSettings['event_socket_ip_address'],
                $freeswitchSettings['event_socket_port'],
                $freeswitchSettings['event_socket_password']
            );
            if ($fp === false) {
                return false;
            }

            //send a custom event
            $event = "sendevent CUSTOM\n";
            $event .= "Event-Name: CUSTOM\n";
            $event .= "Event-Subclass: fusion::file\n";
            $event .= "API-Command: cache\n";
            $event .= "API-Command-Argument: delete " . $key . "\n";
            event_socket_request($fp, $event);

            $cacheLocation = DefaultSettings::where('default_setting_category', 'cache')
                ->where('default_setting_subcategory', 'location')
                ->pluck('default_setting_value')
                ->first();



            // Delete cache file
            foreach (glob($cacheLocation . '/' . $key) as $file) {
                if (File::exists($file)) {
                    File::delete($file);
                }
            }
        }
    }
}
