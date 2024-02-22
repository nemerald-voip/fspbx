<?php

namespace Database\Seeders;

use App\Models\DefaultSettings;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        $this->createMobileAppSettings();

        Model::reguard();

    }

    private function createMobileAppSettings()
    {
        $settings = [
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'apple_store_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'google_play_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'mac_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'windows_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'mobile_app_conn_protocol',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "sip or tcp or sips",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'mobile_app_proxy',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'organization_region',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "1 - US East, 2 - US West, 3 - Europe (Frankfurt), 4 - Asia Pacific (Singapore), 5 - Europe (London)",
            ],



        ];

        // Log::alert(Category::where('name', trans('custom-fields::general.categories.cost_recovery'))->where('company_id', $company_id)->value('id'));
        foreach ($settings as $setting) {
            $existing_item = DefaultSettings::where('default_setting_category', $setting['default_setting_category'])
                ->where('default_setting_subcategory', $setting['default_setting_subcategory'])
                ->first();

            if (empty($existing_item)) {
                // Add new group
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
}
