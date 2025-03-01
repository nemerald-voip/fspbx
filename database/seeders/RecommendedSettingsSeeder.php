<?php

namespace Database\Seeders;

use App\Models\DefaultSettings;
use Illuminate\Database\Seeder;
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
                'default_setting_enabled'       => false,
                'default_setting_description'   => "Send a T38 reinvite when a fax tone is detected.",
            ],
            [
                'default_setting_category'      => 'fax',
                'default_setting_subcategory'   => 'variable',
                'default_setting_name'          => 'array',
                'default_setting_value'         => 'rtp_secure_media_outbound=forbidden',
                'default_setting_enabled'       => true,
                'default_setting_description'   => "Send a T38 reinvite when a fax tone is detected.",
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
            
            // Add more settings here...
        ];

        foreach ($settings as $setting) {
            // Check for existing setting
            $existing_item = DefaultSettings::where('default_setting_category', $setting['default_setting_category'])
                ->where('default_setting_subcategory', $setting['default_setting_subcategory'])
                ->where('default_setting_value', $setting['default_setting_value'])
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

    public function getMacAddress()
    {
        // Run the shell command using Process
        $process = Process::run("ip link show | grep 'link/ether' | awk '{print $2}' | head -n 1");

        // Get the output from the process
        $macAddress = trim($process->output());

        return $macAddress ?: null;
    }
}
