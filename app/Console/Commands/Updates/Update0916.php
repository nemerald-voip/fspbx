<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use Illuminate\Support\Str;

class Update0916
{
    /**
     * Apply the 0.9.16 update steps.
     *
     * @return bool
     */
    public function apply()
    {
        $updates = [
            [
                'category' => 'mobile_apps',
                'subcategory' => 'windows_link',
                'type' => 'text',
                'value' => '',
                'description' => '',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'apple_store_link',
                'type' => 'text',
                'value' => '',
                'description' => '',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'google_play_link',
                'type' => 'text',
                'value' => '',
                'description' => '',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'mac_link',
                'type' => 'text',
                'value' => '',
                'description' => '',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'organization_region',
                'type' => 'text',
                'value' => '',
                'description' => '1 - US East, 2 - US West, 3 - Europe (Frankfurt), 4 - Asia Pacific (Singapore), 5 - Europe (London), 6 - India, 7 - Australia, 8 - Europe (Dublin), 9 - Canada (Central), 10 - South Africa',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'package',
                'type' => 'text',
                'value' => '',
                'description' => '1 - Essentials, 2 - Pro',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'connection_port',
                'type' => 'text',
                'value' => '',
                'description' => '',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'mobile_app_conn_protocol',
                'type' => 'text',
                'value' => 'sip',
                'description' => 'sip or tcp or sips',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'mobile_app_proxy',
                'type' => 'text',
                'value' => '',
                'description' => '',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'dont_send_user_credentials',
                'type' => 'boolean',
                'value' => 'false',
                'description' => "Don't include user credentials in the welcome email",
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'dont_verify_server_certificate',
                'type' => 'boolean',
                'value' => 'false',
                'description' => '',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'disable_srtp',
                'type' => 'boolean',
                'value' => 'false',
                'description' => '',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'multitenant_mode',
                'type' => 'boolean',
                'value' => 'true',
                'description' => '',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'allow_call_recording',
                'type' => 'boolean',
                'value' => 'false',
                'description' => 'Allow users to record calls.',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'max_registrations',
                'type' => 'text',
                'value' => '3',
                'description' => 'Max. number of parallel registrations per softphone user.',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'registration_ttl',
                'type' => 'text',
                'value' => '3600',
                'description' => '',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'voicemail_extension',
                'type' => 'text',
                'value' => '*97',
                'description' => '',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'pbx_features',
                'type' => 'boolean',
                'value' => 'true',
                'description' => '',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'dnd_on_code',
                'type' => 'text',
                'value' => '*78',
                'description' => '',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'dnd_off_code',
                'type' => 'text',
                'value' => '*79',
                'description' => '',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'cf_on_code',
                'type' => 'text',
                'value' => '*72',
                'description' => 'The feature code used to activate CF',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'cf_off_code',
                'type' => 'text',
                'value' => '*73',
                'description' => 'The feature code used to deactivate CF',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'g711u_enabled',
                'type' => 'boolean',
                'value' => 'true',
                'description' => 'Enable G711 Ulaw codec',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'g711a_enabled',
                'type' => 'boolean',
                'value' => 'true',
                'description' => 'Enable G711 Alaw codec',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'g729_enabled',
                'type' => 'boolean',
                'value' => 'false',
                'description' => 'Enable G729 codec',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'opus_enabled',
                'type' => 'boolean',
                'value' => 'false',
                'description' => 'Enable OPUS codec',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'show_call_settings',
                'type' => 'boolean',
                'value' => 'true',
                'description' => 'Allow users to configure call settings from within the app, such as call forwarding, voicemail, call waiting.',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'allow_state_change',
                'type' => 'boolean',
                'value' => 'true',
                'description' => 'Allow users to change their state from the app, such as Online/DND/At the desk.',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'allow_video_calls',
                'type' => 'boolean',
                'value' => 'true',
                'description' => 'Allow users to make 1-on-1 video calls.',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'allow_internal_chat',
                'type' => 'boolean',
                'value' => 'true',
                'description' => 'Allow users to use internal chat feature and create new chats.',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'disable_iphone_recents',
                'type' => 'boolean',
                'value' => 'false',
                'description' => "If enabled, this option disables call history syncing in iPhone Recents and hides the 'Show calls in iPhone Recents' option from the app's settings.",
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'app_opus_codec',
                'type' => 'boolean',
                'value' => 'true',
                'description' => 'Enable the OPUS audio codec between the softphone apps and a softphone server',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'one_push',
                'type' => 'boolean',
                'value' => 'false',
                'description' => "Don't send a second push notification in the case of the user's mobile app was not waked up by the first one.",
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'call_delay',
                'type' => 'text',
                'value' => '10',
                'description' => "Call Delay for 'At the Desk' Status (Seconds)",
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'desktop_app_delay',
                'type' => 'boolean',
                'value' => 'false',
                'description' => "Delay incoming calls to the desktop app",
            ],
        ];

        try {
            foreach ($updates as $update) {
                $this->updateOrCreateRecord(
                    $update['category'],
                    $update['subcategory'],
                    $update['type'],
                    $update['value'],
                    $update['description']
                );
            }

            return true;
        } catch (\Exception $e) {
            echo "Error applying update 0.9.16: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Update or create a record in the DefaultSettings table.
     *
     * @param string $category
     * @param string $subcategory
     * @param string $description
     * @return void
     */
    private function updateOrCreateRecord($category, $subcategory, $type, $value, $description)
    {
        $defaultSetting = DefaultSettings::where('default_setting_category', $category)
            ->where('default_setting_subcategory', $subcategory)
            ->first();

        if ($defaultSetting) {
            // Update the description if the record exists
            $defaultSetting->default_setting_description = $description;
            $defaultSetting->save();
            echo "Updated existing record in category '$category' and subcategory '$subcategory'.\n";
        } else {
            // Create a new record if it does not exist
            DefaultSettings::create([
                'default_setting_uuid' => Str::uuid()->toString(), // Generate a new UUID
                'default_setting_category' => $category,
                'default_setting_subcategory' => $subcategory,
                'default_setting_name' => $type,
                'default_setting_value' => $value,
                'default_setting_enabled' => 't',
                'default_setting_description' => $description,
            ]);
            echo "Created new record in category '$category' and subcategory '$subcategory'.\n";
        }
    }
}
