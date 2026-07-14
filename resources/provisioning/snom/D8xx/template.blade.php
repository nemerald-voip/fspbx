{{-- version: 1.0.2 --}}

@switch($flavor)

{{-- ================= Snom D8xx mac.xml ================= --}}
@case('mac.xml')

<?xml version="1.0" encoding="utf-8"?>
@php
    $boolOn = static function ($value, bool $default = false): string {
        if ($value === null || $value === '') {
            return $default ? 'on' : 'off';
        }

        if (is_bool($value)) {
            return $value ? 'on' : 'off';
        }

        $normalized = strtolower((string) $value);
        return in_array($normalized, ['1', 'on', 'yes', 'true', 'enabled'], true) ? 'on' : 'off';
    };

    $snomDefaultTr69Params = ';Device.X_Snom_FirmwareVersion;Device.UserInterface.AutoUpdateServer;Device.X_Snom_ToneScheme;Device.Time.LocalTimeZone;Device.UserInterface.X_Snom_ColleaguesRingSound;Device.X_Snom_HttpUser;Device.X_Snom_HttpPass;Device.UserInterface.CurrentLanguage;Device.Time.NTPServer1;Device.UserInterface.X_Snom_DimTimer;Device.X_Snom_ReleaseSound;Device.X_Snom_HTTPClientUser;Device.X_Snom_HTTPClientPassword;Device.X_Snom_WebLogoutTimer;Device.X_Snom_AdminModePassword';

@endphp
<settings>
  <phone-settings e="2">
      
    <!-- SIP Accounts -->
    @foreach ($lines as $line)
        @php
            $n = (int) ($line['line_number'] ?? 1);
        @endphp
        <user_active idx="{{ $n }}" perm="R">on</user_active>
        <user_realname idx="{{ $n }}" perm="R">{{ $line['display_name'] ?? '' }}</user_realname>
        <user_name idx="{{ $n }}" perm="R">{{ $line['auth_id'] ?? '' }}</user_name>
        <user_host idx="{{ $n }}" perm="R">{{ $line['server_address'] ?? ''}}:{{ $line['sip_port'] ?? '5060'}}</user_host>
        <user_pass idx="{{ $n }}" perm="R">{{ $line['password'] ?? '' }}</user_pass>
        <user_expiry idx="{{ $n }}" perm="R">{{ $line['register_expires'] ?? $settings['register_expires'] ?? 120 }}</user_expiry>
        <user_mailbox idx="{{ $n }}" perm="R">{{ $settings['voicemail_number'] ?? '*97' }}</user_mailbox>
        <user_shared_line idx="{{ $n }}" perm="R">{{ $boolOn($line['shared_line'] ?? 'off') }}</user_shared_line>
        <user_server_type idx="{{ $n }}" perm="R">{{ $line['snom_server_type'] ?? $settings['snom_server_type'] ?? 'Default' }}</user_server_type>
        <user_srtp idx="{{ $n }}" perm="R">{{ $line['snom_srtp'] ?? $settings['snom_srtp'] ?? 'off' }}</user_srtp>
        <user_symmetrical_rtp idx="{{ $n }}" perm="">{{ $line['snom_symmetrical_rtp'] ?? $settings['snom_symmetrical_rtp'] ?? 'on' }}</user_symmetrical_rtp>
        <user_idle_number idx="{{ $n }}" perm="RW">{{ $line['snom_user_idle_number'] ?? $settings['snom_user_idle_number'] ?? '' }}</user_idle_number>
        @if (!empty($line['snom_user_uid']) || !empty($settings['snom_user_uid']))
        <user_uid idx="{{ $n }}" perm="">{{ $line['snom_user_uid'] ?? $settings['snom_user_uid'] }}</user_uid>
        @endif
        <user_dp_str idx="{{ $n }}" perm="">{{ $settings['snom_user_dp_str'] ?? '|(^[2-9][0-9]{9}$)|sip:\1@\d|d |(^1[0-9]{3}$)|sip:\1@\d|d |(^933$)|sip:\1@\d|d |(^911$)|sip:\1@\d|d' }}</user_dp_str>
        <user_ringer idx="{{ $n }}" perm="RW">{{ $settings['snom_user_ringer'] ?? 'Ringer7' }}</user_ringer>
        <user_outbound idx="{{ $n }}" perm="R">{{ $line['outbound_proxy_primary'] ?? '' }}:{{ $line['sip_port'] ?? '5060'}};transport={{ $line['sip_transport'] ?? udp }}</user_outbound>
        <keepalive_interval idx="{{ $n }}" perm="R">{{ $line['snom_keepalive'] ?? $settings['snom_keepalive'] ?? 25 }}</keepalive_interval>
        <dnd_mode idx="{{ $n }}" perm="">{{ $line['snom_dnd_mode'] ?? $settings['snom_dnd_mode'] ?? 'off' }}</dnd_mode>
        <user_ice idx="{{ $n }}" perm="">{{ $line['snom_ice'] ?? $settings['snom_ice'] ?? 'off' }}</user_ice>
        <stun_server idx="{{ $n }}" perm="R">{{ $line['stun_server'] ?? ''}}</stun_server>
        <stun_binding_interval idx="{{ $n }}" perm="R">{{ $line['snom_stun_interval'] ?? $settings['snom_stun_interval'] ?? '' }}</stun_binding_interval>
        <codec_priority_list idx="{{ $n }}" perm="RW">{{ $line['snom_codec'] ?? $settings['snom_codec'] ?? 'g722,pcmu,pcma,g729,telephone-event' }}</codec_priority_list>
    @endforeach

    <!-- Basic Settings -->
    <web_language perm="">{{ $settings['snom_language'] ?? 'English' }}</web_language>
    <language perm="">{{ $settings['snom_language'] ?? 'English' }}</language>
    <wifi_country_code perm="RW">{{ $settings['snom_wifi_country_code'] ?? 'EU' }}</wifi_country_code>
    <setting_server perm="RW">{{ $settings['provision_base_url'] ?? '' }}{mac}.xml</setting_server>
    <system_time perm="RW">{{ $settings['snom_system_time'] ?? time() }}</system_time>
    <http_user perm="R">{{ $settings['admin_name'] ?? '' }}</http_user>
    <http_pass perm="R">{{ $settings['admin_password'] ?? ''}}</http_pass>
    <webserver_admin_name perm="">{{ $settings['admin_name'] ?? '' }}</webserver_admin_name>
    <webserver_admin_password perm="">{{ $settings['admin_password'] ?? ''}}</webserver_admin_password>
    <dst perm="">{{ $settings['snom_dst'] ?? '3600 03.02.07 02:00:00 11.01.07 02:00:00' }}</dst>
    <tone_scheme perm="">{{ $settings['snom_tone_scheme'] ?? 'USA' }}</tone_scheme>
    <mb_trusted_hosts perm="">{{ $settings['snom_mb_trusted_hosts'] ?? '' }}</mb_trusted_hosts>
    <ntp_server perm="">{{ $settings['snom_ntp_server'] ?? $settings['ntp_server_primary'] ?? '0.pool.ntp.org' }}</ntp_server>
    <ntp_refresh_timer perm="">{{ $settings['snom_ntp_refresh_timer'] ?? 3600 }}</ntp_refresh_timer>
    <timezone perm="">{{ $settings['snom_time_zone'] ?? $settings['time_zone'] ?? 'USA-8' }}</timezone>
    @if (array_key_exists('snom_dhcp', $settings))
        <dhcp perm="R">{{ $settings['snom_dhcp'] }}</dhcp>
    @endif
    <vol_speaker perm="">{{ $settings['snom_vol_speaker'] ?? 15 }}</vol_speaker>
    <vol_ringer perm="">{{ $settings['snom_vol_ringer'] ?? 7 }}</vol_ringer>
    <guess_start_length perm="RW">{{ $settings['snom_guess_start_length'] ?? 3 }}</guess_start_length>
    <colleagues_ring_sound perm="RW">{{ $settings['snom_colleagues_ring_sound'] ?? 'Ringer7' }}</colleagues_ring_sound>
    <work_ring_sound perm="RW">{{ $settings['snom_work_ring_sound'] ?? 'Ringer7' }}</work_ring_sound>
    <dim_timer perm="">{{ $settings['snom_dim_timer'] ?? 20 }}</dim_timer>
    <backlight perm="">{{ $settings['snom_backlight'] ?? 15 }}</backlight>
    <backlight_idle perm="">{{ $settings['snom_backlight_idle'] ?? 5 }}</backlight_idle>
    <display_method perm="">{{ $settings['snom_display_method'] ?? 'display_name_number' }}</display_method>
    <global_missed_counter perm="">{{ $settings['snom_global_missed_counter'] ?? 'off' }}</global_missed_counter>
    <dialnumber_us_format perm="">{{ $settings['snom_dialnumber_us_format'] ?? 'on' }}</dialnumber_us_format>
    <show_ivr_digits perm="">{{ $settings['snom_show_ivr_digits'] ?? 'off' }}</show_ivr_digits>
    <cw_dialtone perm="">{{ $settings['snom_dialtone_on_hold'] ?? 'false' }}</cw_dialtone>
    <answer_after_policy perm="">{{ $settings['snom_answer_after_policy'] ?? 'off' }}</answer_after_policy>
    <keyboard_lock_emergency perm="R">{{ $settings['snom_emergency_numbers'] ?? '911 9911 1911' }}</keyboard_lock_emergency>
    <release_sound perm="RW">{{ $settings['snom_release_sound'] ?? 'on' }}</release_sound>
    <web_logout_timer perm="">{{ $settings['snom_web_logout_timer'] ?? 5 }}</web_logout_timer>
    <admin_mode perm="R">on</admin_mode>
    <admin_mode_password perm="R">{{ $settings['admin_password'] ?? ''}}</admin_mode_password>
    <admin_mode_password_confirm perm="R">{{ $settings['admin_password'] ?? ''}}</admin_mode_password_confirm>
    @if (array_key_exists('snom_firmware_version', $settings))
    <firmware_version perm="">{{ $settings['snom_firmware_version'] }}</firmware_version>
    @endif
    <http_client_user perm="">{{ $settings['http_auth_username'] ?? '' }}</http_client_user>
    <http_client_pass perm="">{{ $settings['http_auth_password'] ?? '' }}</http_client_pass>
    <auto_reboot_on_setting_change perm="RW">{{ $settings['snom_auto_reboot_on_setting_change'] ?? 'on' }}</auto_reboot_on_setting_change>
    @if (array_key_exists('snom_uboot_version', $settings))
    <uboot_version perm="">{{ $settings['snom_uboot_version'] }}</uboot_version>
    @endif
    <tr69_params perm="">{{ $settings['snom_tr69_params'] ?? $snomDefaultTr69Params }}</tr69_params>
    <call_screen_fkeys_on_connected perm="">{{ $settings['snom_call_screen_fkeys_on_connected'] ?? 'F_HOLD transfer F_CONFERENCE ' }}</call_screen_fkeys_on_connected>
    <call_screen_fkeys_on_holding perm="">{{ $settings['snom_call_screen_fkeys_on_holding'] ?? 'F_LEFT F_RIGHT F_CONF_ON(not:Transfer) F_DIAL(Transfer) F_HOLD transfer(not:Transfer) F_CONTACTPOOL(Holding,Transfer) F_ABS F_DELETE_MSG F_CALLRECORD_CONTROL_ON ' }}</call_screen_fkeys_on_holding>
    <status_msgs_that_are_blocked perm="R">{{ $settings['snom_status_msgs_that_are_blocked'] ?? 'HidConnected UxmConnected' }}</status_msgs_that_are_blocked>
    <was_never_registered perm="">{{ $settings['snom_was_never_registered'] ?? 'off' }}</was_never_registered>
    <ui_theme perm="RW">{{ $settings['snom_ui_theme'] ?? '' }}</ui_theme>
    <directory_display_order perm="RW">{{ $settings['snom_directory_display_order'] ?? 'first_name_last_name' }}</directory_display_order>
    @if (array_key_exists('snom_e911_lis_chassis_id', $settings))
    <e911_lis_chassis_id perm="">{{ $settings['snom_e911_lis_chassis_id'] }}</e911_lis_chassis_id>
    @endif
    @if (array_key_exists('snom_e911_lis_port_id', $settings))
    <e911_lis_port_id perm="">{{ $settings['snom_e911_lis_port_id'] }}</e911_lis_port_id>
    @endif
    <replacement_plan_url perm="">{{ $settings['snom_replacement_plan_url'] ?? 'xml' }}</replacement_plan_url>
    <disable_fav_menu perm="RW">{{ $settings['snom_disable_fav_menu'] ?? 'on' }}</disable_fav_menu>

    <!-- Ringtones -->
    <alert_internal_ring_text perm="">{{ $settings['snom_alert_internal_text'] ?? 'alert-internal' }}</alert_internal_ring_text>
    <alert_internal_ring_sound perm="">{{ $settings['snom_alert_internal'] ?? 'Ringer1' }}</alert_internal_ring_sound>
    <alert_external_ring_text perm="">{{ $settings['snom_alert_external_text'] ?? 'alert-external' }}</alert_external_ring_text>
    <alert_external_ring_sound perm="">{{ $settings['snom_alert_external'] ?? 'Ringer1' }}</alert_external_ring_sound>
    <alert_group_ring_text perm="">{{ $settings['snom_alert_group_text'] ?? 'alert-group' }}</alert_group_ring_text>
    <alert_group_ring_sound perm="">{{ $settings['snom_alert_group'] ?? 'Ringer1' }}</alert_group_ring_sound>

    <!-- Wallpaper (1280x720px PNG) -->
    <custom_bg_image_url perm="">{{ $settings['snom_wallpaper_url_d862'] ?? $settings['snom_wallpaper_url'] ?? '' }}</custom_bg_image_url>

    <!-- Expansion Mod (___x___px PNG <2MB) -->
    <expansion_module_background_image perm="">{{ $settings['snom_exp_wallpaper_url_d862'] ?? $settings['snom_exp_wallpaper_url'] ?? '' }}</expansion_module_background_image>

    <!-- SIP Settings -->
    <mwi_notification perm="">{{ $settings['snom_mwi_notification'] ?? 'silent' }}</mwi_notification>
    <mwi_dialtone perm="">{{ $settings['snom_mwi_dialtone'] ?? 'stutter' }}</mwi_dialtone>
    <enable_rport_rfc3581 perm="RW">{{ $settings['snom_enable_rport_rfc3581'] ?? 'on' }}</enable_rport_rfc3581>
    <privacy_in perm="">{{ $settings['snom_privacy_in'] ?? 'off' }}</privacy_in>
    <privacy_out perm="">{{ $settings['snom_privacy_out'] ?? 'off' }}</privacy_out>
    <quick_transfer perm="">{{ $settings['snom_quick_transfer'] ?? 'new_call' }}</quick_transfer>
    <transfer_dialing_on_transfer perm="">{{ $settings['snom_transfer_dialing_on_transfer'] ?? 'blind' }}</transfer_dialing_on_transfer>
    <transfer_dialing_on_other perm="">{{ $settings['snom_transfer_dialing_on_other'] ?? 'blind' }}</transfer_dialing_on_other>

    <!-- Updates -->
    <update_policy perm="">{{ $settings['snom_update_policy'] ?? 'auto_update' }}</update_policy>
    <firmware perm="R">{{ $settings['snom_firmware'] ?? (($settings['snom_firmware_url'] ?? '') . ($settings['snom_firmware_d862'] ?? '')) }}</firmware>
    <firmware_uxm perm="R">{{ $settings['snom_firmware_uxm_full'] ?? (($settings['snom_firmware_url'] ?? '') . ($settings['snom_firmware_uxm'] ?? '')) }}</firmware_uxm>
    <settings_refresh_timer perm="RW">{{ $settings['snom_provision_timer_seconds'] ?? 0 }}</settings_refresh_timer>

    
    <general_purpose_xml_descriptions idx="1" perm="RW">{{ $settings['snom_general_purpose_xml_descriptions'] ?? '' }}</general_purpose_xml_descriptions>

    @for ($i = 1; $i <= (int) ($settings['snom_publish_presence_count'] ?? 12); $i++)
    <publish_presence idx="{{ $i }}" perm="RW">{{ $settings["snom_publish_presence_{$i}"] ?? $settings['snom_publish_presence'] ?? 'on' }}</publish_presence>
    @endfor

    <use_contact_in_refer_to_hdr idx="1" perm="">{{ $settings['snom_use_contact_in_refer_to_hdr'] ?? 'off' }}</use_contact_in_refer_to_hdr>

    @if (($settings['snom_tr369_include'] ?? false))
    <tr369_mqtt_hostport idx="1" perm="R">{{ $settings['snom_tr369_mqtt_hostport'] ?? 'dm.snom.com:8883' }}</tr369_mqtt_hostport>
    <tr369_mqtt_transport idx="1" perm="R">{{ $settings['snom_tr369_mqtt_transport'] ?? 'TLS' }}</tr369_mqtt_transport>
    <tr369_mqtt_clientid idx="1" perm="R">{{ $settings['snom_tr369_mqtt_clientid'] ?? '!!$(::)!!$(mac_lower_case)' }}</tr369_mqtt_clientid>
    <tr369_mqtt_topic idx="1" perm="R">{{ $settings['snom_tr369_mqtt_topic'] ?? '!!$(::)!!usp/endpoints/$(mac_lower_case)' }}</tr369_mqtt_topic>
    <tr369_controller_endpointid idx="1" perm="R">{{ $settings['snom_tr369_controller_endpointid'] ?? 'self::usp-controller' }}</tr369_controller_endpointid>
    <tr369_controller_subscriptionid idx="1" perm="R">{{ $settings['snom_tr369_controller_subscriptionid'] ?? 'default-boot-event-ACS' }}</tr369_controller_subscriptionid>
    <tr369_controller_e2esize idx="1" perm="R">{{ $settings['snom_tr369_controller_e2esize'] ?? 130000 }}</tr369_controller_e2esize>
    <tr369_mtp_protocol idx="1" perm="R">{{ $settings['snom_tr369_mtp_protocol'] ?? 'MQTT' }}</tr369_mtp_protocol>
    <tr369_enable idx="1" perm="RW">{{ $settings['snom_tr369_enable'] ?? 'true' }}</tr369_enable>
    @endif
  </phone-settings>

  <!-- Function Keys. View Key Types here: https://service.snom.com/display/wiki/Function+Key+Types -->
    <functionKeys e="2">
        @php
            $phoneType = $phone_type ?? request('phone_type');
    
            // snomD862 main keys end at idx 35.
            // snomD865 main keys end at idx 39.
            // Expansion key id 1 therefore becomes idx 36 or 40.
            $fkeyOffset = $phoneType === 'snomD862' ? 31 : 35;
    
            $getFkeyValue = function ($key) {
                $value = trim((string) ($key['value'] ?? ''));
    
                return trim(match ($key['type'] ?? '') {
                    'line' => 'line',
                    'none' => 'none',
                    'blf', 'check_voicemail' => 'blf ' . $value,
                    'park' => 'orbit ' . $value,
                    'speed_dial' => 'speed ' . $value,
                    'dest' => 'dest ' . $value,
                    default => $value,
                });
            };

            // Main keys are 1-based from the server, but Snom fkey idx is 0-based.
            $lastMainIdx = collect($main_keys ?? [])
                ->max(fn ($key) => ((int) ($key['id'] ?? 0)) - 1);
    
            $lastMainIdx = $lastMainIdx ?? -1;
    
            // Expansion keys use the model offset.
            $lastExpansionIdx = collect($expansion_keys ?? [])
                ->max(fn ($key) => ((int) ($key['id'] ?? 0)) + $fkeyOffset);
    
            $lastExpansionIdx = $lastExpansionIdx ?? $fkeyOffset;
            
            $usedFkeyIdxs = [];

            foreach (($main_keys ?? []) as $key) {
                $idx = ((int) ($key['id'] ?? 0)) - 1;
        
                if ($idx >= 0) {
                    $usedFkeyIdxs[$idx] = true;
                }
            }
        
            foreach (($expansion_keys ?? []) as $key) {
                $idx = ((int) ($key['id'] ?? 0)) + $fkeyOffset;
        
                if ($idx >= 0) {
                    $usedFkeyIdxs[$idx] = true;
                }
            }
        @endphp
    

        {{-- Main phone keys --}}
        @foreach ($main_keys as $key)
            @php
                $fkeyValue = $getFkeyValue($key);
            @endphp
        
            <fkey idx="{{ $key['id'] -1 }}" 
                context="{{ $key['line'] ?? '' }}" 
                short_label_mode="{{ $settings['snom_fkey_short_label_mode'] ?? 'icon_text'}}" 
                short_label="{{ $key['short_label'] ?? '' }}" 
                short_default_text="{{ $settings['snom_fkey_short_default_text'] ?? '!!$(::)!!$(generate_via_conditional_label_short)'}}" 
                label_mode="{{ $settings['snom_fkey_label_mode'] ?? 'icon_text' }}" 
                icon_type="{{ $key['icon_type'] ?? ''}}" 
                reg_label_mode="{{ $settings['snom_fkey_reg_label_mode'] ?? 'icon_text'}}" 
                ringer="{{ $settings['snom_fkey_ringer'] ?? 'Silent'}}" 
                park_retrieve="{{ $key['park_retrieve'] ?? ''}}" 
                label="{{ $key['label'] ?? ''}}" 
                lp="{{ $settings['snom_fkey_lp'] ?? 'on'}}" 
                default_text="{{ $settings['snom_fkey_default_text'] ?? '!!$(::)!!$(generate_via_conditional_label_full)'}}" 
                perm="{{ $key['perm'] ?? ''}}">{{ $fkeyValue }}</fkey>
        @endforeach
      
    {{-- Blank main key slots up to idx 35 for D862 or idx 39 for D865 --}}
        @for ($idx = $lastMainIdx + 1; $idx <= $fkeyOffset; $idx++)
            @continue(isset($usedFkeyIdxs[$idx]))
            <fkey idx="{{ $idx }}" 
                context="1" 
                short_label_mode="{{ $settings['snom_fkey_short_label_mode'] ?? 'icon_text'}}" 
                short_label="" 
                short_default_text="{{ $settings['snom_fkey_short_default_text'] ?? '!!$(::)!!$(generate_via_conditional_label_short)'}}" 
                label_mode="{{ $settings['snom_fkey_label_mode'] ?? 'icon_text' }}" 
                icon_type="" 
                reg_label_mode="{{ $settings['snom_fkey_reg_label_mode'] ?? 'icon_text'}}" 
                ringer="{{ $settings['snom_fkey_ringer'] ?? 'Silent'}}" 
                park_retrieve="" 
                label="" 
                lp="{{ $settings['snom_fkey_lp'] ?? 'on'}}" 
                default_text="{{ $settings['snom_fkey_default_text'] ?? '!!$(::)!!$(generate_via_conditional_label_full)'}}" 
                perm=""/>
        @endfor
      
        {{-- Expansion keys --}}
        @foreach ($expansion_keys as $key)
            @php
                $fkeyValue = $getFkeyValue($key);
            @endphp
    
            <fkey idx="{{ $key['id'] + $fkeyOffset }}" 
                context="{{ $key['line'] ?? '' }}" 
                short_label_mode="{{ $settings['snom_fkey_short_label_mode'] ?? 'icon_text'}}" 
                short_label="{{ $key['short_label'] ?? '' }}" 
                short_default_text="{{ $settings['snom_fkey_short_default_text'] ?? '!!$(::)!!$(generate_via_conditional_label_short)'}}" 
                label_mode="{{ $settings['snom_fkey_label_mode'] ?? 'icon_text' }}" 
                icon_type="{{ $key['icon_type'] ?? ''}}" 
                reg_label_mode="{{ $settings['snom_fkey_reg_label_mode'] ?? 'icon_text'}}" 
                ringer="{{ $settings['snom_fkey_ringer'] ?? 'Silent'}}" 
                park_retrieve="{{ $key['park_retrieve'] ?? ''}}" 
                label="{{ $key['label'] ?? ''}}" 
                lp="{{ $settings['snom_fkey_lp'] ?? 'on'}}" 
                default_text="{{ $settings['snom_fkey_default_text'] ?? '!!$(::)!!$(generate_via_conditional_label_full)'}}" 
                perm="{{ $key['perm'] ?? ''}}">{{ $fkeyValue }}</fkey>
        @endforeach
    
    
        {{-- Blank expansion key slots through idx 219 --}}
        @for ($idx = $lastExpansionIdx + 1; $idx <= 219; $idx++)
            @continue(isset($usedFkeyIdxs[$idx]))
            <fkey idx="{{ $idx }}" 
                context="1" 
                short_label_mode="{{ $settings['snom_fkey_short_label_mode'] ?? 'icon_text'}}" 
                short_label="" 
                short_default_text="{{ $settings['snom_fkey_short_default_text'] ?? '!!$(::)!!$(generate_via_conditional_label_short)'}}" 
                label_mode="{{ $settings['snom_fkey_label_mode'] ?? 'icon_text' }}" 
                icon_type="" 
                reg_label_mode="{{ $settings['snom_fkey_reg_label_mode'] ?? 'icon_text'}}" 
                ringer="{{ $settings['snom_fkey_ringer'] ?? 'Silent'}}" 
                park_retrieve="" 
                label="" 
                lp="{{ $settings['snom_fkey_lp'] ?? 'on'}}" 
                default_text="{{ $settings['snom_fkey_default_text'] ?? '!!$(::)!!$(generate_via_conditional_label_full)'}}" 
                perm=""/>
        @endfor
    </functionKeys>

  <tbook e="2" complete="true">
    @php $contactCount = 0; @endphp
    @foreach (($contacts ?? []) as $contact)
    @php
        $category = $contact['category'] ?? '';
        $isExtension = $category === 'extensions';
        $contactType = $isExtension ? 'colleagues' : ($contact['snom_contact_type'] ?? 'none');
        $contactNumber = $isExtension ? ($contact['phone_extension'] ?? '') : ($contact['phone_number'] ?? '');
        $contactIndex = $contact['index'] ?? $contactCount;
    @endphp
    <item context="{{ $contact['context'] ?? 'active' }}" type="{{ $contactType }}" index="{{ $contactIndex }}">
      <first_name>{{ $contact['contact_name_given'] ?? '' }}</first_name>
      <last_name>{{ $contact['contact_name_family'] ?? '' }}</last_name>
      <number>{{ $contactNumber }}</number>
    </item>
    @php $contactCount++; @endphp
    @endforeach
  </tbook>
  
  <!-- Advanced Network -->
  <tcp_listen perm="">on</tcp_listen>
</settings>
@break
@endswitch