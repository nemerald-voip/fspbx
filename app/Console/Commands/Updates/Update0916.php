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
                'newDescription' => '1 - US East, 2 - US West, 3 - Europe (Frankfurt), 4 - Asia Pacific (Singapore), 5 - Europe (London), 6 - India, 7 - Australia, 8 - Europe (Dublin), 9 - Canada (Central), 10 - South Africa',
            ],
            [
                'category' => 'mobile_apps',
                'subcategory' => 'package',
                'newDescription' => '1 - Essentials, 2 - Pro',
            ],
        ];

        try {
            foreach ($updates as $update) {
                $this->updateOrCreateRecord(
                    $update['category'],
                    $update['subcategory'],
                    $update['newDescription']
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
     * @param string $newDescription
     * @return void
     */
    private function updateOrCreateRecord($category, $subcategory, $newDescription)
    {
        $defaultSetting = DefaultSettings::where('default_setting_category', $category)
            ->where('default_setting_subcategory', $subcategory)
            ->first();

        if ($defaultSetting) {
            // Update the description if the record exists
            $defaultSetting->default_setting_description = $newDescription;
            $defaultSetting->save();
            echo "Updated description for existing record in category '$category' and subcategory '$subcategory'.\n";
        } else {
            // Create a new record if it does not exist
            DefaultSettings::create([
                'default_setting_uuid' => Str::uuid()->toString(), // Generate a new UUID
                'default_setting_category' => $category,
                'default_setting_subcategory' => $subcategory,
                'default_setting_name' => 'text',
                'default_setting_value' => '',
                'default_setting_enabled' => 't',
                'default_setting_description' => $newDescription,
            ]);
            echo "Created new record in category '$category' and subcategory '$subcategory'.\n";
        }
    }
}
