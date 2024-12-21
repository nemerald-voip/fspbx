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