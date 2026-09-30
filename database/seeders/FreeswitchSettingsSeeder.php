<?php

namespace Database\Seeders;

use App\Models\DefaultSettings;
use Illuminate\Database\Seeder;

/**
 * Initialize directory settings for the Debian installer before FreeSWITCH starts.
 * Called by fresh setup only, not by DatabaseSeeder or application upgrades.
 */
class FreeswitchSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $existing = DefaultSettings::query()
            ->where('default_setting_category', 'switch')
            ->where('default_setting_name', 'dir')
            ->get()
            ->keyBy('default_setting_subcategory');

        $conf = rtrim(trim((string) ($existing->get('conf')?->default_setting_value)), '/') ?: '/etc/freeswitch';
        $storage = rtrim(trim((string) ($existing->get('storage')?->default_setting_value)), '/') ?: '/var/lib/freeswitch/storage';
        $base = rtrim(trim((string) ($existing->get('base')?->default_setting_value)), '/') ?: '/usr';
        $missingConf = blank($existing->get('conf')?->default_setting_value);
        $missingStorage = blank($existing->get('storage')?->default_setting_value);

        // Match install_freeswitch.sh's /usr prefix and /etc + /var layout.
        $directories = [
            // Legacy uses PATH on Debian; an empty bin value is intentional.
            'bin' => $base === '/usr/local/freeswitch' ? $base.'/bin' : '',
            'base' => $base,
            'conf' => $conf,
            'db' => '/var/lib/freeswitch/db',
            'grammar' => '/usr/share/freeswitch/grammar',
            'log' => '/var/log/freeswitch',
            'mod' => '/usr/lib/freeswitch/mod',
            'recordings' => '/var/lib/freeswitch/recordings',
            'scripts' => '/usr/share/freeswitch/scripts',
            'sounds' => '/usr/share/freeswitch/sounds',
            'storage' => $storage,
        ];

        $derived = [
            'call_center' => [$conf, '/autoload_configs'],
            'dialplan' => [$conf, '/dialplan'],
            'extensions' => [$conf, '/directory'],
            'languages' => [$conf, '/languages'],
            'sip_profiles' => [$conf, '/sip_profiles'],
            'voicemail' => [$storage, '/voicemail'],
        ];

        foreach ($derived as $name => [$parent, $suffix]) {
            $directories[$name] = $parent.$suffix;
        }

        foreach ($directories as $name => $path) {
            $setting = $existing->get($name);

            if ($setting) {
                // An offline legacy discovery also produced paths such as
                // /languages or /voicemail by appending to an empty parent.
                $emptyParentPath = $derived[$name][1] ?? null;
                $missingParent = $name === 'voicemail' ? $missingStorage : $missingConf;
                if (blank($setting->default_setting_value) || ($missingParent && $setting->default_setting_value === $emptyParentPath)) {
                    $setting->update(['default_setting_value' => $path]);
                }

                continue;
            }

            DefaultSettings::create([
                'default_setting_category' => 'switch',
                'default_setting_subcategory' => $name,
                'default_setting_name' => 'dir',
                'default_setting_value' => $path,
                // These configurations are generated dynamically by the XML handler.
                'default_setting_enabled' => in_array($name, ['call_center', 'dialplan', 'extensions', 'sip_profiles'], true) ? 'false' : 'true',
                'default_setting_description' => '',
            ]);
        }
    }
}
