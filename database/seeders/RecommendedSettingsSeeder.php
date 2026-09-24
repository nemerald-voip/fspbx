<?php

namespace Database\Seeders;

use App\Models\DefaultSettings;
use Illuminate\Database\Seeder;


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
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'allowed_extension',
                'default_setting_name'          => 'array',
                'default_setting_value'         => '.csv',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'allowed_extension',
                'default_setting_name'          => 'array',
                'default_setting_value'         => '.jpeg',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'allowed_extension',
                'default_setting_name'          => 'array',
                'default_setting_value'         => '.jpg',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'allowed_extension',
                'default_setting_name'          => 'array',
                'default_setting_value'         => '.docx',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'allowed_extension',
                'default_setting_name'          => 'array',
                'default_setting_value'         => '.doc',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'allowed_extension',
                'default_setting_name'          => 'array',
                'default_setting_value'         => '.rtf',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'allowed_extension',
                'default_setting_name'          => 'array',
                'default_setting_value'         => '.txt',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'allowed_extension',
                'default_setting_name'          => 'array',
                'default_setting_value'         => '.xls',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'allowed_extension',
                'default_setting_name'          => 'array',
                'default_setting_value'         => '.xlsx',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'allowed_extension',
                'default_setting_name'          => 'array',
                'default_setting_value'         => '.pdf',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'fax_slack_notification',
                'default_setting_name'          => 'text',
                'default_setting_value'         => 'errors',
                'default_setting_enabled'       => false,
                'default_setting_description'   => "all - send all. errors - send errors only",
            ],
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'variable',
                'default_setting_name'          => 'array',
                'default_setting_value'         => 'fax_enable_t38_request=true',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Send a T38 reinvite when a fax tone is detected.",
            ],
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'variable',
                'default_setting_name'          => 'array',
                'default_setting_value'         => 'fax_enable_t38=true',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enable T.38.",
            ],
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'variable',
                'default_setting_name'          => 'array',
                'default_setting_value'         => 'rtp_secure_media_outbound=forbidden',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Dissallow strp for outbound calls",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'enabled',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enable device provisioning service",
            ],
            [
                'default_setting_category'      => 'provision',
                'default_setting_subcategory'   => 'http_domain_filter',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "false",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Filter by domain",
            ],
            [
                'default_setting_category'      => 'voicemail',
                'default_setting_subcategory'   => 'transcribe_enabled',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enable voicemail transcriptions",
            ],
            [
                'default_setting_category'      => 'voicemail',
                'default_setting_subcategory'   => 'voicemail_queue_strategy',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "modern",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "modern: enables the new, optimized queue handling with improved performance and stability. legacy: uses the original queue behavior for backward compatibility.",
            ],
            [
                'default_setting_category'      => 'theme',
                'default_setting_subcategory'   => 'footer',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "© Copyright 2008 - 2024 FS PBX. All rights reserved.",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],

            // Add more settings here...
        ];

        foreach ($settings as $setting) {
            // Check for existing setting
            $existing_item = DefaultSettings::where('default_setting_category', $setting['default_setting_category'])
                ->where('default_setting_subcategory', $setting['default_setting_subcategory'])
                ->where('default_setting_name', '!=', 'array')
                ->get();

            // Delete the existing items
            $existing_item->each->delete();

            // Recreate the setting
            DefaultSettings::create([
                'default_setting_category'      => $setting['default_setting_category'],
                'default_setting_subcategory'   => $setting['default_setting_subcategory'],
                'default_setting_name'          => $setting['default_setting_name'],
                'default_setting_value'         => $setting['default_setting_value'],
                'default_setting_enabled'       => $setting['default_setting_enabled'],
                'default_setting_description'   => $setting['default_setting_description'],
                'insert_date'                   => now(),
            ]);
        }
    }

}
