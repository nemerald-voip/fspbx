<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use Illuminate\Support\Str;

class Update0918
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
                'category'      => 'authentication',
                'subcategory'   => 'email_challenge',
                'type'          => 'boolean',
                'value'         => "true",
                'description'   => "Enable or disable email challenge authentication. When enabled, users will be required to verify their email before completing the login process.",
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
                'default_setting_enabled' => 'f',
                'default_setting_description' => $description,
            ]);
            echo "Created new record in category '$category' and subcategory '$subcategory'.\n";
        }
    }
}
