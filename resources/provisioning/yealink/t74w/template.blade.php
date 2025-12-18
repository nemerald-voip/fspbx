{{-- version: 1.0.2 --}}

@switch($flavor)

{{-- ================= Yealink T74W mac.cfg ================= --}}
@case('mac.cfg')

#!version:1.0.0.1


################################################################
#                      Account Register                       ##
################################################################
@foreach ($lines as $line)
    @php  $n = (int)($line['line_number'] ?? 0); @endphp
    @continue($n <= 0)

    account.{{ $n }}.enable = 1
    account.{{ $n }}.label = {{ $line['display_name'] ?? $line['auth_id'] }}
    account.{{ $n }}.display_name = {{ $line['display_name'] ?? $line['auth_id'] }}
    account.{{ $n }}.auth_name = {{ $line['auth_id'] ?? '' }}
    account.{{ $n }}.user_name = {{ $line['auth_id'] ?? '' }}{{ '@' }}{{ $line['server_address'] ?? '' }}
    account.{{ $n }}.password = {{ $line['password'] ?? '' }}
    
    account.{{ $n }}.sip_server.1.address = {{ $line['server_address_primary'] ?? $line['server_address'] ?? $domain_name }}
    account.{{ $n }}.sip_server.1.port = {{ $line['sip_port'] ?? '5060' }}
    account.{{ $n }}.sip_server.1.transport_type = {{ strtolower($line['sip_transport'] ?? '0') }}
    account.{{ $n }}.sip_server.1.expires = {{ $line['register_expires'] ?? '3600' }}
    account.{{ $n }}.sip_server.1.failback_subscribe.enable = 1
    
    @if (!empty($line['server_address_secondary']))
        account.{{ $n }}.sip_server.2.address = {{ $line['server_address_secondary'] ?? '' }}
        account.{{ $n }}.sip_server.2.port = {{ $line['sip_port'] ?? '5060' }}
        account.{{ $n }}.sip_server.2.transport_type = {{ strtolower($line['sip_transport'] ?? '0') }}
        account.{{ $n }}.sip_server.2.expires = {{ $line['register_expires'] ?? '3600' }}
        account.{{ $n }}.sip_server.1.failback_subscribe.enable = 1
    @endif
    
    account.{{ $n }}.cp_source = {{ $settings['yealink_cp_source'] ?? '0' }}
    account.{{ $n }}.cid_source_ppi= 1
    account.{{ $n }}.nat.nat_traversal = {{ isset($settings['stun_server']) ? '1' : '0' }}
    account.{{ $n }}.subscribe_mwi = 1
    account.{{ $n }}.subscribe_mwi_to_vm = {{ isset($settings['yealink_subscribe_mwi_to_vm']) ? '1' : '0' }}
    
    @if (!empty($line['outbound_proxy_primary']))
        account.{{ $n }}.outbound_proxy_enable = 1
        account.{{ $n }}.outbound_proxy.1.address = {{ $line['outbound_proxy_primary'] ?? '' }}
        account.{{ $n }}.outbound_proxy.2.address = {{ $line['outbound_proxy_secondary'] ?? ''}}
        account.{{ $n }}.outbound_proxy.1.port = {{ $line['sip_port'] ?? '5060' }}
        account.{{ $n }}.outbound_proxy.2.port = {{ $line['sip_port'] ?? '5060' }}
        account.{{ $n }}.outbound_proxy_fallback_interval = {{ $settings['yealink_outbound_proxy_fallback_interval'] ?? '3600' }}
    @endif
    
    @if (!empty($line['shared_line']))
    account.{{ $n }}.shared_line = 1
    @endif

@endforeach

################################################################
#                      Account Codec                          ##
################################################################
@foreach ($lines as $line)
    @php  $n = (int)($line['line_number'] ?? 0); @endphp
    @continue($n <= 0)

    @php
      // codec key => [enable_key, priority_key] in $settings
      $codecMap = [
        'ilbc_15_2kbps'   => ['yealink_codec_ilbc_15_2_enable',   'yealink_codec_ilbc_15_2_priority'],
        'ilbc_13_33kbps'  => ['yealink_codec_iLBC_enable',        'yealink_codec_iLBC_priority'],
        'pcma'            => ['yealink_codec_pcma_enable',        'yealink_codec_pcma_priority'],
        'pcmu'            => ['yealink_codec_pcmu_enable',        'yealink_codec_pcmu_priority'],
        'opus'            => ['yealink_codec_opus_enable',        'yealink_codec_opus_priority'],
        'g726_40'         => ['yealink_codec_g726_40_enable',     'yealink_codec_g726_40_priority'],
        'g726_32'         => ['yealink_codec_g726_32_enable',     'yealink_codec_g726_32_priority'],
        'g726_24'         => ['yealink_codec_g726_24_enable',     'yealink_codec_g726_24_priority'],
        'g726_16'         => ['yealink_codec_g726_16_enable',     'yealink_codec_g726_16_priority'],
        'g723_63'         => ['yealink_codec_g723_63_enable',     'yealink_codec_g723_63_priority'],
        'g723_53'         => ['yealink_codec_g723_53_enable',     'yealink_codec_g723_53_priority'],
        'g729'            => ['yealink_codec_g729_enable',        'yealink_codec_g729_priority'],
        'g722'            => ['yealink_codec_g722_enable',        'yealink_codec_g722_priority'],
      ];
    @endphp
    
    @foreach ($codecMap as $codec => [$enableKey, $prioKey])
        account.{{ $n }}.codec.{{ $codec }}.enable = {{ $settings[$enableKey] ?? 0 }}
        account.{{ $n }}.codec.{{ $codec }}.priority = {{ $settings[$prioKey] ?? 0 }}
    
    @endforeach

    account.{{ $n }}.codec.opus.para = {{ $settings['yealink_codec_opus_pt'] ?? 106 }}

@endforeach


################################################################
#                      Account Advanced                       ##
################################################################
@foreach ($lines as $line)
    @php  $n = (int)($line['line_number'] ?? 0); @endphp
    @continue($n <= 0)
    
    account.{{ $n }}.nat.udp_update_enable = {{ $settings['yealink_udp_update_enable'] ?? '1' }}
    account.{{ $n }}.nat.rport = 1
    account.{{ $n }}.dtmf.type = {{ $settings['yealink_udp_update_enable'] ?? '1' }}
    account.{{ $n }}.cid_source = {{ $settings['yealink_cid_source'] ?? '0' }}
    account.{{ $n }}.srtp_encryption = {{ ($settings['yealink_srtp_encryption']) ?? '0' }}
    account.{{ $n }}.session_timer.enable = {{ ($settings['yealink_session_timer']) ?? '0' }}
    account.{{ $n }}.conf_type = {{ (isset($settings['nway_conference']) && $settings['nway_conference'] === 'true') ? 2 : 0 }}
    account.{{ $n }}.missed_calllog = {{ ($settings['yealink_missed_calllog']) ?? '1' }}
    account.{{ $n }}.picture_info_enable= 1
    account.{{ $n }}.phone_setting.call_appearance.transfer_via_new_linekey = {{ ($settings['yealink_transfer_via_new_linekey']) ?? '1' }}
    
    voice_mail.number.{{ $n }} = {{ $settings['voicemail_number'] ?? '' }}

@endforeach


################################################################
##                          Linekeys                          ##
################################################################
@php
  // 1) Mark which accounts are shared (optional: if you already know this, build this array upstream)
  $sharedLines = [];
  foreach ($lines as $ln) {
      $n = (int)($ln['line_number'] ?? 0);
      if ($n > 0 && !empty($ln['shared_line'])) {
          $sharedLines[$n] = true;
      }
  }

  // 2) Count appearances per account for type=15 (line keys)
  $appearanceCount = [];
  foreach ($keys as $k) {
      if ((string)($k['type'] ?? '') === '15') {
          $ln = (int)($k['line'] ?? 0);
          if ($ln > 0) {
              $appearanceCount[$ln] = ($appearanceCount[$ln] ?? 0) + 1;
          }
      }
  }

  // 3) Running index per account to pick suffix a,b,c...
  $appearanceIndex = [];
  $slot = 1;

  // helper for suffix: 1->a, 2->b ... wraps after 26
  $suffixFor = static function (int $idx): string {
      $idx = max(1, $idx);
      $alpha = chr(96 + ((($idx - 1) % 26) + 1));
      return $alpha;
  };
@endphp

@foreach ($keys as $k)
@php
  $type = (string)($k['type'] ?? '');
  $ln   = (int)($k['line'] ?? 0);

  // Base label as provided
  $label = isset($k['label']) ? (string)$k['label'] : '';

  // If it's a line key on a shared account AND label is empty, add postfix
  if ($type === '15' && $ln > 0 && !isset($k['label']) && !empty($sharedLines[$ln])) {
      $appearanceIndex[$ln] = ($appearanceIndex[$ln] ?? 0) + 1;
      $sfx = $suffixFor($appearanceIndex[$ln]);

      // Find a base (display_name/auth_id) for this account
      $base = '';
      if (!empty($lines[$ln]['display_name']))       $base = (string)$lines[$ln]['display_name'];
      elseif (!empty($lines[$ln]['auth_id']))        $base = (string)$lines[$ln]['auth_id'];
      else                                          $base = (string)$ln;

      $label = trim($base . ' ' . $sfx);
  }

  $value = isset($k['value']) ? (string)$k['value'] : '';
  $ext   = array_key_exists('extension', $k) ? (string)($k['extension'] ?? '') : null;
@endphp
linekey.{{ $slot }}.type = {{ $k['type'] }}
linekey.{{ $slot }}.line = {{ $k['line'] }}
linekey.{{ $slot }}.label = {{ $label }}
linekey.{{ $slot }}.value = {{ $value }}
@if ($ext !== null)
linekey.{{ $slot }}.extension = "{{ $ext }}"
@endif

@php $slot++; @endphp
@endforeach

################################################################
##                           DND                              ##
################################################################
features.dnd.allow = {{ $settings['yealink_dnd_allow'] ?? '0' }}
features.dnd.off_code = {{ $settings['yealink_dnd_off_code'] ?? '' }}
features.dnd.on_code = {{ $settings['yealink_dnd_on_code'] ?? '' }}

################################################################
##                           Forward                          ##
################################################################
features.fwd.allow = {{ $settings['yealink_fwd_allow'] ?? 0 }}
forward.always.off_code = {{ $settings['yealink_forward_always_off_code'] ?? '' }}
forward.always.on_code  = {{ $settings['yealink_forward_always_on_code'] ?? '' }}

################################################################
##                           Phone Lock                       ##
################################################################
phone_setting.phone_lock.enable = {{ $settings['yealink_lock_enable'] ?? 0 }}
phone_setting.phone_lock.lock_key_type = {{ $settings['yealink_lock_key_type'] ?? 0 }} 
phone_setting.phone_lock.unlock_pin = {{ $settings['yealink_lock_pin'] ?? '' }}
phone_setting.emergency.number = {{ $settings['yealink_emergency_number'] ?? '' }}
phone_setting.phone_lock.lock_time_out = {{ $settings['yealink_lock_timeout'] ?? 0 }}

################################################################
##                      Voice Mail                            ##
################################################################
features.voice_mail_popup.enable = {{ $settings['yealink_voice_mail_popup_enable'] ?? 0 }}

################################################################
##                      Feature General                       ##
################################################################
features.missed_call_popup.enable = {{ $settings['yealink_missed_call_popup_enable'] ?? 1 }}
features.direct_ip_call_enable = {{ $settings['yealink_direct_ip_call_enable'] ?? 1 }}
features.dtmf.hide = {{ $settings['yealink_dtmf_hide'] ?? 0 }}
call_waiting.enable = {{ $settings['yealink_call_waiting'] ?? 1 }}
call_waiting.tone = {{ $settings['yealink_call_waiting_tone'] ?? 1 }}
sip.rfc2543_hold = {{ $settings['yealink_rfc2543_hold'] ?? 0 }}
features.hide_feature_access_codes.enable = {{ $settings['yealink_hide_feature_access_codes_enable'] ?? 0 }}
phone_setting.missed_call_power_led_flash.enable = {{ $settings['yealink_missed_call_power_led_flash_enable'] ?? 1 }}
phone_setting.backlight_time = {{ $settings['yealink_backlight_time'] ?? 30 }} 
phone_setting.inactive_backlight_level = {{ $settings['yealink_inactive_backlight_level'] ?? 1 }}
phone_setting.predial_autodial = {{ $settings['yealink_predial_autodial'] ?? 1 }}
ringtone.url = {{ $settings['yealink_ringtone_url'] ?? '' }}
ringtone.delete = {{ $settings['yealink_ringtone_delete'] ?? 0 }}
phone_setting.ring_type = {{ $settings['yealink_ring_type'] ?? 1 }}
phone_setting.inter_digit_time = {{ $settings['yealink_inter_digit_time'] ?? 4 }}
lang.gui = "{{ $settings['yealink_language_gui'] ?? 'English' }}"
features.blf_active_backlight.enable = {{ $settings['yealink_blf_active_backlight'] ?? 1 }}
screensaver.wait_time = {{ $settings['yealink_screensaver_wait'] ?? 600 }}
features.blf_led_mode = {{ $settings['yealink_blf_led_mode'] ?? 0 }}
features.pickup.direct_pickup_enable = {{ $settings['yealink_direct_pickup_enable'] ?? 1 }}
features.pickup.direct_pickup_code = **
features.feature_key_sync.enable = {{ $settings['yealink_feature_key_sync'] ?? 1 }}
features.caller_name_type_on_dialing = 2

################################################################
##                      Time&Date                             ##
################################################################
auto_dst.url = {{ $settings['yealink_auto_dst_url'] ?? '' }}

local_time.manual_time_enable = {{ $settings['yealink_manual_time_enable'] ?? 0 }}
local_time.manual_ntp_srv_prior = {{ $settings['yealink_manual_ntp_srv_prior'] ?? 0 }}

local_time.time_format = {{ $settings['yealink_time_format'] ?? '0' }}
local_time.date_format = {{ $settings['yealink_date_format'] ?? '4' }}
local_time.dhcp_time = {{ $settings['yealink_dhcp_time'] ?? 1 }}

local_time.summer_time = {{ $settings['yealink_summer_time'] ?? '2' }}
local_time.dst_time_type = {{ $settings['yealink_dst_type'] ?? '0' }}
local_time.start_time = {{ $settings['yealink_time_zone_start_time'] ?? '' }}
local_time.end_time = {{ $settings['yealink_time_zone_end_time'] ?? '' }}
local_time.offset_time = {{ $settings['yealink_offset_time'] ?? '+0:00' }}
local_time.interval = {{ $settings['yealink_time_update'] ?? 86400 }}  

local_time.ntp_server1 = {{ $settings['ntp_server_primary'] ?? 'pool.ntp.org' }}
local_time.ntp_server2 = {{ $settings['ntp_server_secondary'] ?? '' }}
local_time.time_zone = {{ $settings['yealink_time_zone'] ?? 'GMT+0' }}
local_time.time_zone_name = {{ $settings['yealink_time_zone_name'] ?? 'UTC' }}

################################################################
##                      Rings Settings                        ##
################################################################
@for ($i = 1; $i <= 10; $i++)
distinctive_ring_tones.alert_info.{{ $i }}.text = {{ $settings["yealink_ring_text_{$i}"] ?? '' }}
distinctive_ring_tones.alert_info.{{ $i }}.ringer = {{ $settings["yealink_ring_file_{$i}"] ?? '' }}
@endfor  

################################################################
##                      Backgrounds  Settings                 ##
################################################################
##File Formate:
##SIP-T57W/T54W/T54S/T52S/T48S/T48G/T46G/T46S/T29G/T46U/T48U: .jpg/.png/.bmp/.jpeg;
##Resolution:
##SIP-T57W/T48S/T48G/T48U/T46U:<=2.0 megapixels;
##for SIP-T54W/T46G/T46S/T29G: <=1.8 megapixels;SIP-T54S/T52S:<=4.2 megapixels;
##Single File Size: <=5MB
##2MB of space should bereserved for the phone
wallpaper_upload.url = {{ $settings['yealink_t46u_wallpaper'] ?? '' }}
phone_setting.backgrounds = Config:{{ $settings['yealink_t46u_wallpaper_filename'] ?? '' }}


################################################################
##                      Network Basic                         ##
################################################################
static.network.ip_address_mode = {{ $settings['yealink_ip_address_mode'] ?? '0' }}
static.network.primary_dns = {{ $settings['dns_server_primary'] ?? '' }}
static.network.secondary_dns = {{ $settings['dns_server_secondary'] ?? '' }}
static.network.vlan.pc_port_priority = {{ $settings['yealink_vlan_pc_cos'] ?? '0' }}
static.network.vlan.pc_port_vid = {{ $settings['yealink_vlan_pc_id'] ?? '1' }}
static.network.vlan.pc_port_enable = {{ $settings['yealink_vlan_pc_enable'] ?? '0' }}

################################################################
#                      Network Advanced                       ##
################################################################
static.network.cdp.enable = {{ $settings['yealink_cdp_enable'] ?? '0' }}
static.network.cdp.packet_interval = {{ $settings['yealink_cdp_packet_interval'] ?? '60' }}
static.network.lldp.enable = {{ $settings['yealink_lldp_enable'] ?? '0' }}
static.network.lldp.packet_interval = {{ $settings['yealink_lldp_packet_interval'] ?? '60' }}
static.network.vpn_enable = {{ $settings['yealink_network_vpn_enable'] ?? '0' }}
static.openvpn.url = {{ $settings['yealink_openvpn_url'] ?? '' }}

################################################################
##                      Autop URL                             ##
################################################################
static.auto_provision.server.url = {{ $settings['provision_base_url'] ?? '' }} 
static.auto_provision.server.username = {{ $settings['http_auth_username'] ?? '' }}
static.auto_provision.server.password = {{ $settings['http_auth_password'] ?? '' }}

#################################################################
##                      Firmware Update                        ##
#################################################################
static.firmware.url = {{ $settings['yealink_firmware_t46u'] ?? '' }} 

#################################################################
##                      Security                               ##
#################################################################
static.security.default_ssl_method = {{ $settings['yealink_security_default_ssl_method'] ?? '3' }}
static.security.trust_certificates = {{ $settings['yealink_trust_certificates'] ?? '0' }}
@if (isset($settings['user_name']))
    static.security.user_name.user = {{ $settings['user_name'] }}
    static.security.user_password = {{ $settings['user_name'] }}:{{ $settings['user_password'] }}
@endif
@if (isset($settings['admin_name']))
    static.security.user_name.admin = {{ $settings['admin_name'] }}
    static.security.user_password = {{ $settings['admin_name'] }}:{{ $settings['admin_password'] }}
@endif
@if (isset($settings['var_name']))
    static.security.user_name.var = {{ $settings['var_name'] }}
    static.security.user_password = {{ $settings['var_name'] }}:{{ $settings['var_password'] }}
@endif
sip.trust_ctrl = {{ $settings['yealink_trust_ctrl'] ?? '1' }}
sip.listen_port = {{ $settings['yealink_sip_listen_port'] ?? '5060' }}
phone_setting.called_party_info_display.enable = 0

#################################################################
##                          Transfer                          ##
#################################################################
dialplan.transfer.mode = {{ $settings['yealink_transfer_mode'] ?? '1' }}
transfer.on_hook_trans_enable = {{ $settings['yealink_transfer_onhook'] ?? '1' }}
transfer.tran_others_after_conf_enable = {{ $settings['yealink_transfer_after_conf'] ?? '0' }}
transfer.blind_tran_on_hook_enable = {{ $settings['yealink_transfer_blind_on_hook'] ?? '1' }}
transfer.semi_attend_tran_enable = {{ $settings['yealink_transfer_semi_attended'] ?? '1' }}
phone_setting.call_appearance.transfer_via_new_linekey = {{ $settings['yealink_transfer_via_new_linekey'] ?? '0' }}
transfer.dsskey_deal_type = {{ $settings['yealink_dsskey_transfer_mode'] ?? '0' }}

#################################################################
##                          Features USB Record                ##
#################################################################
features.usb_call_recording.enable = {{ $settings['yealink_usb_record_enable'] ?? '0' }}

@if (!empty($settings['stun_server']) && $settings['stun_server'] !== ''))
    #################################################################
    ##                          NAT&ICE                            ##
    #################################################################
    static.sip.nat_stun.enable = {{ isset($settings['stun_server']) && $settings['stun_server'] !== '' ? 1 : 0 }}
    static.sip.nat_stun.server = {{ $settings['stun_server'] ?? '' }}
    static.sip.nat_stun.port   = {{ $settings['stun_port'] ?? 3478 }}
    static.ice.enable = {{ $settings['yealink_ice_enable'] ?? 0 }}
    static.network.static_nat.enable = {{ isset($settings['yealink_static_nat']) && $settings['yealink_static_nat'] !== '' ? 1 : 0 }}
    static.network.static_nat.addr   = {{ $settings['yealink_static_nat'] ?? '' }}
@endif

@if (!empty($settings['ldap_enable']) && $settings['ldap_enable'] !== ''))
    #################################################################
    ##                          LDAP                               ##
    #################################################################
    ldap.enable = {{ $settings['ldap_enable'] ?? 0 }}
    ldap.user = "{{ $settings['ldap_user'] ?? '' }}"
    ldap.password = "{{ $settings['ldap_password'] ?? '' }}"
    ldap.base = "{{ $settings['ldap_base'] ?? '' }}"
    ldap.port = {{ $settings['ldap_port'] ?? 389 }}
    ldap.host = "{{ $settings['ldap_host'] ?? '' }}"
    
    ldap.customize_label = "{{ $settings['ldap_customize_label'] ?? '' }}"
    ldap.incoming_call_special_search.enable = {{ $settings['ldap_incoming_call_special_search_enable'] ?? 0 }}
    ldap.tls_mode = "{{ $settings['ldap_tls_mode'] ?? '0' }}"         # 0/1/2 model-dependent (none/StartTLS/LDAPS)
    ldap.search_type = "{{ $settings['ldap_search_type'] ?? '' }}"     # e.g., "blf" or model-specific enum
    ldap.numb_display_mode = "{{ $settings['ldap_numb_display_mode'] ?? '' }}"
    
    ldap.ldap_sort = {{ $settings['ldap_sort'] ?? 0 }}
    ldap.call_in_lookup = {{ $settings['ldap_call_in_lookup'] ?? 0 }}
    ldap.version = {{ $settings['ldap_version'] ?? 3 }}
    ldap.display_name = "{{ $settings['ldap_display_name'] ?? 'displayName' }}"
    ldap.numb_attr = "{{ $settings['ldap_numb_attr'] ?? 'telephoneNumber' }}"
    ldap.name_attr = "{{ $settings['ldap_name_attr'] ?? 'cn' }}"
    ldap.max_hits = {{ $settings['ldap_max_hits'] ?? 50 }}
    ldap.number_filter = "{{ $settings['ldap_number_filter'] ?? '(telephoneNumber=%s)' }}"
    ldap.name_filter = "{{ $settings['ldap_name_filter'] ?? '(cn=%s*)' }}"
    ldap.call_out_lookup = {{ $settings['ldap_dial_lookup'] ?? 0 }}
    
    directory_setting.ldap.enable = {{ $settings['directory_ldap_enable'] ?? 0 }}
    directory_setting.ldap.priority = {{ $settings['directory_ldap_priority'] ?? 0 }}
    
    search_in_dialing.ldap.enable = {{ $settings['search_in_dialing_ldap_enable'] ?? 0 }}
    search_in_dialing.ldap.priority = {{ $settings['search_in_dialing_ldap_priority'] ?? 0 }}
@endif

@if (!empty($settings['yealink_wifi_enable']) && $settings['yealink_wifi_enable'] == '1'))
    ################################################################
    ##                      Network WiFi                          ##
    ################################################################
    static.wifi.enable = {{ $settings['yealink_wifi_enable'] ?? 0 }}
    
    static.wifi.1.label = "{{ $settings['yealink_wifi_1_label'] ?? '' }}"
    static.wifi.1.ssid = "{{ $settings['yealink_wifi_1_ssid'] ?? '' }}"
    static.wifi.1.priority = {{ $settings['yealink_wifi_1_priority'] ?? 1 }}
    
    # security_mode examples: open/WEP/WPA/WPA2/WPA3/EAP  (model-dependent)
    static.wifi.1.security_mode = "{{ $settings['yealink_wifi_1_security'] ?? 'WPA2' }}"
    # cipher_type examples: TKIP/CCMP/AES (model-dependent; AES == CCMP)
    static.wifi.1.cipher_type = "{{ $settings['yealink_wifi_1_cipher'] ?? 'AES' }}"
    
    # PSK password (leave empty for open/EAP as needed)
    static.wifi.1.password = "{{ $settings['yealink_wifi_1_password'] ?? '' }}"
    
    # EAP settings (used only if security_mode == EAP)
    static.wifi.1.eap_type = "{{ $settings['yealink_wifi_1_type'] ?? 'PEAP' }}"
    static.wifi.1.eap_user_name = "{{ $settings['yealink_wifi_1_username'] ?? '' }}"
    static.wifi.1.eap_password = "{{ $settings['yealink_wifi_1_password'] ?? '' }}"
    
    # Show scan prompt on phone UI (0/1)
    static.wifi.show_scan_prompt = {{ $settings['yealink_wifi_scan_prompt'] ?? 0 }}
@endif

################################################################
##                      Custom Keys                           ##
################################################################

features.enhanced_dss_keys.enable= {{ $settings['yealink_enhanced_dss_keys'] ?? '0' }}
features.config_dsskey_length = {{ $settings['yealink_dsskey_length'] ?? '0' }}

## Transfer for Voicemail
softkey.1.enable = 1
softkey.1.label = Xfer to VM 
softkey.1.position = 3 
softkey.1.action = *99$PEnter Extension&TTransfer to Voicemail&C4&N$$Trefer$
softkey.1.use.idle = 0
softkey.1.use.on_talk = 1

## Intercom
programablekey.2.type = 73
programablekey.2.line = 1
programablekey.2.value = *8$PEnter Extension&TIntercom Extension&C4&N$ 
programablekey.2.label = Intercom


@endswitch