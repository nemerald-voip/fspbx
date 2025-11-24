<?php

namespace Database\Seeders;

use App\Models\DefaultSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;


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

        $this->updateEmailTemplate();
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
                'default_setting_value'         => 'sip',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Options: sip or sip-tcp or sips or DNS-NAPTR",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'organization_region',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "1 - US East, 2 - US West, 3 - Europe (Frankfurt), 4 - Asia Pacific (Singapore), 5 - Europe (London), 6 - India, 7 - Australia, 8 - Europe (Dublin), 9 - Canada (Central), 10 - South Africa",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'package',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '1',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "1 - Essentials, 2 - Pro",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'connection_port',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'dont_verify_server_certificate',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'false',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'disable_srtp',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'false',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'multitenant_mode',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'false',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'allow_call_recording',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'false',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'max_registrations',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '3',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Max. number of parallel registrations per softphone user.",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'registration_ttl',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '3600',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'voicemail_extension',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '*97',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'pbx_features',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'true',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'dnd_on_code',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '*78',
                'default_setting_enabled'       => false,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'dnd_off_code',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '*79',
                'default_setting_enabled'       => false,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'cf_on_code',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '*72',
                'default_setting_enabled'       => false,
                'default_setting_description'   => "The feature code used to activate CF",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'cf_off_code',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '*73',
                'default_setting_enabled'       => false,
                'default_setting_description'   => "The feature code used to deactivate CF",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'g711u_enabled',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'true',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enable G711 Ulaw codec",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'g711a_enabled',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'true',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enable G711 Alaw codec",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'g729_enabled',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'false',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enable G729 codec",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'opus_enabled',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'false',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enable OPUS codec",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'show_call_settings',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'true',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Allow users to configure call settings from within the app, such as call forwarding, voicemail, call waiting.",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'allow_state_change',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'true',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Allow users to change their state from the app, such as Online/DND/At the desk.",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'allow_video_calls',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'true',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Allow users to make 1-on-1 video calls.",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'allow_internal_chat',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'true',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Allow users to use internal chat feature and create new chats.",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'disable_iphone_recents',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'true',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "If enabled, this option disables call history syncing in iPhone Recents and hides the 'Show calls in iPhone Recents' option from the app's settings.",
            ],

            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'app_opus_codec',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'true',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enable the OPUS audio codec between the softphone apps and a softphone server.",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'one_push',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'false',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Don't send a second push notification in the case of the user's mobile app was not waked up by the first one.",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'call_delay',
                'default_setting_name'          => 'text',
                'default_setting_value'         => '10',
                'default_setting_enabled'       => false,
                'default_setting_description'   => "Delay incoming calls to the desktop app",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'desktop_app_delay',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => 'false',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Delay incoming calls to the desktop app",
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
                'default_setting_value'         => 'rtp_secure_media_outbound=forbidden',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Dissallow strp for outbound calls",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'aws_upload_calls_' . $this->getMacAddress(),
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => false,
                'default_setting_description'   => "Executes upload job only on the server with MAC address " . $this->getMacAddress(),
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'clear_export_directory',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'horizon_snapshot',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'horizon_check_status',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'cache_prune_stale_tags',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'prune_old_webhook_requests',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'backup',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => false,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'backup_path',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "/var/backups/fspbx",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'check_fax_service_status',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "false",
                'default_setting_enabled'       => false,
                'default_setting_description'   => "Monitors pending faxes and identifies those exceeding the allowed threshold.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'fax_service_threshold',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "5",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Defines the maximum number of pending faxes allowed before exceeding the threshold.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'fax_wait_time_threshold',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "60",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Specifies the number of minutes a fax can remain in waiting status before being counted against the threshold.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'fax_service_notify_email',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Email address to receive notifications when pending faxes exceed the allowed wait time threshold.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'audit_stale_ringotel_users',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "false",
                'default_setting_enabled'       => false,
                'default_setting_description'   => "Enables checking for stale Ringotel users based on last active time.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'ringotel_audit_notify_email',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Email address to receive notifications for stale Ringotel users.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'stale_ringotel_users_threshold',
                'default_setting_name'          => 'numeric',
                'default_setting_value'         => "180",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Defines the number of days after which a Ringotel user is considered stale.",
            ],
            [
                'default_setting_category'      => 'scheduled_jobs',
                'default_setting_subcategory'   => 'wake_up_calls',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "false",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enable or disable the processing of scheduled wake-up calls. If set to 'false', scheduled calls will not be executed.",
            ],
            [
                'default_setting_category'      => 'authentication',
                'default_setting_subcategory'   => 'email_challenge',
                'default_setting_name'          => 'boolean',
                'default_setting_value'         => "true",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Enable or disable email challenge authentication. When enabled, users will be required to verify their email before completing the login process.",
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

    /**
     * Update the voicemail transcription email template in the database.
     *
     * @return void
     */
    protected function updateEmailTemplate()
    {
        $appName = config('app.name');

        $newTemplateBody = <<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="x-apple-disable-message-reformatting" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="color-scheme" content="light dark" />
    <meta name="supported-color-schemes" content="light dark" />
    <title></title>
    <style type="text/css" rel="stylesheet" media="all">
        @import url("https://fonts.googleapis.com/css?family=Nunito+Sans:400,700&display=swap");
        body { width: 100% !important; height: 100%; margin: 0; -webkit-text-size-adjust: none; }
        a { color: #3869D4; }
        a img { border: none; }
        td { word-break: break-word; }
        .preheader { display: none !important; visibility: hidden; mso-hide: all; font-size: 1px; line-height: 1px; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; }
        body, td, th { font-family: "Nunito Sans", Helvetica, Arial, sans-serif; }
        h1 { margin-top: 0; color: #333333; font-size: 22px; font-weight: bold; text-align: left; }
        h2 { margin-top: 0; color: #333333; font-size: 16px; font-weight: bold; text-align: left; }
        h3 { margin-top: 0; color: #333333; font-size: 14px; font-weight: bold; text-align: left; }
        td, th { font-size: 16px; }
        p, ul, ol, blockquote { margin: .4em 0 1.1875em; font-size: 16px; line-height: 1.625; }
        p.sub { font-size: 13px; }
        .align-right { text-align: right; }
        .align-left { text-align: left; }
        .align-center { text-align: center; }
        .button { background-color: #3869D4; border-top: 10px solid #3869D4; border-right: 18px solid #3869D4; border-bottom: 10px solid #3869D4; border-left: 18px solid #3869D4; display: inline-block; color: #FFF; text-decoration: none; border-radius: 3px; box-shadow: 0 2px 3px rgba(0, 0, 0, 0.16); -webkit-text-size-adjust: none; box-sizing: border-box; }
        .button--green { background-color: #22BC66; border-top: 10px solid #22BC66; border-right: 18px solid #22BC66; border-bottom: 10px solid #22BC66; border-left: 18px solid #22BC66; }
        .button--red { background-color: #FF6136; border-top: 10px solid #FF6136; border-right: 18px solid #FF6136; border-bottom: 10px solid #FF6136; border-left: 18px solid #FF6136; }
        @media only screen and (max-width: 500px) {
            .button { width: 100% !important; text-align: center !important; }
        }
        .attributes { margin: 0 0 21px; }
        .attributes_content { background-color: #F4F4F7; padding: 16px; }
        .attributes_item { padding: 0; }
        .related { width: 100%; margin: 0; padding: 25px 0 0 0; }
        .related_item { padding: 10px 0; color: #CBCCCF; font-size: 15px; line-height: 18px; }
        .related_item-title { display: block; margin: .5em 0 0; }
        .related_item-thumb { display: block; padding-bottom: 10px; }
        .related_heading { border-top: 1px solid #CBCCCF; text-align: center; padding: 25px 0 10px; }
        .discount { width: 100%; margin: 0; padding: 24px; background-color: #F4F4F7; border: 2px dashed #CBCCCF; }
        .discount_heading { text-align: center; }
        .discount_body { text-align: center; font-size: 15px; }
        .social { width: auto; }
        .social td { padding: 0; width: auto; }
        .social_icon { height: 20px; margin: 0 8px 10px 8px; padding: 0; }
        .purchase { width: 100%; margin: 0; padding: 35px 0; }
        .purchase_content { width: 100%; margin: 0; padding: 25px 0 0 0; }
        .purchase_item { padding: 10px 0; color: #51545E; font-size: 15px; line-height: 18px; }
        .purchase_heading { padding-bottom: 8px; border-bottom: 1px solid #EAEAEC; }
        .purchase_heading p { margin: 0; color: #85878E; font-size: 12px; }
        .purchase_footer { padding-top: 15px; border-top: 1px solid #EAEAEC; }
        .purchase_total { margin: 0; text-align: right; font-weight: bold; color: #333333; }
        .purchase_total--label { padding: 0 15px 0 0; }
        body { background-color: #F2F4F6; color: #51545E; }
        p { color: #51545E; }
        .email-wrapper { width: 100%; margin: 0; padding: 0; background-color: #F2F4F6; }
        .email-content { width: 100%; margin: 0; padding: 0; }
        .email-masthead { padding: 25px 0; text-align: center; }
        .email-masthead_logo { width: 94px; }
        .email-masthead_name { font-size: 16px; font-weight: bold; color: #A8AAAF; text-decoration: none; text-shadow: 0 1px 0 white; }
        .email-body { width: 100%; margin: 0; padding: 0; }
        .email-body_inner { width: 570px; margin: 0 auto; padding: 0; background-color: #FFFFFF; }
        .email-footer { width: 570px; margin: 0 auto; padding: 0; text-align: center; }
        .email-footer p { color: #A8AAAF; }
        .body-action { width: 100%; margin: 30px auto; padding: 0; text-align: center; }
        .body-sub { margin-top: 25px; padding-top: 25px; border-top: 1px solid #EAEAEC; }
        .content-cell { padding: 45px; }
        @media only screen and (max-width: 600px) {
            .email-body_inner, .email-footer { width: 100% !important; }
        }
        @media (prefers-color-scheme: dark) {
            body, .email-body, .email-body_inner, .email-content, .email-wrapper, .email-masthead, .email-footer { background-color: #333333 !important; color: #FFF !important; }
            p, ul, ol, blockquote, h1, h2, h3, span, .purchase_item { color: #FFF !important; }
            .attributes_content, .discount { background-color: #222 !important; }
            .email-masthead_name { text-shadow: none !important; }
        }
        :root { color-scheme: light dark; supported-color-schemes: light dark; }
    </style>
    <!--[if mso]>
    <style type="text/css">
      .f-fallback  {
        font-family: Arial, sans-serif;
      }
    </style>
    <![endif]-->
</head>
<body>
    <table class="email-wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center">
                <table class="email-content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td class="email-masthead">
                            <a href="" class="f-fallback email-masthead_name">
                                {$appName}
                            </a>
                        </td>
                    </tr>
                    <!-- Email Body -->
                    <tr>
                        <td class="email-body" width="570" cellpadding="0" cellspacing="0">
                            <table class="email-body_inner" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
                                <!-- Body content -->
                                <tr>
                                    <td class="content-cell">
                                        <div class="f-fallback">
                                            <p>You have a new voice message:</p>
                                            <table class="attributes" width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td class="attributes_content">
                                                        <table width="100%" cellpadding="0" cellspacing="0">
                                                            <tr>
                                                                <td class="attributes_item"><strong>From:</strong> \${caller_id_name} \${caller_id_number} </td>
                                                            </tr>
                                                            <tr>
                                                                <td class="attributes_item"><strong>To mailbox:</strong> \${dialed_user}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="attributes_item"><strong>Received:</strong> \${message_date}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="attributes_item"><strong>Length:</strong> \${message_duration}</td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                            <p><strong>Voicemail Preview:</strong></p>
                                            <table class="attributes" width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td class="attributes_content">
                                                        <table width="100%" cellpadding="0" cellspacing="0">
                                                            <tr>
                                                                <td class="attributes_item">\${message_text}</td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                            <p>Listen to this voicemail over your phone or by opening the attached sound file. You can also sign in to your account with your credentials to manage and listen to voicemails.</p>
                                            <p>If you have any questions, email our customer success team. (We're lightning quick at replying.)</p>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table class="email-footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td class="content-cell" align="center">
                                        <p class="f-fallback sub align-center">© {$appName}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
EOT;

        $oldTemplateBody = <<<EOT
<html>
<body>
Voicemail from \${caller_id_name} <a href="tel:\${caller_id_number}">\${caller_id_number}</a><br />
<br />
To \${voicemail_name_formatted}<br />
Received \${message_date}<br />
Length \${message_duration}<br />
Message \${message}<br />
<br />
Transcription<br />
\${message_text}
</body>
</html>
EOT;

        // Replace {$appName} placeholders with the actual app name
        $newTemplateBody = str_replace('{$appName}', $appName, $newTemplateBody);

        DB::table('v_email_templates')
            ->where('template_category', 'voicemail')
            ->where('template_subcategory', 'transcription')
            ->where('template_body', $oldTemplateBody)
            ->update([
                'template_body' => $newTemplateBody
            ]);

        echo "Voicemail transcription email template updated successfully.\n";

        $newTemplateBody = <<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="x-apple-disable-message-reformatting" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="color-scheme" content="light dark" />
    <meta name="supported-color-schemes" content="light dark" />
    <title></title>
    <style type="text/css" rel="stylesheet" media="all">
        @import url("https://fonts.googleapis.com/css?family=Nunito+Sans:400,700&display=swap");
        body { width: 100% !important; height: 100%; margin: 0; -webkit-text-size-adjust: none; }
        a { color: #3869D4; }
        a img { border: none; }
        td { word-break: break-word; }
        .preheader { display: none !important; visibility: hidden; mso-hide: all; font-size: 1px; line-height: 1px; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; }
        body, td, th { font-family: "Nunito Sans", Helvetica, Arial, sans-serif; }
        h1 { margin-top: 0; color: #333333; font-size: 22px; font-weight: bold; text-align: left; }
        h2 { margin-top: 0; color: #333333; font-size: 16px; font-weight: bold; text-align: left; }
        h3 { margin-top: 0; color: #333333; font-size: 14px; font-weight: bold; text-align: left; }
        td, th { font-size: 16px; }
        p, ul, ol, blockquote { margin: .4em 0 1.1875em; font-size: 16px; line-height: 1.625; }
        p.sub { font-size: 13px; }
        .align-right { text-align: right; }
        .align-left { text-align: left; }
        .align-center { text-align: center; }
        .button { background-color: #3869D4; border-top: 10px solid #3869D4; border-right: 18px solid #3869D4; border-bottom: 10px solid #3869D4; border-left: 18px solid #3869D4; display: inline-block; color: #FFF; text-decoration: none; border-radius: 3px; box-shadow: 0 2px 3px rgba(0, 0, 0, 0.16); -webkit-text-size-adjust: none; box-sizing: border-box; }
        .button--green { background-color: #22BC66; border-top: 10px solid #22BC66; border-right: 18px solid #22BC66; border-bottom: 10px solid #22BC66; border-left: 18px solid #22BC66; }
        .button--red { background-color: #FF6136; border-top: 10px solid #FF6136; border-right: 18px solid #FF6136; border-bottom: 10px solid #FF6136; border-left: 18px solid #FF6136; }
        @media only screen and (max-width: 500px) {
            .button { width: 100% !important; text-align: center !important; }
        }
        .attributes { margin: 0 0 21px; }
        .attributes_content { background-color: #F4F4F7; padding: 16px; }
        .attributes_item { padding: 0; }
        .related { width: 100%; margin: 0; padding: 25px 0 0 0; }
        .related_item { padding: 10px 0; color: #CBCCCF; font-size: 15px; line-height: 18px; }
        .related_item-title { display: block; margin: .5em 0 0; }
        .related_item-thumb { display: block; padding-bottom: 10px; }
        .related_heading { border-top: 1px solid #CBCCCF; text-align: center; padding: 25px 0 10px; }
        .discount { width: 100%; margin: 0; padding: 24px; background-color: #F4F4F7; border: 2px dashed #CBCCCF; }
        .discount_heading { text-align: center; }
        .discount_body { text-align: center; font-size: 15px; }
        .social { width: auto; }
        .social td { padding: 0; width: auto; }
        .social_icon { height: 20px; margin: 0 8px 10px 8px; padding: 0; }
        .purchase { width: 100%; margin: 0; padding: 35px 0; }
        .purchase_content { width: 100%; margin: 0; padding: 25px 0 0 0; }
        .purchase_item { padding: 10px 0; color: #51545E; font-size: 15px; line-height: 18px; }
        .purchase_heading { padding-bottom: 8px; border-bottom: 1px solid #EAEAEC; }
        .purchase_heading p { margin: 0; color: #85878E; font-size: 12px; }
        .purchase_footer { padding-top: 15px; border-top: 1px solid #EAEAEC; }
        .purchase_total { margin: 0; text-align: right; font-weight: bold; color: #333333; }
        .purchase_total--label { padding: 0 15px 0 0; }
        body { background-color: #F2F4F6; color: #51545E; }
        p { color: #51545E; }
        .email-wrapper { width: 100%; margin: 0; padding: 0; background-color: #F2F4F6; }
        .email-content { width: 100%; margin: 0; padding: 0; }
        .email-masthead { padding: 25px 0; text-align: center; }
        .email-masthead_logo { width: 94px; }
        .email-masthead_name { font-size: 16px; font-weight: bold; color: #A8AAAF; text-decoration: none; text-shadow: 0 1px 0 white; }
        .email-body { width: 100%; margin: 0; padding: 0; }
        .email-body_inner { width: 570px; margin: 0 auto; padding: 0; background-color: #FFFFFF; }
        .email-footer { width: 570px; margin: 0 auto; padding: 0; text-align: center; }
        .email-footer p { color: #A8AAAF; }
        .body-action { width: 100%; margin: 30px auto; padding: 0; text-align: center; }
        .body-sub { margin-top: 25px; padding-top: 25px; border-top: 1px solid #EAEAEC; }
        .content-cell { padding: 45px; }
        @media only screen and (max-width: 600px) {
            .email-body_inner, .email-footer { width: 100% !important; }
        }
        @media (prefers-color-scheme: dark) {
            body, .email-body, .email-body_inner, .email-content, .email-wrapper, .email-masthead, .email-footer { background-color: #333333 !important; color: #FFF !important; }
            p, ul, ol, blockquote, h1, h2, h3, span, .purchase_item { color: #FFF !important; }
            .attributes_content, .discount { background-color: #222 !important; }
            .email-masthead_name { text-shadow: none !important; }
        }
        :root { color-scheme: light dark; supported-color-schemes: light dark; }
    </style>
    <!--[if mso]>
    <style type="text/css">
      .f-fallback  {
        font-family: Arial, sans-serif;
      }
    </style>
    <![endif]-->
</head>
<body>
    <table class="email-wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center">
                <table class="email-content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td class="email-masthead">
                            <a href="" class="f-fallback email-masthead_name">
                                {$appName}
                            </a>
                        </td>
                    </tr>
                    <!-- Email Body -->
                    <tr>
                        <td class="email-body" width="570" cellpadding="0" cellspacing="0">
                            <table class="email-body_inner" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
                                <!-- Body content -->
                                <tr>
                                    <td class="content-cell">
                                        <div class="f-fallback">
                                            <p>You have a new voice message:</p>
                                            <table class="attributes" width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td class="attributes_content">
                                                        <table width="100%" cellpadding="0" cellspacing="0">
                                                            <tr>
                                                                <td class="attributes_item"><strong>From:</strong> \${caller_id_name} \${caller_id_number} </td>
                                                            </tr>
                                                            <tr>
                                                                <td class="attributes_item"><strong>To mailbox:</strong> \${dialed_user}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="attributes_item"><strong>Received:</strong> \${message_date}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="attributes_item"><strong>Length:</strong> \${message_duration}</td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                            
                                            <p>Listen to this voicemail over your phone or by opening the attached sound file. You can also sign in to your account with your credentials to manage and listen to voicemails.</p>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table class="email-footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td class="content-cell" align="center">
                                        <p class="f-fallback sub align-center">© {$appName}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
EOT;

        $oldTemplateBody = <<<EOT
<html>
<body>
Voicemail from \${caller_id_name} <a href="tel:\${caller_id_number}">\${caller_id_number}</a><br />
<br />
To \${voicemail_name_formatted}<br />
Received \${message_date}<br />
Length \${message_duration}<br />
Message \${message}<br />
</body>
</html>
EOT;

        // Replace {$appName} placeholders with the actual app name
        $newTemplateBody = str_replace('{$appName}', $appName, $newTemplateBody);

        DB::table('v_email_templates')
            ->where('template_category', 'voicemail')
            ->where('template_subcategory', 'default')
            ->where('template_body', $oldTemplateBody)
            ->update([
                'template_body' => $newTemplateBody
            ]);

        echo "Voicemail email template updated successfully.\n";
    }

    public function getMacAddress()
    {
        // Run the shell command using Process
        $process = Process::run("ip link show | grep 'link/ether' | awk '{print $2}' | head -n 1");

        // Get the output from the process
        $macAddress = trim($process->output());

        return $macAddress ?: null;
    }
}
