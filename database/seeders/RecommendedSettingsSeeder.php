<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RecommendedSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createDefaultSettings();
    }

    private function createDefaultSettings()
    {
        $settings = [
            [
                'default_setting_category'      => 'destinations',
                'default_setting_subcategory'   => 'dialplan_mode',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "single",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Options: multiple, single",
            ],
            // Add more settings here...
        ];

        foreach ($settings as $setting) {
            // Check for existing setting
            $existing_item = DefaultSettings::where('default_setting_category', $setting['default_setting_category'])
                ->where('default_setting_subcategory', $setting['default_setting_subcategory'])
                ->first();

            if ($existing_item) {
                // Delete the existing item
                $existing_item->delete();
            }

            // Recreate the setting
            DefaultSettings::create([
                'default_setting_category'      => $setting['default_setting_category'],
                'default_setting_subcategory'   => $setting['default_setting_subcategory'],
                'default_setting_name'          => $setting['default_setting_name'],
                'default_setting_value'         => $setting['default_setting_value'],
                'default_setting_enabled'       => $setting['default_setting_enabled'],
                'default_setting_description'   => $setting['default_setting_description'],
            ]);
        }
    }
}
