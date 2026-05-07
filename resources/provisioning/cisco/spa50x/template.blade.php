{{-- version: 1.0.1 --}}

@switch($flavor)

{{-- ================= Cisco SPA50xG mac.xml ================= --}}
@case('mac.xml')

<flat-profile>

<Restricted_Access_Domains ua="na"></Restricted_Access_Domains>
<Enable_Web_Server ua="na">Yes</Enable_Web_Server>
<Web_Server_Port ua="na">80</Web_Server_Port>
<Enable_Web_Admin_Access ua="na">Yes</Enable_Web_Admin_Access>
<Admin_Passwd ua="na">{{ $settings['admin_password'] ?? '' }}</Admin_Passwd>
<User_Password ua="rw"></User_Password>
<Signaling_Protocol ua="na">SIP</Signaling_Protocol> <SPCP_Auto-detect ua="na">Yes</SPCP_Auto-detect>
<Phone-UI-readonly ua="na">No</Phone-UI-readonly>
<Phone-UI-user-mode ua="na">No</Phone-UI-user-mode>


<Syslog_Server group="System/Optional_Network_Configuration" />
<Debug_Server group="System/Optional_Network_Configuration" />
<Debug_Level group="System/Optional_Network_Configuration">0</Debug_Level>
<Primary_NTP_Server>{{ $settings['ntp_server_primary'] ?? 'pool.ntp.org' }}</Primary_NTP_Server>
<Secondary_NTP_Server>{{ $settings['ntp_server_secondary'] ?? '' }}</Secondary_NTP_Server>  
<Enable_VLAN group="System/VLAN_Settings">Yes</Enable_VLAN>
<Enable_CDP group="System/VLAN_Settings">Yes</Enable_CDP>
<VLAN_ID group="System/VLAN_Settings">1</VLAN_ID>
<PC_Port_VLAN_Highest_Priority group="System/VLAN_Settings">No Limit</PC_Port_VLAN_Highest_Priority>
<Enable_PC_Port_VLAN_Tagging group="System/VLAN_Settings">No</Enable_PC_Port_VLAN_Tagging>
<PC_Port_VLAN_ID group="System/VLAN_Settings">1</PC_Port_VLAN_ID>
<Max_Forward group="SIP/SIP_Parameters">70</Max_Forward>
<Max_Redirection group="SIP/SIP_Parameters">5</Max_Redirection>
<Max_Auth group="SIP/SIP_Parameters">2</Max_Auth>
<SIP_User_Agent_Name group="SIP/SIP_Parameters">$VERSION</SIP_User_Agent_Name>
<SIP_Server_Name group="SIP/SIP_Parameters">$VERSION</SIP_Server_Name>
<SIP_Reg_User_Agent_Name group="SIP/SIP_Parameters" />
<SIP_Accept_Language group="SIP/SIP_Parameters" />
<DTMF_Relay_MIME_Type group="SIP/SIP_Parameters">application/dtmf-relay</DTMF_Relay_MIME_Type>
<Remove_Last_Reg group="SIP/SIP_Parameters">No</Remove_Last_Reg>
<Use_Compact_Header group="SIP/SIP_Parameters">No</Use_Compact_Header>
<Escape_Display_Name group="SIP/SIP_Parameters">No</Escape_Display_Name>
<SIP-B_Enable group="SIP/SIP_Parameters">No</SIP-B_Enable>
<Talk_Package group="SIP/SIP_Parameters">No</Talk_Package>
<Hold_Package group="SIP/SIP_Parameters">No</Hold_Package>
<Conference_Package group="SIP/SIP_Parameters">No</Conference_Package>
<Notify_Conference group="SIP/SIP_Parameters">No</Notify_Conference>
<RFC_2543_Call_Hold group="SIP/SIP_Parameters">Yes</RFC_2543_Call_Hold>
<Random_REG_CID_On_Reboot group="SIP/SIP_Parameters">No</Random_REG_CID_On_Reboot>
<Mark_All_AVT_Packets group="SIP/SIP_Parameters">Yes</Mark_All_AVT_Packets>
<SIP_TCP_Port_Min group="SIP/SIP_Parameters">5060</SIP_TCP_Port_Min>
<SIP_TCP_Port_Max group="SIP/SIP_Parameters">15080</SIP_TCP_Port_Max>
<CTI_Enable group="SIP/SIP_Parameters">No</CTI_Enable>
<Caller_ID_Header group="SIP/SIP_Parameters">PAID-RPID-FROM</Caller_ID_Header>
<SRTP_Method group="SIP/SIP_Parameters">x-sipura</SRTP_Method>
<Hold_Target_Before_REFER group="SIP/SIP_Parameters">No</Hold_Target_Before_REFER>
<SIP_T1 group="SIP/SIP_Timer_Values__sec_">.5</SIP_T1>
<SIP_T2 group="SIP/SIP_Timer_Values__sec_">4</SIP_T2>
<SIP_T4 group="SIP/SIP_Timer_Values__sec_">5</SIP_T4>
<SIP_Timer_B group="SIP/SIP_Timer_Values__sec_">16</SIP_Timer_B>
<SIP_Timer_F group="SIP/SIP_Timer_Values__sec_">16</SIP_Timer_F>
<SIP_Timer_H group="SIP/SIP_Timer_Values__sec_">16</SIP_Timer_H>
<SIP_Timer_D group="SIP/SIP_Timer_Values__sec_">16</SIP_Timer_D>
<SIP_Timer_J group="SIP/SIP_Timer_Values__sec_">16</SIP_Timer_J>
<INVITE_Expires group="SIP/SIP_Timer_Values__sec_">240</INVITE_Expires>
<ReINVITE_Expires group="SIP/SIP_Timer_Values__sec_">30</ReINVITE_Expires>
<Reg_Min_Expires group="SIP/SIP_Timer_Values__sec_">1</Reg_Min_Expires>
<Reg_Max_Expires group="SIP/SIP_Timer_Values__sec_">7200</Reg_Max_Expires>
<Reg_Retry_Intvl group="SIP/SIP_Timer_Values__sec_">30</Reg_Retry_Intvl>
<Reg_Retry_Long_Intvl group="SIP/SIP_Timer_Values__sec_">1200</Reg_Retry_Long_Intvl>
<Reg_Retry_Random_Delay group="SIP/SIP_Timer_Values__sec_" />
<Reg_Retry_Long_Random_Delay group="SIP/SIP_Timer_Values__sec_" />
<Reg_Retry_Intvl_Cap group="SIP/SIP_Timer_Values__sec_" />
<Sub_Min_Expires group="SIP/SIP_Timer_Values__sec_">10</Sub_Min_Expires>
<Sub_Max_Expires group="SIP/SIP_Timer_Values__sec_">7200</Sub_Max_Expires>
<Sub_Retry_Intvl group="SIP/SIP_Timer_Values__sec_">10</Sub_Retry_Intvl>
<SIT1_RSC group="SIP/Response_Status_Code_Handling" />
<SIT2_RSC group="SIP/Response_Status_Code_Handling" />
<SIT3_RSC group="SIP/Response_Status_Code_Handling" />
<SIT4_RSC group="SIP/Response_Status_Code_Handling" />
<Try_Backup_RSC group="SIP/Response_Status_Code_Handling" />
<Retry_Reg_RSC group="SIP/Response_Status_Code_Handling" />
<RTP_Port_Min group="SIP/RTP_Parameters">16384</RTP_Port_Min>
<RTP_Port_Max group="SIP/RTP_Parameters">32768</RTP_Port_Max>
<RTP_Packet_Size group="SIP/RTP_Parameters">0.020</RTP_Packet_Size>
<Max_RTP_ICMP_Err group="SIP/RTP_Parameters">0</Max_RTP_ICMP_Err>
<RTCP_Tx_Interval group="SIP/RTP_Parameters">0</RTCP_Tx_Interval>
<No_UDP_Checksum group="SIP/RTP_Parameters">No</No_UDP_Checksum>
<Symmetric_RTP group="SIP/RTP_Parameters">No</Symmetric_RTP>
<Stats_In_BYE group="SIP/RTP_Parameters">No</Stats_In_BYE>
<AVT_Dynamic_Payload group="SIP/SDP_Payload_Types">101</AVT_Dynamic_Payload>
<INFOREQ_Dynamic_Payload group="SIP/SDP_Payload_Types" />
<G726r16_Dynamic_Payload group="SIP/SDP_Payload_Types">98</G726r16_Dynamic_Payload>
<G726r24_Dynamic_Payload group="SIP/SDP_Payload_Types">97</G726r24_Dynamic_Payload>
<G726r32_Dynamic_Payload group="SIP/SDP_Payload_Types">2</G726r32_Dynamic_Payload>
<G726r40_Dynamic_Payload group="SIP/SDP_Payload_Types">96</G726r40_Dynamic_Payload>
<G729b_Dynamic_Payload group="SIP/SDP_Payload_Types">99</G729b_Dynamic_Payload>
<EncapRTP_Dynamic_Payload group="SIP/SDP_Payload_Types">112</EncapRTP_Dynamic_Payload>
<RTP-Start-Loopback_Dynamic_Payload group="SIP/SDP_Payload_Types">113</RTP-Start-Loopback_Dynamic_Payload>
<RTP-Start-Loopback_Codec group="SIP/SDP_Payload_Types">G711u</RTP-Start-Loopback_Codec>
<AVT_Codec_Name group="SIP/SDP_Payload_Types">telephone-event</AVT_Codec_Name>
<G711u_Codec_Name group="SIP/SDP_Payload_Types">PCMU</G711u_Codec_Name>
<G711a_Codec_Name group="SIP/SDP_Payload_Types">PCMA</G711a_Codec_Name>
<G726r16_Codec_Name group="SIP/SDP_Payload_Types">G726-16</G726r16_Codec_Name>
<G726r24_Codec_Name group="SIP/SDP_Payload_Types">G726-24</G726r24_Codec_Name>
<G726r32_Codec_Name group="SIP/SDP_Payload_Types">G726-32</G726r32_Codec_Name>
<G726r40_Codec_Name group="SIP/SDP_Payload_Types">G726-40</G726r40_Codec_Name>
<G729a_Codec_Name group="SIP/SDP_Payload_Types">G729a</G729a_Codec_Name>
<G729b_Codec_Name group="SIP/SDP_Payload_Types">G729ab</G729b_Codec_Name>
<G722_Codec_Name group="SIP/SDP_Payload_Types">G722</G722_Codec_Name>
<EncapRTP_Codec_Name group="SIP/SDP_Payload_Types">encaprtp</EncapRTP_Codec_Name>
<Handle_VIA_received group="SIP/NAT_Support_Parameters">No</Handle_VIA_received>
<Handle_VIA_rport group="SIP/NAT_Support_Parameters">{{ $settings['spa_handle_via_rport'] ?? '' }}</Handle_VIA_rport>
<Insert_VIA_received group="SIP/NAT_Support_Parameters">No</Insert_VIA_received>
<Insert_VIA_rport group="SIP/NAT_Support_Parameters">{{ $settings['spa_insert_via_rport'] ?? '' }}</Insert_VIA_rport>
<Substitute_VIA_Addr group="SIP/NAT_Support_Parameters">No</Substitute_VIA_Addr>
<Send_Resp_To_Src_Port group="SIP/NAT_Support_Parameters">No</Send_Resp_To_Src_Port>
<STUN_Enable group="SIP/NAT_Support_Parameters">No</STUN_Enable>
<STUN_Test_Enable group="SIP/NAT_Support_Parameters">No</STUN_Test_Enable>
<STUN_Server group="SIP/NAT_Support_Parameters" />
<EXT_IP group="SIP/NAT_Support_Parameters" />
<EXT_RTP_Port_Min group="SIP/NAT_Support_Parameters" />
<NAT_Keep_Alive_Intvl group="SIP/NAT_Support_Parameters">15</NAT_Keep_Alive_Intvl>
<Linksys_Key_System group="SIP/Linksys_Key_System_Parameters">No</Linksys_Key_System>
<Force_LAN_Codec group="SIP/Linksys_Key_System_Parameters">none</Force_LAN_Codec>
<Provision_Enable group="Provisioning/Configuration_Profile">Yes</Provision_Enable>
<Resync_On_Reset group="Provisioning/Configuration_Profile">Yes</Resync_On_Reset>
<Resync_Random_Delay group="Provisioning/Configuration_Profile">2</Resync_Random_Delay>
<Resync_Periodic group="Provisioning/Configuration_Profile">0</Resync_Periodic>
<Resync_Error_Retry_Delay group="Provisioning/Configuration_Profile">300</Resync_Error_Retry_Delay>
<Forced_Resync_Delay group="Provisioning/Configuration_Profile">30</Forced_Resync_Delay>
<Resync_From_SIP group="Provisioning/Configuration_Profile">Yes</Resync_From_SIP>
<Resync_After_Upgrade_Attempt group="Provisioning/Configuration_Profile">Yes</Resync_After_Upgrade_Attempt>
<Resync_Trigger_1 group="Provisioning/Configuration_Profile" />
<Resync_Trigger_2 group="Provisioning/Configuration_Profile" />
<Resync_Fails_On_FNF group="Provisioning/Configuration_Profile">Yes</Resync_Fails_On_FNF>

@if(!empty($settings['http_auth_username']))
<Profile_Rule group="Provisioning/Configuration_Profile">[--uid {{ $settings['http_auth_username'] }} --pwd {{ $settings['http_auth_password'] ?? '' }}]http://{{ $domain_name }}/prov/$MA.xml</Profile_Rule>
@else
<Profile_Rule group="Provisioning/Configuration_Profile">http://{{ $domain_name }}/prov/$MA.xml</Profile_Rule>
@endif

<Profile_Rule_B group="Provisioning/Configuration_Profile" />
<Profile_Rule_C group="Provisioning/Configuration_Profile" />
<Profile_Rule_D group="Provisioning/Configuration_Profile" />
<Log_Resync_Request_Msg group="Provisioning/Configuration_Profile">$PN $MAC -- Requesting resync $SCHEME://$SERVIP:$PORT$PATH</Log_Resync_Request_Msg>
<Log_Resync_Success_Msg group="Provisioning/Configuration_Profile">$PN $MAC -- Successful resync $SCHEME://$SERVIP:$PORT$PATH</Log_Resync_Success_Msg>
<Log_Resync_Failure_Msg group="Provisioning/Configuration_Profile">$PN $MAC -- Resync failed: $ERR</Log_Resync_Failure_Msg>
<Report_Rule group="Provisioning/Configuration_Profile" />
<User_Configurable_Resync group="Provisioning/Configuration_Profile">No</User_Configurable_Resync>
<Upgrade_Enable group="Provisioning/Firmware_Upgrade">{{ $settings['spa_upgrade_enable'] ?? 'Yes' }}</Upgrade_Enable>
<Upgrade_Error_Retry_Delay group="Provisioning/Firmware_Upgrade">3600</Upgrade_Error_Retry_Delay>
<Downgrade_Rev_Limit group="Provisioning/Firmware_Upgrade" />
<Upgrade_Rule group="Provisioning/Firmware_Upgrade">{{ $settings['cisco_spa50x_firmware_rule'] ?? '' }}</Upgrade_Rule>
<Log_Upgrade_Request_Msg group="Provisioning/Firmware_Upgrade">$PN $MAC -- Requesting upgrade $SCHEME://$SERVIP:$PORT$PATH</Log_Upgrade_Request_Msg>
<Log_Upgrade_Success_Msg group="Provisioning/Firmware_Upgrade">$PN $MAC -- Successful upgrade $SCHEME://$SERVIP:$PORT$PATH -- $ERR</Log_Upgrade_Success_Msg>
<Log_Upgrade_Failure_Msg group="Provisioning/Firmware_Upgrade">$PN $MAC -- Upgrade failed: $ERR</Log_Upgrade_Failure_Msg>
<License_Keys group="Provisioning/Firmware_Upgrade" />
<GPP_A group="Provisioning/General_Purpose_Parameters" />
<GPP_B group="Provisioning/General_Purpose_Parameters" />
<GPP_C group="Provisioning/General_Purpose_Parameters" />
<GPP_D group="Provisioning/General_Purpose_Parameters" />
<GPP_E group="Provisioning/General_Purpose_Parameters" />
<GPP_F group="Provisioning/General_Purpose_Parameters" />
<GPP_G group="Provisioning/General_Purpose_Parameters" />
<GPP_H group="Provisioning/General_Purpose_Parameters" />
<GPP_I group="Provisioning/General_Purpose_Parameters" />
<GPP_J group="Provisioning/General_Purpose_Parameters" />
<GPP_K group="Provisioning/General_Purpose_Parameters" />
<GPP_L group="Provisioning/General_Purpose_Parameters" />
<GPP_M group="Provisioning/General_Purpose_Parameters" />
<GPP_N group="Provisioning/General_Purpose_Parameters" />
<GPP_O group="Provisioning/General_Purpose_Parameters" />
<GPP_P group="Provisioning/General_Purpose_Parameters" />
<Time_Zone group="Regional/Miscellaneous">{{ $settings['spa_time_zone'] ?? '' }}</Time_Zone>
<Time_Offset__HH_mm_ group="Regional/Miscellaneous" />
<Daylight_Saving_Time_Rule group="Regional/Miscellaneous">start={{ $settings['daylight_savings_start_month'] ?? '' }}/{{ $settings['daylight_savings_start_day'] ?? '' }}/{{ $settings['daylight_savings_start_weekday'] ?? '' }}/{{ $settings['daylight_savings_start_time'] ?? '' }}:0:0;end={{ $settings['daylight_savings_stop_month'] ?? '' }}/{{ $settings['daylight_savings_stop_day'] ?? '' }}/{{ $settings['daylight_savings_stop_weekday'] ?? '' }}/{{ $settings['daylight_savings_stop_time'] ?? '' }}:0:0;save=1</Daylight_Saving_Time_Rule>
<Daylight_Saving_Time_Enable group="Regional/Miscellaneous">Yes</Daylight_Saving_Time_Enable>

@php $firstLine = $lines[0] ?? current($lines) ?? []; @endphp
<Voice_Mail_Number group="Phone/General">{{ $settings['voicemail_number'] ?? '' }}</Voice_Mail_Number>
<Text_Logo group="Phone/General" />
<BMP_Picture_Download_URL group="Phone/General" />
<Select_Logo group="Phone/General">Default</Select_Logo>
<Select_Background_Picture group="Phone/General">None</Select_Background_Picture>
<Screen_Saver_Enable group="Phone/General">No</Screen_Saver_Enable>
<Screen_Saver_Wait group="Phone/General">300</Screen_Saver_Wait>
<Screen_Saver_Icon group="Phone/General">Background Picture</Screen_Saver_Icon>

@php
    $keysById = collect($keys ?? [])
        ->where('area', 'main')
        ->keyBy('id');
@endphp

@for ($i = 1; $i <= 8; $i++)
@php
    $row = $keysById->get($i, []);

    $keyId = $i;
    $keyType = strtolower(trim((string)($row['type'] ?? $row['key_type'] ?? '')));
    $keyLine = $row['line'] ?? $row['key_line'] ?? '';
    $keyLabel = trim((string)($row['label'] ?? $row['key_label'] ?? ''));
    $keyValue = trim((string)($row['value'] ?? $row['key_value'] ?? ''));

    $hasValue = $keyValue !== '';
    $hasLabel = $keyLabel !== '';

    // Default every key to Disabled.
    // Only actual line keys get a line assignment.
    $extensionValue = 'Disabled';

    if ($keyType === 'line' && $keyLine !== '') {
        $extensionValue = $keyLine;
    }

    if (in_array($keyType, ['blf', 'speed_dial', 'park', 'check_voicemail'], true)) {
        $extensionValue = 'Disabled';
    }

    $shortName = $hasLabel ? $keyLabel : ' ';

    $extendedFunction = ' ';

    if ($keyType === 'blf' && $hasValue) {
        $extendedFunction = 'fnc=blf+sd+cp;sub=' . $keyValue . '@$PROXY';
    }

    if ($keyType === 'speed_dial' && $hasValue) {
        $extendedFunction = 'fnc=sd;ext=' . $keyValue . '@$PROXY';
    }

    if ($keyType === 'park' && $hasValue) {
        $extendedFunction = 'fnc=blf+sd+cp;sub=' . $keyValue . '@$PROXY';
    }

    if ($keyType === 'check_voicemail') {
        $voicemailValue = $hasValue ? $keyValue : '';
        $extendedFunction = 'fnc=blf+sd+cp;sub=' . $voicemailValue . '@$PROXY';
    }
@endphp

<Extension_{{ $keyId }}_ group="Phone/Line_Key_{{ $keyId }}">{!! $extensionValue !!}</Extension_{{ $keyId }}_>
<Short_Name_{{ $keyId }}_ group="Phone/Line_Key_{{ $keyId }}">{!! $shortName !!}</Short_Name_{{ $keyId }}_>
<Extended_Function_{{ $keyId }}_ group="Phone/Line_Key_{{ $keyId }}">{!! $extendedFunction !!}</Extended_Function_{{ $keyId }}_>

@endfor



@foreach ($lines as $line)
    @php $n = (int)($line['line_number'] ?? 0); @endphp
    @continue($n <= 0)



<Line_Enable_{{ $n }}_ group="Ext_{{ $n }}/General">{{ !empty($line['password']) ? 'Yes' : 'No' }}</Line_Enable_{{ $n }}_>
<Share_Ext_{{ $n }}_ group="Ext_{{ $n }}/Share_Line_Appearance">{{  ($line['shared_line'] ?? false) ? 'shared' : 'private' }}</Share_Ext_{{ $n }}_>
<Shared_User_ID_{{ $n }}_ group="Ext_{{ $n }}/Share_Line_Appearance" />
<Subscription_Expires_{{ $n }}_ group="Ext_{{ $n }}/Share_Line_Appearance">3600</Subscription_Expires_{{ $n }}_>
<NAT_Mapping_Enable_{{ $n }}_ group="Ext_{{ $n }}/NAT_Settings">No</NAT_Mapping_Enable_{{ $n }}_>
<NAT_Keep_Alive_Enable_{{ $n }}_ group="Ext_{{ $n }}/NAT_Settings">No</NAT_Keep_Alive_Enable_{{ $n }}_>
<NAT_Keep_Alive_Msg_{{ $n }}_ group="Ext_{{ $n }}/NAT_Settings">$NOTIFY</NAT_Keep_Alive_Msg_{{ $n }}_>
<NAT_Keep_Alive_Dest_{{ $n }}_ group="Ext_{{ $n }}/NAT_Settings">$PROXY</NAT_Keep_Alive_Dest_{{ $n }}_>
<SIP_TOS_DiffServ_Value_{{ $n }}_ group="Ext_{{ $n }}/Network_Settings">0x68</SIP_TOS_DiffServ_Value_{{ $n }}_>
<SIP_CoS_Value_{{ $n }}_ group="Ext_{{ $n }}/Network_Settings">3</SIP_CoS_Value_{{ $n }}_>
<RTP_TOS_DiffServ_Value_{{ $n }}_ group="Ext_{{ $n }}/Network_Settings">0xb8</RTP_TOS_DiffServ_Value_{{ $n }}_>
<RTP_CoS_Value_{{ $n }}_ group="Ext_{{ $n }}/Network_Settings">6</RTP_CoS_Value_{{ $n }}_>
<Network_Jitter_Level_{{ $n }}_ group="Ext_{{ $n }}/Network_Settings">high</Network_Jitter_Level_{{ $n }}_>
<Jitter_Buffer_Adjustment_{{ $n }}_ group="Ext_{{ $n }}/Network_Settings">up and down</Jitter_Buffer_Adjustment_{{ $n }}_>
<SIP_Transport_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings">{{ strtoupper($line['sip_transport'] ?? '') }}</SIP_Transport_{{ $n }}_>
<SIP_Port_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings">{{ $line['sip_port'] ?? '5060' }}</SIP_Port_{{ $n }}_>
<SIP_100REL_Enable_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings">No</SIP_100REL_Enable_{{ $n }}_>
<EXT_SIP_Port_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings" />
<Auth_Resync-Reboot_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings">Yes</Auth_Resync-Reboot_{{ $n }}_>
<SIP_Proxy-Require_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings" />
<SIP_Remote-Party-ID_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings">No</SIP_Remote-Party-ID_{{ $n }}_>
<Referor_Bye_Delay_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings">4</Referor_Bye_Delay_{{ $n }}_>
<Refer-To_Target_Contact_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings">No</Refer-To_Target_Contact_{{ $n }}_>
<Referee_Bye_Delay_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings">0</Referee_Bye_Delay_{{ $n }}_>
<SIP_Debug_Option_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings">none</SIP_Debug_Option_{{ $n }}_>
<Refer_Target_Bye_Delay_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings">0</Refer_Target_Bye_Delay_{{ $n }}_>
<Sticky_183_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings">No</Sticky_183_{{ $n }}_>
<Auth_INVITE_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings">No</Auth_INVITE_{{ $n }}_>
<Ntfy_Refer_On_1xx-To-Inv_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings">Yes</Ntfy_Refer_On_1xx-To-Inv_{{ $n }}_>
<Use_Anonymous_With_RPID_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings">Yes</Use_Anonymous_With_RPID_{{ $n }}_>
<Set_G729_annexb_{{ $n }}_ group="Ext_{{ $n }}/SIP_Settings">none</Set_G729_annexb_{{ $n }}_>
<Blind_Attn-Xfer_Enable_{{ $n }}_ group="Ext_{{ $n }}/Call_Feature_Settings">No</Blind_Attn-Xfer_Enable_{{ $n }}_>
<MOH_Server_{{ $n }}_ group="Ext_{{ $n }}/Call_Feature_Settings" />
<Auth_Page_{{ $n }}_ group="Ext_{{ $n }}/Call_Feature_Settings">No</Auth_Page_{{ $n }}_>
<Default_Ring_{{ $n }}_ group="Ext_{{ $n }}/Call_Feature_Settings">1</Default_Ring_{{ $n }}_>
<Feature_Key_Sync_{{ $n }}_ group="Ext_{{ $n }}/Call_Feature_Settings">{{ $settings['spa_feature_key_sync'] ?? '' }}</Feature_Key_Sync_{{ $n }}_> 
<Auth_Page_Realm_{{ $n }}_ group="Ext_{{ $n }}/Call_Feature_Settings" />
<Conference_Bridge_URL_{{ $n }}_ group="Ext_{{ $n }}/Call_Feature_Settings" />
<Auth_Page_Password_{{ $n }}_ group="Ext_{{ $n }}/Call_Feature_Settings" />
<Mailbox_ID_{{ $n }}_ group="Ext_{{ $n }}/Call_Feature_Settings" />
<Voice_Mail_Server_{{ $n }}_ group="Ext_{{ $n }}/Call_Feature_Settings" />
<State_Agent_{{ $n }}_ group="Ext_{{ $n }}/Call_Feature_Settings" />
<CFWD_Notify_Serv_{{ $n }}_ group="Ext_{{ $n }}/Call_Feature_Settings">No</CFWD_Notify_Serv_{{ $n }}_>
<CFWD_Notifier_{{ $n }}_ group="Ext_{{ $n }}/Call_Feature_Settings" />
<Proxy_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">{{ $line['server_address'] ?? '' }}</Proxy_{{ $n }}_>
<Use_Outbound_Proxy_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">{{ !empty($line['outbound_proxy_primary']) ? 'Yes' : 'No' }}</Use_Outbound_Proxy_{{ $n }}_>
<Outbound_Proxy_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">{{ $line['outbound_proxy_primary'] ?? '' }}</Outbound_Proxy_{{ $n }}_>
<Alternate_Proxy_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">{{ $line['server_address'] ?? '' }}</Alternate_Proxy_{{ $n }}_>
<Alternate_Outbound_Proxy_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">{{ $line['outbound_proxy_secondary'] ?? '' }}</Alternate_Outbound_Proxy_{{ $n }}_>
<Use_OB_Proxy_In_Dialog_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">Yes</Use_OB_Proxy_In_Dialog_{{ $n }}_>
<Register_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">Yes</Register_{{ $n }}_>
<Make_Call_Without_Reg_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">No</Make_Call_Without_Reg_{{ $n }}_>
<Register_Expires_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">{{ $line['register_expires'] ?? '3600' }}</Register_Expires_{{ $n }}_>
<Ans_Call_Without_Reg_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">No</Ans_Call_Without_Reg_{{ $n }}_>
@if(($line['sip_transport'] ?? '') == 'dns srv')
<Use_DNS_SRV_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">Yes</Use_DNS_SRV_{{ $n }}_>
@else
<Use_DNS_SRV_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">No</Use_DNS_SRV_{{ $n }}_>
@endif
<DNS_SRV_Auto_Prefix_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">No</DNS_SRV_Auto_Prefix_{{ $n }}_>
<Proxy_Fallback_Intvl_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">600</Proxy_Fallback_Intvl_{{ $n }}_>
<Proxy_Redundancy_Method_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">Normal</Proxy_Redundancy_Method_{{ $n }}_>
<Dual_Registration_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">{{ isset($settings['spa_dual_registration']) ? 'Yes' : 'No' }}</Dual_Registration_{{ $n }}_>
<Auto_Register_When_Failover_{{ $n }}_ group="Ext_{{ $n }}/Proxy_and_Registration">{{ isset($settings['spa_register_when_failover']) ? 'Yes' : 'No' }}</Auto_Register_When_Failover_{{ $n }}_>
<Display_Name_{{ $n }}_ group="Ext_{{ $n }}/Subscriber_Information">{{ $line['display_name'] ?? '' }}</Display_Name_{{ $n }}_>
<User_ID_{{ $n }}_ group="Ext_{{ $n }}/Subscriber_Information">{{ $line['user_id'] ?? $line['auth_id'] ?? '' }}</User_ID_{{ $n }}_>
<Password_{{ $n }}_ group="Ext_{{ $n }}/Subscriber_Information">{{ $line['password'] ?? '' }}</Password_{{ $n }}_>
<Use_Auth_ID_{{ $n }}_ group="Ext_{{ $n }}/Subscriber_Information">No</Use_Auth_ID_{{ $n }}_>
<Auth_ID_{{ $n }}_ group="Ext_{{ $n }}/Subscriber_Information" />
<Mini_Certificate_{{ $n }}_ group="Ext_{{ $n }}/Subscriber_Information" />
<SRTP_Private_Key_{{ $n }}_ group="Ext_{{ $n }}/Subscriber_Information" />
<Preferred_Codec_{{ $n }}_ group="Ext_{{ $n }}/Audio_Configuration">G711u</Preferred_Codec_{{ $n }}_>
<Use_Pref_Codec_Only_{{ $n }}_ group="Ext_{{ $n }}/Audio_Configuration">No</Use_Pref_Codec_Only_{{ $n }}_>
<Second_Preferred_Codec_{{ $n }}_ group="Ext_{{ $n }}/Audio_Configuration">Unspecified</Second_Preferred_Codec_{{ $n }}_>
<Third_Preferred_Codec_{{ $n }}_ group="Ext_{{ $n }}/Audio_Configuration">Unspecified</Third_Preferred_Codec_{{ $n }}_>
<G729a_Enable_{{ $n }}_ group="Ext_{{ $n }}/Audio_Configuration">Yes</G729a_Enable_{{ $n }}_>
<G722_Enable_{{ $n }}_ group="Ext_{{ $n }}/Audio_Configuration">Yes</G722_Enable_{{ $n }}_>
<G726-16_Enable_{{ $n }}_ group="Ext_{{ $n }}/Audio_Configuration">Yes</G726-16_Enable_{{ $n }}_>
<G726-24_Enable_{{ $n }}_ group="Ext_{{ $n }}/Audio_Configuration">Yes</G726-24_Enable_{{ $n }}_>
<G726-32_Enable_{{ $n }}_ group="Ext_{{ $n }}/Audio_Configuration">Yes</G726-32_Enable_{{ $n }}_>
<G726-40_Enable_{{ $n }}_ group="Ext_{{ $n }}/Audio_Configuration">Yes</G726-40_Enable_{{ $n }}_>
<Release_Unused_Codec_{{ $n }}_ group="Ext_{{ $n }}/Audio_Configuration">Yes</Release_Unused_Codec_{{ $n }}_>
<DTMF_Process_AVT_{{ $n }}_ group="Ext_{{ $n }}/Audio_Configuration">Yes</DTMF_Process_AVT_{{ $n }}_>
<Silence_Supp_Enable_{{ $n }}_ group="Ext_{{ $n }}/Audio_Configuration">No</Silence_Supp_Enable_{{ $n }}_>
<DTMF_Tx_Method_{{ $n }}_ group="Ext_{{ $n }}/Audio_Configuration">Auto</DTMF_Tx_Method_{{ $n }}_>
<Dial_Plan_{{ $n }}_ group="Ext_{{ $n }}/Dial_Plan">{{ $settings['spa_dial_plan'] ?? '' }}</Dial_Plan_{{ $n }}_>
<Caller_ID_Map_{{ $n }}_ group="Ext_{{ $n }}/Dial_Plan" />
<Enable_IP_Dialing_{{ $n }}_ group="Ext_{{ $n }}/Dial_Plan">Yes</Enable_IP_Dialing_{{ $n }}_>
<Emergency_Number_{{ $n }}_ group="Ext_{{ $n }}/Dial_Plan" />
@endforeach

<SCA_Line_ID_Mapping group="Phone/Miscellaneous_Line_Key_Settings">Vertical First</SCA_Line_ID_Mapping>
<SCA_Barge-In_Enable group="Phone/Miscellaneous_Line_Key_Settings">Yes</SCA_Barge-In_Enable>
<Conference_Serv group="Phone/Supplementary_Services">Yes</Conference_Serv>
<Attn_Transfer_Serv group="Phone/Supplementary_Services">Yes</Attn_Transfer_Serv>
<Blind_Transfer_Serv group="Phone/Supplementary_Services">Yes</Blind_Transfer_Serv>
<DND_Serv group="Phone/Supplementary_Services">Yes</DND_Serv>
<Block_ANC_Serv group="Phone/Supplementary_Services">Yes</Block_ANC_Serv>
<Call_Back_Serv group="Phone/Supplementary_Services">Yes</Call_Back_Serv>
<Block_CID_Serv group="Phone/Supplementary_Services">Yes</Block_CID_Serv>
<Secure_Call_Serv group="Phone/Supplementary_Services">Yes</Secure_Call_Serv>
<Cfwd_All_Serv group="Phone/Supplementary_Services">Yes</Cfwd_All_Serv>
<Cfwd_Busy_Serv group="Phone/Supplementary_Services">Yes</Cfwd_Busy_Serv>
<Cfwd_No_Ans_Serv group="Phone/Supplementary_Services">Yes</Cfwd_No_Ans_Serv>
<Paging_Serv group="Phone/Supplementary_Services">Yes</Paging_Serv>
<Call_Park_Serv group="Phone/Supplementary_Services">Yes</Call_Park_Serv>
<Call_Pick_Up_Serv group="Phone/Supplementary_Services">Yes</Call_Pick_Up_Serv>
<ACD_Login_Serv group="Phone/Supplementary_Services">No</ACD_Login_Serv>
<Group_Call_Pick_Up_Serv group="Phone/Supplementary_Services">Yes</Group_Call_Pick_Up_Serv>
<ACD_Ext group="Phone/Supplementary_Services">1</ACD_Ext>
<Service_Annc_Serv group="Phone/Supplementary_Services">No</Service_Annc_Serv>
<Ring1 group="Phone/Ring_Tone">n=Classic-1;w=3;c=1</Ring1>
<Ring2 group="Phone/Ring_Tone">n=Classic-2;w=3;c=2</Ring2>
<Ring3 group="Phone/Ring_Tone">n=Classic-3;w=3;c=3</Ring3>
<Ring4 group="Phone/Ring_Tone">n=Classic-4;w=3;c=4</Ring4>
<Ring5 group="Phone/Ring_Tone">n=Simple-1;w=2;c=1</Ring5>
<Ring6 group="Phone/Ring_Tone">n=Simple-2;w=2;c=2</Ring6>
<Ring7 group="Phone/Ring_Tone">n=Simple-3;w=2;c=3</Ring7>
<Ring8 group="Phone/Ring_Tone">n=Simple-4;w=2;c=4</Ring8>
<Ring9 group="Phone/Ring_Tone">n=Simple-5;w=2;c=5</Ring9>
<Ring10 group="Phone/Ring_Tone">n=Office;w=4;c=1</Ring10>
<LDAP_Dir_Enable group="Phone/LDAP_Corporate_Directory_Search">No</LDAP_Dir_Enable>
<LDAP_Corp_Dir_Name group="Phone/LDAP_Corporate_Directory_Search" />
<LDAP_Server group="Phone/LDAP_Corporate_Directory_Search" />
<LDAP_Auth_Method group="Phone/LDAP_Corporate_Directory_Search">None</LDAP_Auth_Method>
<LDAP_Client_DN group="Phone/LDAP_Corporate_Directory_Search" />
<LDAP_Username group="Phone/LDAP_Corporate_Directory_Search" />
<LDAP_Password group="Phone/LDAP_Corporate_Directory_Search" />
<LDAP_Search_Base group="Phone/LDAP_Corporate_Directory_Search" />
<LDAP_Last_Name_Filter group="Phone/LDAP_Corporate_Directory_Search" />
<LDAP_First_Name_Filter group="Phone/LDAP_Corporate_Directory_Search" />
<LDAP_Search_Item_3 group="Phone/LDAP_Corporate_Directory_Search" />
<LDAP_Item_3_Filter group="Phone/LDAP_Corporate_Directory_Search" />
<LDAP_Search_Item_4 group="Phone/LDAP_Corporate_Directory_Search" />
<LDAP_item_4_Filter group="Phone/LDAP_Corporate_Directory_Search" />
<LDAP_Display_Attrs group="Phone/LDAP_Corporate_Directory_Search" />
<LDAP_Number_Mapping group="Phone/LDAP_Corporate_Directory_Search" />
<EM_Enable group="Phone/Extension_Mobility">No</EM_Enable>
<EM_User_Domain group="Phone/Extension_Mobility" />

<Cfwd_All_Dest group="User/Call_Forward" />
<Cfwd_Busy_Dest group="User/Call_Forward" />
<Cfwd_No_Ans_Dest group="User/Call_Forward" />
<Cfwd_No_Ans_Delay group="User/Call_Forward">20</Cfwd_No_Ans_Delay>
<Speed_Dial_2 group="User/Speed_Dial" />
<Speed_Dial_3 group="User/Speed_Dial" />
<Speed_Dial_4 group="User/Speed_Dial" />
<Speed_Dial_5 group="User/Speed_Dial" />
<Speed_Dial_6 group="User/Speed_Dial" />
<Speed_Dial_7 group="User/Speed_Dial" />
<Speed_Dial_8 group="User/Speed_Dial" />
<Speed_Dial_9 group="User/Speed_Dial" />
<CW_Setting group="User/Supplementary_Services">{{ $settings['spa_call_waiting'] ?? 'Yes' }}</CW_Setting>
<Block_CID_Setting group="User/Supplementary_Services">No</Block_CID_Setting>
<Block_ANC_Setting group="User/Supplementary_Services">No</Block_ANC_Setting>
<DND_Setting group="User/Supplementary_Services">No</DND_Setting>
<Secure_Call_Setting group="User/Supplementary_Services">{{ isset($settings['spa_secure_call_setting']) ? 'Yes' : 'No' }}</Secure_Call_Setting>
<Dial_Assistance group="User/Supplementary_Services">No</Dial_Assistance>
<Auto_Answer_Page group="User/Supplementary_Services">Yes</Auto_Answer_Page>
<Preferred_Audio_Device group="User/Supplementary_Services">Speaker</Preferred_Audio_Device>
<Send_Audio_To_Speaker group="User/Supplementary_Services">No</Send_Audio_To_Speaker>
<Time_Format group="User/Supplementary_Services">{{ $settings['spa_time_format'] ?? '' }}</Time_Format>
<Date_Format group="User/Supplementary_Services">{{ $settings['spa_date_format'] ?? '' }}</Date_Format>
<Miss_Call_Shortcut group="User/Supplementary_Services">Yes</Miss_Call_Shortcut>
<Accept_Media_Loopback_Request group="User/Supplementary_Services">automatic</Accept_Media_Loopback_Request>
<Media_Loopback_Mode group="User/Supplementary_Services">source</Media_Loopback_Mode>
<Media_Loopback_Type group="User/Supplementary_Services">media</Media_Loopback_Type>
<Text_Message group="User/Supplementary_Services">Yes</Text_Message>
<Text_Message_From_3rd_Party group="User/Supplementary_Services">No</Text_Message_From_3rd_Party>
<Alert_Tone_Off group="User/Supplementary_Services">No</Alert_Tone_Off>
<Log_Missed_Calls_for_EXT_1 group="User/Supplementary_Services">Yes</Log_Missed_Calls_for_EXT_1>
<Log_Missed_Calls_for_EXT_2 group="User/Supplementary_Services">Yes</Log_Missed_Calls_for_EXT_2>
<Log_Missed_Calls_for_EXT_3 group="User/Supplementary_Services">Yes</Log_Missed_Calls_for_EXT_3>
<Log_Missed_Calls_for_EXT_4 group="User/Supplementary_Services">Yes</Log_Missed_Calls_for_EXT_4>
<Ringer_Volume group="User/Audio_Volume">9</Ringer_Volume>
<Speaker_Volume group="User/Audio_Volume">8</Speaker_Volume>
<Handset_Volume group="User/Audio_Volume">10</Handset_Volume>
<Headset_Volume group="User/Audio_Volume">10</Headset_Volume>
<LCD_Contrast group="User/Audio_Volume">8</LCD_Contrast>
<Back_Light_Timer group="User/Audio_Volume">{{ $settings['spa_back_light_timer'] ?? '' }}</Back_Light_Timer>
<Subscribe_Expires group="Attendant_Console/General">1800</Subscribe_Expires>
<Subscribe_Retry_Interval group="Attendant_Console/General">30</Subscribe_Retry_Interval>
<Unit_1_Enable group="Attendant_Console/General">Yes</Unit_1_Enable>
<Subscribe_Delay group="Attendant_Console/General">1</Subscribe_Delay>
<Unit_2_Enable group="Attendant_Console/General">Yes</Unit_2_Enable>

<Group_Paging_Script group="Phone/Multiple_Paging_Group_Parameters"></Group_Paging_Script>

<Server_Type group="Attendant_Console/General">Asterisk</Server_Type>
<Test_Mode_Enable group="Attendant_Console/General">No</Test_Mode_Enable>
<Attendant_Console_Call_Pickup_Code group="Attendant_Console/General">*8</Attendant_Console_Call_Pickup_Code>

@php
    $expansionKeys = collect($keys ?? [])
        ->where('area', 'expansion')
        ->keyBy('id');
@endphp

@for ($i = 1; $i <= 64; $i++)
@php
    $row = $expansionKeys->get($i, []);

    $unitNumber = $i <= 32 ? 1 : 2;
    $unitKeyId = $i <= 32 ? $i : $i - 32;

    $keyType = strtolower(trim((string)($row['type'] ?? '')));
    $keyValue = trim((string)($row['value'] ?? ''));
    $keyLabel = trim((string)($row['label'] ?? ''));

    $hasValue = $keyValue !== '';
    $hasLabel = $keyLabel !== '';

    $unitKeyValue = ' ';

    if ($keyType === 'blf' && $hasValue) {
        $unitKeyValue = 'fnc=blf+sd+cp;sub=' . $keyValue . '@$PROXY';

        if ($hasLabel) {
            $unitKeyValue .= ';nme=' . $keyLabel;
        }
    }

    if ($keyType === 'speed_dial' && $hasValue) {
        $unitKeyValue = 'fnc=sd;ext=' . $keyValue . '@$PROXY';

        if ($hasLabel) {
            $unitKeyValue .= ';nme=' . $keyLabel;
        }
    }

    if ($keyType === 'park' && $hasValue) {
        $unitKeyValue = 'fnc=blf+sd+cp;sub=' . $keyValue . '@$PROXY';

        if ($hasLabel) {
            $unitKeyValue .= ';nme=' . $keyLabel;
        }
    }

    if ($keyType === 'check_voicemail') {
        $voicemailValue = $hasValue ? $keyValue : '';

        $unitKeyValue = 'fnc=sd;ext=' . $voicemailValue . '@$PROXY';

        if ($hasLabel) {
            $unitKeyValue .= ';nme=' . $keyLabel;
        }
    }
@endphp

<Unit_{{ $unitNumber }}_Key_{{ $unitKeyId }} group="Attendant_Console/Unit_{{ $unitNumber }}">{!! $unitKeyValue !!}</Unit_{{ $unitNumber }}_Key_{{ $unitKeyId }}>

@endfor

</flat-profile>

@endswitch
