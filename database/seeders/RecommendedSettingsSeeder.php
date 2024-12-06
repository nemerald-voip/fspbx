<?php

namespace Database\Seeders;

use App\Models\DefaultSettings;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RecommendedSettingsSeeder extends Seeder
{
    // Run this command in console to apply
    // php artisan db:seed --class=RecommendedSettingsSeeder

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
            [
                'default_setting_category'      => 'destinations',
                'default_setting_subcategory'   => 'select_mode',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "dynamic",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Options: default, dynamic",
            ],
            [
                'default_setting_category'      => 'dialplan',
                'default_setting_subcategory'   => 'destination',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '${sip_req_user}',
                'default_setting_enabled'       => true,
                'default_setting_description'   => 'Options: destination_number, ${sip_to_user}, ${sip_req_user}',
            ],
            [
                'default_setting_category'      => 'email',
                'default_setting_subcategory'   => 'email_company_address',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '',
                'default_setting_enabled'       => true,
                'default_setting_description'   => '',
            ],
            [
                'default_setting_category'      => 'email',
                'default_setting_subcategory'   => 'email_company_name',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '',
                'default_setting_enabled'       => true,
                'default_setting_description'   => '',
            ],
            [
                'default_setting_category'      => 'email',
                'default_setting_subcategory'   => 'help_url',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '',
                'default_setting_enabled'       => true,
                'default_setting_description'   => '',
            ],
            [
                'default_setting_category'      => 'email',
                'default_setting_subcategory'   => 'support_email',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '',
                'default_setting_enabled'       => true,
                'default_setting_description'   => '',
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'apple_store_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '',
                'default_setting_enabled'       => true,
                'default_setting_description'   => '',
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'google_play_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '',
                'default_setting_enabled'       => true,
                'default_setting_description'   => '',
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'mac_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '',
                'default_setting_enabled'       => true,
                'default_setting_description'   => '',
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'windows_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '',
                'default_setting_enabled'       => true,
                'default_setting_description'   => '',
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'dont_send_user_credentials',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'false',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Don't include user credentials in the welcome email",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'mobile_app_conn_protocol',
                'default_setting_name'          => 'text',
                'default_setting_value'         => 'sips',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Options: sip or tcp or sips",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'organization_region',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "1 - US East, 2 - US West, 3 - Europe (Frankfurt), 4 - Asia Pacific (Singapore), 5 - Europe (London)",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'favicon',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '/storage/favicon.ico',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'logo',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '/storage/logo.png',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'menu_brand_image',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '/storage/logo.png',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'menu_main_background_color',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '#546ee5',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Set a background color (and opacity) of the main menu bar.",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'menu_main_background_color_hover',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '#546ee5',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Set a background hover color (and opacity) of the main menu items.",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'menu_main_shadow_color',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '#4e73df',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Set the shadow color (and opacity) of the main menu bar.",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'menu_main_text_color',
                'default_setting_name'          => 'text',
                'default_setting_value'         => 'rgba(255,255,255,0.55)',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Set the text color of the main menu items.",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'menu_main_text_size',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '11pt',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Set the text size of the main menu items.",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'menu_sub_background_color',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '#ffffff',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Set the background color (and opacity) of the sub menus.",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'menu_sub_background_color_hover',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '#eef2f7',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Set the hover background color (and opacity) of the sub menu items.",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'menu_sub_shadow_color',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '#eef2f7',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Set the shadow color (and opacity) of sub menus.",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'menu_sub_text_color',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '#6c757d',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Set the text color (and opacity) of sub menu items.",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'menu_sub_text_color_hover',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '#000000',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Set the hover text color (and opacity) of sub menu items.",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'menu_sub_text_size',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '11pt',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Set the text size of the sub menu items.",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'title',
                'default_setting_name'          => 'text',
                'default_setting_value'         => 'FS PBX',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Set the hover logo title.",
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
