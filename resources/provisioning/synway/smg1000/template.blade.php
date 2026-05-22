{{-- version: 1.0.1 --}}

@switch($flavor)

{{-- ================= Synway SMG1000 mac.cfg ================= --}}
@case('mac.cfg')

@php
    $primaryLine = [];
    $synwayLines = [];

    foreach (($lines ?? []) as $line) {
        $n = (int)($line['line_number'] ?? 0);

        if (empty($primaryLine)) {
            $primaryLine = $line;
        }

        $synwayLines[] = $line;
    }

    $routeName = $settings['synway_route_name'] ?? 'Voiceware';

    $digitsMapRules1 = $settings['synway_digits_map_rules_1'] ?? [
        '0 *11* 0',
        '1 *12* 0',
        '2 *20* 0',
        '3 *30* 0',
        '4 *47* 0',
        '5 *53* 0',
        '6 *010* 0',
        '7 *48* 0',
        '8 *61* 0',
        '9 *62* 0',
        '10 *020* 0',
        '11 *021* 0',
        '12 *022* 0',
        '13 *030* 0',
        '14 *031* 0',
        '15 *040* 0',
        '16 *041* 0',
        '17 *050* 0',
        '18 *051* 0',
        '19 *060* 0',
        '20 *061* 0',
        '21 *70* 0',
        '22 *070* 0',
        '23 *#88921532*# 0',
        '24 *71* 0',
    ];

    if (!is_array($digitsMapRules1)) {
        $digitsMapRules1 = [];
    }

    $digitsMapRules0 = $settings['synway_digits_map_rules_0'] ?? [
        '30 *2 ' . $routeName,
        '31 [2-9]11 ' . $routeName,
        '32 [8-9]1[2-9]xxxxxxxxx ' . $routeName,
        '33 [8-9][2-9]xxxxxx ' . $routeName,
        '34 *xx ' . $routeName,
        '35 [1-7]xx ' . $routeName,
        '36 911 ' . $routeName,
        '37 x. ' . $routeName,
        '38 911 ' . $routeName,
        '39 777 ' . $routeName,
    ];

    if (!is_array($digitsMapRules0)) {
        $digitsMapRules0 = [];
    }

    $iptablesCmds = $settings['synway_iptables_cmds'] ?? [];
    if (!is_array($iptablesCmds)) {
        $iptablesCmds = [];
    }

    $synwayUsers = $settings['synway_users'] ?? [];
    if (!is_array($synwayUsers)) {
        $synwayUsers = [];
    }

    $toneDetectorItems = $settings['synway_tone_detector_items'] ?? [
        0 => '0,1,0,450,0,600,0,0,5,20',
        1 => '1,2,1,450,0,350,350,2,5,20',
        2 => '2,3,1,450,0,1000,4000,1,5,20',
        3 => '3,4,0,1100,0,250,0,0,5,20',
        4 => '4,5,0,2100,0,250,0,0,5,20',
    ];

    if (!is_array($toneDetectorItems)) {
        $toneDetectorItems = [];
    }

    $toneGeneratorItems = $settings['synway_tone_generator_items'] ?? [
        0 => '450/0',
        1 => '450/1000,0/4000',
        2 => '450/350,0/350',
        4 => '450/200,0/600,450/200,0/1000',
    ];

    if (!is_array($toneGeneratorItems)) {
        $toneGeneratorItems = [];
    }
@endphp

[GWGLOBAL]
global_eth={{ $settings['synway_global_eth'] ?? 0 }}
global_localport={{ $settings['sip_port'] ?? 5060 }}
global_register={{ $settings['synway_global_register'] ?? 1 }}
global_username={{ $settings['synway_global_username'] ?? $primaryLine['auth_id'] ?? '' }}
global_password={{ $settings['synway_global_password'] ?? $primaryLine['password'] ?? '' }}
global_authusername={{ $settings['synway_global_authusername'] ?? $primaryLine['auth_id'] ?? '' }}
global_serveraddress={{ $primaryLine['server_address'] ?? $domain_name ?? '' }}
global_serverport={{ $primaryLine['sip_port'] ?? 5060 }}
global_sipims={{ $settings['synway_global_sipims'] ?? 1 }}
global_outboundaddress={{ $primaryLine['outbound_proxy_primary'] ?? '' }}
global_outboundport={{ $settings['synway_global_outboundport'] ?? $primaryLine['sip_port'] ?? 5060 }}
global_SipRegTimeMs={{ $settings['synway_global_sip_reg_time_ms'] ?? 10 }}
global_regexpires={{ $settings['synway_global_regexpires'] ?? $primaryLine['register_expires'] ?? 120 }}
global_regagainexpires={{ $settings['synway_global_regagainexpires'] ?? 32 }}
global_calledidplace={{ $settings['synway_global_calledidplace'] ?? 1 }}
global_calleridplace={{ $settings['synway_global_calleridplace'] ?? 0 }}
global_getcalleridplace={{ $settings['synway_global_getcalleridplace'] ?? 0 }}
global_forwardinghandlemethod={{ $settings['synway_global_forwardinghandlemethod'] ?? 1 }}
global_flashhandlemethod={{ $settings['synway_global_flashhandlemethod'] ?? 1 }}
global_holdmusicsource={{ $settings['synway_global_holdmusicsource'] ?? 0 }}
global_enablenetcutoffivr={{ $settings['synway_global_enablenetcutoffivr'] ?? 1 }}
IpCallINEnableIptables={{ $settings['synway_ip_call_in_enable_iptables'] ?? 0 }}
OpenFlashEnable={{ $settings['synway_open_flash_enable'] ?? 1 }}
OpenFlashKey={{ $settings['synway_open_flash_key'] ?? 42 }}
OpenFlashWay={{ $settings['synway_open_flash_way'] ?? 0 }}
global_FxsTxDtmfCallOutWaitConn={{ $settings['synway_global_fxs_tx_dtmf_call_out_wait_conn'] ?? 1 }}
global_enabledisplaydtmf={{ $settings['synway_global_enable_display_dtmf'] ?? 1 }}

[SIP4CALLOUT]
siptrunks_fromChange={{ $settings['synway_siptrunks_from_change'] ?? 0 }}
siptrunks_DNSResolveDomain={{ $settings['synway_siptrunks_dns_resolve_domain'] ?? 1 }}
siptrunks_DNSHeart={{ $settings['synway_siptrunks_dns_heart'] ?? 0 }}
siptrunks_LoadBalancingMethod={{ $settings['synway_siptrunks_load_balancing_method'] ?? 0 }}
siptrunks_RedundancyMode={{ $settings['synway_siptrunks_redundancy_mode'] ?? 0 }}

[DigitsMapRules1]
PrefixRulesNum={{ $settings['synway_digits_map_rules_1_num'] ?? count($digitsMapRules1) }}
@foreach ($digitsMapRules1 as $idx => $rule)
PrefixRules{{ $idx }}={{ $rule }}
@endforeach

[DigitsMapRules0]
@foreach ($digitsMapRules0 as $idx => $rule)
PrefixRules{{ $idx }}={{ $rule }}
@endforeach
PrefixRulesNum={{ $settings['synway_digits_map_rules_0_num'] ?? count($digitsMapRules0) }}

[DialTimeout]
TimeoutRules0={{ $settings['synway_timeout_rules_0'] ?? 6 }}

[DSCPCtrl]
EnableDscp={{ $settings['synway_enable_dscp'] ?? 1 }}
Ctrl_Rtp={{ $settings['synway_dscp_rtp'] ?? 46 }}
Ctrl_Sip={{ $settings['synway_dscp_sip'] ?? 26 }}

[TDMPorts]
port_max={{ $settings['synway_port_max'] ?? count($synwayLines) }}
@foreach ($synwayLines as $idx => $line)
@php
    $portId = $line['synway_port_id'] ?? $line['port_id'] ?? $line['tdm_port_id'] ?? $line['line_number'] ?? ($idx + 1);
    $username = $line['synway_username'] ?? $line['auth_id'] ?? $line['extension'] ?? '';
    $password = $line['synway_password'] ?? $line['password'] ?? '';
    $authUsername = $line['synway_authusername'] ?? $line['auth_id'] ?? $username;
    $portInfoTail = $line['synway_port_info_tail'] ?? '0 <@#> <@#> 0 0 0 0 0 <@#> 0 1 0 1 <@#> 0 0';
@endphp
port_info{{ $portId }}={{ $username }} {{ $password }} {{ $authUsername }} {{ $portInfoTail }}
@endforeach

[SysInfo]
WebPort={{ $settings['synway_web_port'] ?? 80 }}
WebLimit={{ $settings['synway_web_limit'] ?? 0 }}
LimitIp={{ $settings['synway_limit_ip'] ?? '' }}
SetTelnet={{ $settings['synway_set_telnet'] ?? 1 }}
EnableFTP={{ $settings['synway_enable_ftp'] ?? 1 }}
SetCDR={{ $settings['synway_set_cdr'] ?? 1 }}
CDRIP={{ $settings['synway_cdr_ip'] ?? '127.0.0.1' }}
CDRPort={{ $settings['synway_cdr_port'] ?? 3 }}
CDRSelect={{ $settings['synway_cdr_select'] ?? '0,1,2,3,4,5,6,7,8' }}
CDRDownloadEn={{ $settings['synway_cdr_download_enable'] ?? 1 }}
NTPZone={{ $settings['synway_ntp_zone'] ?? $settings['time_zone'] ?? 'GMT+8' }}
SetNTP={{ $settings['synway_set_ntp'] ?? 1 }}
NTPIP={{ $settings['synway_ntp_ip'] ?? $settings['ntp_server_primary'] ?? 'pool.ntp.org' }}
NTPCycle={{ $settings['synway_ntp_cycle'] ?? 120 }}
SetReboot={{ $settings['synway_set_reboot'] ?? 1 }}
RebootHour={{ $settings['synway_reboot_hour'] ?? 5 }}
RebootMin={{ $settings['synway_reboot_min'] ?? 0 }}

[WebCtrl]
EnableVerify={{ $settings['synway_web_enable_verify'] ?? 0 }}

[NetConfig]
ConnectMode={{ $settings['synway_connect_mode'] ?? 2 }}
ConnectWay1={{ $settings['synway_connect_way_1'] ?? 1 }}
AutoDns1={{ $settings['synway_auto_dns_1'] ?? 1 }}
IpAddr1={{ $settings['synway_ip_addr_1'] ?? $settings['ip_address'] ?? '' }}
Subnet1={{ $settings['synway_subnet_1'] ?? $settings['subnet'] ?? '' }}
Gateway1={{ $settings['synway_gateway_1'] ?? $settings['gateway'] ?? '' }}
DNS1={{ $settings['synway_dns_1'] ?? $settings['dns_server_primary'] ?? '' }}
Backup_DNS1={{ $settings['synway_backup_dns_1'] ?? $settings['dns_server_secondary'] ?? '' }}
EnableIPv6={{ $settings['synway_enable_ipv6'] ?? '' }}
IpAddr1V6={{ $settings['synway_ip_addr_1_v6'] ?? '' }}
Subnet1V6={{ $settings['synway_subnet_1_v6'] ?? '' }}
Gateway1V6={{ $settings['synway_gateway_1_v6'] ?? '' }}
DNS1V6={{ $settings['synway_dns_1_v6'] ?? '' }}
OpenLan2={{ $settings['synway_open_lan_2'] ?? '' }}
ConnectWay2={{ $settings['synway_connect_way_2'] ?? '' }}
AutoDns2={{ $settings['synway_auto_dns_2'] ?? '' }}
ipaddress2_dhcp={{ $settings['synway_ipaddress_2_dhcp'] ?? '' }}
subnet2_dhcp={{ $settings['synway_subnet_2_dhcp'] ?? '' }}
ip_start2_dhcp={{ $settings['synway_ip_start_2_dhcp'] ?? '' }}
ip_end2_dhcp={{ $settings['synway_ip_end_2_dhcp'] ?? '' }}
dhcp_lease_time2={{ $settings['synway_dhcp_lease_time_2'] ?? '' }}
IpAddr2={{ $settings['synway_ip_addr_2'] ?? '' }}
Subnet2={{ $settings['synway_subnet_2'] ?? '' }}
Gateway2={{ $settings['synway_gateway_2'] ?? '' }}
DNS2={{ $settings['synway_dns_2'] ?? '' }}
Backup_DNS2={{ $settings['synway_backup_dns_2'] ?? '' }}
Enable2IPv6={{ $settings['synway_enable_2_ipv6'] ?? '' }}
IpAddr2V6={{ $settings['synway_ip_addr_2_v6'] ?? '' }}
Subnet2V6={{ $settings['synway_subnet_2_v6'] ?? '' }}
Gateway2V6={{ $settings['synway_gateway_2_v6'] ?? '' }}
DNS2V6={{ $settings['synway_dns_2_v6'] ?? '' }}

[AutoDeployConfig]
UrlMethod={{ $settings['synway_url_method'] ?? 1 }}
HttpsUpdateEnable={{ $settings['synway_https_update_enable'] ?? 1 }}
HttpsUrl={{ $settings['synway_https_url'] ?? $settings['provision_base_url'] ?? '' }}
HttpsUpdateMethod={{ $settings['synway_https_update_method'] ?? 3 }}
HttpsUpdateTime={{ $settings['synway_https_update_time'] ?? 10080 }}
HttpsUpdateDHCPTime={{ $settings['synway_https_update_dhcp_time'] ?? 10080 }}
HttpNeedAuth={{ $settings['synway_http_need_auth'] ?? (!empty($settings['http_auth_username']) ? 1 : 0) }}
HttpsUser={{ $settings['synway_https_user'] ?? $settings['http_auth_username'] ?? '' }}
HttpsPassword={{ $settings['synway_https_password'] ?? $settings['http_auth_password'] ?? '' }}

[Tr069Config]
Enable={{ $settings['synway_tr069_enable'] ?? 1 }}
AcsUrl={{ $settings['synway_tr069_acs_url'] ?? '' }}
AuthMode={{ $settings['synway_tr069_auth_mode'] ?? 1 }}
UserName={{ $settings['synway_tr069_username'] ?? '' }}
Password={{ $settings['synway_tr069_password'] ?? '' }}
InformInterval={{ $settings['synway_tr069_inform_interval'] ?? '' }}
EnableStun={{ $settings['synway_tr069_enable_stun'] ?? (!empty($settings['stun_server']) ? 1 : '') }}
StunServer={{ $settings['synway_tr069_stun_server'] ?? $settings['stun_server'] ?? '' }}
StunServerPort={{ $settings['synway_tr069_stun_server_port'] ?? $settings['stun_port'] ?? '' }}

[IPTABLES]
Num={{ $settings['synway_iptables_num'] ?? count($iptablesCmds) }}
@foreach ($iptablesCmds as $idx => $cmd)
Cmd{{ $idx }}={{ $cmd }}
@endforeach

[Version]
GWSvrV={{ $settings['synway_gwsvr_version'] ?? '' }}
DownloadUrl={{ $settings['synway_firmware_url'] ?? '' }}

[UserInfo]
{{--  UserName={{ $settings['synway_current_admin_name'] ?? 'admin' }}
Pwd={{ $settings['synway_current_admin_password'] ?? '' }}
NewAdminName={{ $settings['synway_new_admin_name'] ?? $settings['admin_name'] ?? '' }}
NewAdminPwd={{ $settings['synway_new_admin_password'] ??  'Admin@75180' }} --}}

[USER]
UserNum={{ $settings['synway_user_num'] ?? count($synwayUsers) }}
@foreach ($synwayUsers as $idx => $user)
username{{ $idx }}={{ $user['username'] ?? '' }}
userpass{{ $idx }}={{ $user['password'] ?? '' }}
@endforeach

====><====

[SIP]
SipTransportProtocol={{ $settings['synway_sip_transport_protocol'] ?? 0 }}
RequestUseSourceIp={{ $settings['synway_request_use_source_ip'] ?? 0 }}
RequestUseContact={{ $settings['synway_request_use_contact'] ?? 0 }}
MaxWaitAutoDialAnswerTime={{ $settings['synway_max_wait_auto_dial_answer_time'] ?? 60 }}
UserAgent={{ $settings['synway_user_agent'] ?? 'Phonesuite PSG-10' }}
RemoteCrashCheckInterval={{ $settings['synway_remote_crash_check_interval'] ?? 0 }}
SipCallCheckInterval={{ $settings['synway_sip_call_check_interval'] ?? '' }}
SendOptionsInterval={{ $settings['synway_send_options_interval'] ?? 10 }}
RetransmitLost200OK={{ $settings['synway_retransmit_lost_200_ok'] ?? '' }}
EnabSipSessionExpires={{ $settings['synway_enable_sip_session_expires'] ?? 0 }}
SipSessionExpires={{ $settings['synway_sip_session_expires'] ?? '' }}
SipSessionExpiresMin={{ $settings['synway_sip_session_expires_min'] ?? '' }}
AutoNatType={{ $settings['synway_auto_nat_type'] ?? '' }}
MapContactIP={{ $settings['synway_map_contact_ip'] ?? $settings['external_ip'] ?? '' }}
UseRport={{ $settings['synway_use_rport'] ?? 1 }}
LearnNat={{ $settings['synway_learn_nat'] ?? 1 }}
AutoDetectNat={{ $settings['synway_auto_detect_nat'] ?? 1 }}
AutoDetectRemoteRTPAddress={{ $settings['synway_auto_detect_remote_rtp_address'] ?? 0 }}

[SystemConfig]
SipEnableEncrypt={{ $settings['synway_sip_enable_encrypt'] ?? 0 }}
EncryptCriterion={{ $settings['synway_encrypt_criterion'] ?? '' }}
EncryptTag={{ $settings['synway_encrypt_tag'] ?? '' }}
EncryptKey={{ $settings['synway_encrypt_key'] ?? '' }}
RtpEnableEncrypt={{ $settings['synway_rtp_enable_encrypt'] ?? 0 }}
SipRequire100rel={{ $settings['synway_sip_require_100rel'] ?? 0 }}
EnableSilenceSuppressionFlag={{ $settings['synway_enable_silence_suppression_flag'] ?? 0 }}
IPDefaultSpeakVolume={{ $settings['synway_ip_default_speak_volume'] ?? 0 }}
TonePlayEnergy={{ $settings['synway_tone_play_energy'] ?? -6 }}
OpenCheckFlash={{ $settings['synway_open_check_flash'] ?? 0 }}
LocalHookFilterTime={{ $settings['synway_local_hook_filter_time'] ?? 80 }}
MaxLocalFlashTime={{ $settings['synway_max_local_flash_time'] ?? 700 }}
MsgWaiting={{ $settings['synway_msg_waiting'] ?? '' }}
DtmfDetector={{ $settings['synway_dtmf_detector'] ?? '5,9,23,36,838,10,50,60,-21,-15,-3,-3,0' }}
DtmfPlayEnergy={{ $settings['synway_dtmf_play_energy'] ?? -145 }}

[BoardId={{ $settings['synway_board_id'] ?? 0 }}]
AudioCodecList={{ $settings['synway_audio_codec_list'] ?? '0,8' }}
SizeG711A={{ $settings['synway_size_g711a'] ?? 20 }}
SizeG711U={{ $settings['synway_size_g711u'] ?? 20 }}
SizeG729={{ $settings['synway_size_g729'] ?? 20 }}
SizeIlbc={{ $settings['synway_size_ilbc'] ?? 30 }}
SizeAMR={{ $settings['synway_size_amr'] ?? 20 }}
SizeG723={{ $settings['synway_size_g723'] ?? 30 }}
EnableRTPStun={{ $settings['synway_enable_rtp_stun'] ?? (!empty($settings['stun_server']) ? 1 : '') }}
StunServerIP={{ $settings['synway_stun_server_ip'] ?? $settings['stun_server'] ?? '' }}
MapIP={{ $settings['synway_map_ip'] ?? $settings['external_ip'] ?? '' }}
SendDtmfType={{ $settings['synway_send_dtmf_type'] ?? $settings['dtmf_type'] ?? 0 }}
TelephoneEventsPt={{ $settings['synway_telephone_events_pt'] ?? 101 }}
RTPRange={{ $settings['synway_rtp_range'] ?? (($settings['rtp_start_port'] ?? '10000') . ',' . ($settings['rtp_end_port'] ?? '20000')) }}
SupportG729B={{ $settings['synway_support_g729b'] ?? '' }}
OmitABCD={{ $settings['synway_omit_abcd'] ?? '' }}

[ToneDetector]
MaxToneDetectorItem={{ $settings['synway_max_tone_detector_item'] ?? count($toneDetectorItems) }}
@foreach ($toneDetectorItems as $idx => $item)
ToneDetectorItem{{ $idx }}={{ $item }}
@endforeach

[ToneGenerator]
@foreach ($toneGeneratorItems as $idx => $item)
ToneGeneratorItem{{ $idx }}={{ $item }}
@endforeach

@break

@endswitch