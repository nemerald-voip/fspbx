{{-- version: 1.0.2 --}}

@switch($flavor)

{{-- ================= Htek cfg{mac}.xml ================= --}}
@case('mac.xml')
{!! '<' . '?xml version="1.0" encoding="UTF-8" ?' . '>' !!}
<hl_provision version="1">
    <config version="1">
        <!--Account1/Basic-->
        <P271 para="Account1.Active">1</P271>
        <P47 para="Account1.Sipserver">{{ $lines['1']['server_address'] ?? $domain_name }}:{{ $lines['1']['sip_port'] ?? '' }}</P47>
        <P967 para="Account1.FailoverSipserver"></P967>
        <P8851 para="Account1.SecondFailoverSipserver" />
        <P4567 para="Account1.PreferPrimaryServer">1</P4567>
        <P48 para="Account1.OutboundProxy">@if(!empty($lines['1']['outbound_proxy_primary'] ?? '')){{ $lines['1']['outbound_proxy_primary'] }}:{{ $lines['1']['sip_port'] ?? '' }}@endif</P48>
        <P20047 para="Account1.BackUpOutboundProxy">@if(!empty($lines['1']['outbound_proxy_secondary'] ?? '')){{ $lines['1']['outbound_proxy_secondary'] }}:{{ $lines['1']['sip_port'] ?? '' }}@endif</P20047>
        
        <!-- Configure the transport type; 0-UDP (default), 1-TCP, 2-TLS, 3-DNS SRV; -->
        @if (($lines['1']['sip_transport'] ?? '') === 'udp')
        <P130 para="Account1.SipTransport">0</P130>
        @elseif (($lines['1']['sip_transport'] ?? '') === 'tcp')
        <P130 para="Account1.SipTransport">1</P130>
        @elseif (($lines['1']['sip_transport'] ?? '') === 'tls')
        <P130 para="Account1.SipTransport">2</P130>
        @elseif (($lines['1']['sip_transport'] ?? '') === 'dns srv')
        <P130 para="Account1.SipTransport">3</P130>
        @endif

        <P40004 para="Preference.ExpScnSaverTime">0</P40004>
        <P52 para="Account1.NatTraversal">2</P52>
        <P20000 para="Account1.Label">{{ $lines['1']['display_name'] ?? $lines['1']['auth_id'] }}</P20000>
        <P35 para="Account1.SipUserId">{{ $lines['1']['auth_id'] }}</P35>
        <P36 para="Account1.AuthenticateID">{{ $lines['1']['auth_id'] }}</P36>
        <P34 para="Account1.AuthenticatePassword">{{ $lines['1']['password'] ?? '' }}</P34>
        <P3 para="Account1.DispalyName">{{ $lines['1']['display_name'] ?? $lines['1']['auth_id'] }}</P3>
        <P103 para="Account1.DnsMode">{{ $settings['htek_dns_mode'] ?? ''}}</P103>
        <P63 para="Account1.UserIdIsPhoneNumber">0</P63>
        <P31 para="Account1.SipRegistration">1</P31>
        <P81 para="Account1.UnregisterOnReboot">0</P81>
        <P32 para="Account1.RegisterExpiration">2</P32>
        <P109 para="Account1.OutCallWithoutReg">1</P109>
        <P40 para="Account1.LocalSipPort" />
        <P78 para="Account1.UseRandomPort">1</P78>
        <P33 para="Account1.VoiceMailId">{{ $settings['voicemail_number'] ?? '' }}</P33>
        <P136 para="Account1.RPort">1</P136>
        <P1100 para="Account1.RFC2543Hold">1</P1100>
        <P8775 para="Account1.ConnectMode">0</P8775>
        <!--Account1/Codec-->
        <P57 para="Account1.Choice1">0</P57>
        <P58 para="Account1.Choice2">8</P58>
        <P59 para="Account1.Choice3">9</P59>
        <P60 para="Account1.Choice4">2</P60>
        <P61 para="Account1.Choice5">18</P61>
        <P62 para="Account1.Choice6">4</P62>
        <P37 para="Account1.VoiceFramesPerTX">2</P37>
        <P49 para="Account1.G723Rate">0</P49>
        <P394 para="Account1.LibcMode">0</P394>
        <P390 para="Account1.LibcPayloadType">97</P390>
        <!--Account1/Advance-->
        <P79 para="Account1.DtmfPayloadType">101</P79>
        <P20166 para="Account1.DtmfMode">0</P20166>
        <P74 para="Account1.SendFlashEvent">0</P74>
        <P191 para="Account1.EnableCallFeatures">0</P191>
        <P197 para="Account1.ProxyRequire" />
        <P101 para="Account1.UseNatIP" />
        <P183 para="Account1.SRtpMode">{{ $settings['htek_account1_srtpmode'] ?? '' }}</P183>
        <P50 para="Account1.VAD">0</P50>
        <P291 para="Account1.SymmetricRTP">0</P291>
        <P133 para="Account1.JitterBufferType">1</P133>
        <P132 para="Account1.JitterBufferLength">1</P132>
        @if(isset($settings['htek_account1_ring_tone']))
        <P104 para="Account1.AccountRingTone">{{ $settings['htek_account1_ring_tone'] }}</P104>
        @else
        <P104 para="Account1.AccountRingTone">1</P104>
        @endif
        <P185 para="Account1.RingTimeout">60</P185>
        <P72 para="Account1.Use#AsDialKey">1</P72>
        <P4200 para="Account1.DialPlan">{[x*]+}</P4200>
        <P99 para="Account1.SubscribeForMWI">1</P99>
        <P65 para="Account1.SendAnonymous">0</P65>
        <P129 para="Account1.AnonymousCallRejection">0</P129>
        <P258 para="Account1.CheckSIPUserID">1</P258>
        <P90 para="Account1.AutoAnswer">0</P90>
        <P298 para="Account1.AnswerViaCallInfo">1</P298>
        <P299 para="Account1.OffSpeakerDisconnect">1</P299>
        <P260 para="Account1.SessionExpiration">180</P260>
        <P261 para="Account1.MinSE">90</P261>
        <P262 para="Account1.CallerRequestTimer">0</P262>
        <P263 para="Account1.CalleeRequestTimer">0</P263>
        <P264 para="Account1.ForceTimer">0</P264>
        <P266 para="Account1.UACSpecifyRefresher">0</P266>
        <P267 para="Account1.UASSpecifyRefresher">1</P267>
        <P265 para="Account1.ForceINVITE">0</P265>
        <P251 para="Account1.HookFlashMinTiming">50</P251>
        <P252 para="Account1.HookFlashMaxTiming">100</P252>
        <P198 para="Account1.SpecialFeature">100</P198>
        <P134 para="Account1.EventlistBlfUrl" />
        <P8771 para="Account1.ShareLine">0</P8771>
        <P8791 para="Account1.SIPServerType">0</P8791>
        <P8811 para="Account1.100rel">0</P8811>
        <P8841 para="Account1.EarlySession">0</P8841>
        <P8845 para="Account1.RefuseReturnCode">0</P8845>
        <P4705 para="Account1.DirectCallPickupCode">**</P4705>
        <P4706 para="Account1.GroupCallPickupCode" />
        <P8633 para="Account1.FeatureKeySyn">0</P8633>
        <P20004 para="Account1.ConferenceType">0</P20004>
        <P20008 para="Account1.ConferenceURI" />
        <P20053 para="Account1.SipSendMac">0</P20053>
        <P20157 para="Account1.CallerDisplaySource">3</P20157>
        <P20655 para="Account1.EnableACD">0</P20655>
        <P20661 para="Account1.ACDShowAvailable">0</P20661>
        <P20667 para="Account1.ACDInitialState">0</P20667>
        <P20673 para="Account1.ACDShowUnavailableReason">0</P20673>
        <P20900 para="Account1.ACDUnAvailableName1" />
        <P20901 para="Account1.ACDUnAvailableName2" />
        <P20902 para="Account1.ACDUnAvailableName3" />
        <P20903 para="Account1.ACDUnAvailableName4" />
        <P20904 para="Account1.ACDUnAvailableName5" />
        <P20679 para="Account1.ACDUnAvailableCode1" />
        <P20680 para="Account1.ACDUnAvailableCode2" />
        <P20681 para="Account1.ACDUnAvailableCode3" />
        <P20682 para="Account1.ACDUnAvailableCode4" />
        <P20683 para="Account1.ACDUnAvailableCode5" />
        <P20709 para="Account1.EnableHoteling">0</P20709>
        <P20715 para="Account1.HotelingAutoLogin">0</P20715>
        <P20721 para="Account1.HotelingUserId" />
        <P20727 para="Account1.HotelingPassWord" />
        <P20733 para="Account1.EnableCallCenterStatus">0</P20733>
        <P20739 para="Account1.EnableCallCenterTrace">0</P20739>
        <P20745 para="Account1.EnableCallCenterEmergencyEscalation">0</P20745>
        <P20751 para="Account1.CallCenterSupervisorCode1" />
        <P20752 para="Account1.CallCenterSupervisorCode2" />
        <P20753 para="Account1.CallCenterSupervisorCode3" />
        <P20754 para="Account1.CallCenterSupervisorCode4" />
        <P20755 para="Account1.CallCenterSupervisorCode5" />
        <P20787 para="Account1.CallCenterSupervisorName1" />
        <P20788 para="Account1.CallCenterSupervisorName2" />
        <P20789 para="Account1.CallCenterSupervisorName3" />
        <P20790 para="Account1.CallCenterSupervisorName4" />
        <P20791 para="Account1.CallCenterSupervisorName5" />
        <P20823 para="Account1.EnableCallCenterDispCode">0</P20823>
        <P20829 para="Account1.CCDispCode1" />
        <P20830 para="Account1.CCDispCode2" />
        <P20831 para="Account1.CCDispCode3" />
        <P20832 para="Account1.CCDispCode4" />
        <P20833 para="Account1.CCDispCode5" />
        <P20865 para="Account1.CCDispName1" />
        <P20866 para="Account1.CCDispName2" />
        <P20867 para="Account1.CCDispName3" />
        <P20868 para="Account1.CCDispName4" />
        <P20869 para="Account1.CCDispName5" />
        <P20970 para="Account1.SubscribeExpires">300</P20970>
        <P20976 para="Account1.SCABargeIn">0</P20976>
        
        <!--Account2/Basic-->
        <P401 para="Account2.Active">1</P401>
        <P747 para="Account2.Sipserver">{{ $lines['2']['server_address'] ?? '' }}</P747>
        <P987 para="Account2.FailoverSipserver" />
        <P8852 para="Account2.SecondFailoverSipserver" />
        <P4568 para="Account2.PreferPrimaryServer">0</P4568>
    <P748 para="Account2.OutboundProxy">@if(!empty($lines['2']['outbound_proxy_primary'] ?? '')){{ $lines['2']['outbound_proxy_primary'] }}:{{ $lines['2']['sip_port'] ?? '' }}@endif</P748>
    <P20048 para="Account2.BackUpOutboundProxy">@if(!empty($lines['2']['outbound_proxy_secondary'] ?? '')){{ $lines['2']['outbound_proxy_secondary'] }}:{{ $lines['2']['sip_port'] ?? '' }}@endif</P20048>
        
        <!-- Configure the transport type; 0-UDP (default), 1-TCP, 2-TLS, 3-DNS SRV; -->
        @if (($lines['2']['sip_transport'] ?? '') == 'udp')<P830 para="Account2.SipTransport">0</P830>@endif
        @if (($lines['2']['sip_transport'] ?? '') == 'tcp')<P830 para="Account2.SipTransport">1</P830>@endif
        @if (($lines['2']['sip_transport'] ?? '') == 'tls')<P830 para="Account2.SipTransport">2</P830>@endif
        @if (($lines['2']['sip_transport'] ?? '') == 'dns srv')<P830 para="Account2.SipTransport">3</P830>@endif

        <P730 para="Account2.NatTraversal">2</P730>
        <P20001 para="Account2.Label">{{ $lines['2']['display_name'] ?? '' }}</P20001>
        <P735 para="Account2.SipUserId">{{ $lines['2']['user_id'] ?? '' }}</P735>
        <P736 para="Account2.AuthenticateID">{{ $lines['2']['auth_id'] ?? '' }}</P736>
        <P734 para="Account2.AuthenticatePassword">{{ $lines['2']['password'] ?? '' }}</P734>
        <P703 para="Account2.DispalyName">{{ $lines['2']['display_name'] ?? '' }}</P703>
        <P702 para="Account2.DnsMode">0</P702>
        <P763 para="Account2.UserIdIsPhoneNumber">0</P763>
        <P731 para="Account2.SipRegistration">1</P731>
        <P752 para="Account2.UnregisterOnReboot">0</P752>
        <P732 para="Account2.RegisterExpiration">2</P732>
        <P813 para="Account2.OutCallWithoutReg">1</P813>
        <P740 para="Account2.LocalSipPort" />
        <P778 para="Account2.UseRandomPort">1</P778>
        <P426 para="Account2.VoiceMailId">{{ $settings['voicemail_number'] ?? '' }}</P426>
        <P137 para="Account2.RPort">1</P137>
        <P1101 para="Account2.RFC2543Hold">1</P1101>
        <P8776 para="Account2.ConnectMode">0</P8776>
        <!--Account2/Codec-->
        <P757 para="Account2.Choice1">0</P757>
        <P758 para="Account2.Choice2">8</P758>
        <P759 para="Account2.Choice3">9</P759>
        <P760 para="Account2.Choice4">2</P760>
        <P761 para="Account2.Choice5">18</P761>
        <P762 para="Account2.Choice6">4</P762>
        <P737 para="Account2.VoiceFramesPerTX">2</P737>
        <P749 para="Account2.G723Rate">0</P749>
        <P395 para="Account2.LibcMode">0</P395>
        <P391 para="Account2.LibcPayloadType">97</P391>
        <!--Account2/Advance-->
        <P779 para="Account2.DtmfPayloadType">101</P779>
        <P20167 para="Account2.DtmfMode">0</P20167>
        <P774 para="Account2.SendFlashEvent">0</P774>
        <P751 para="Account2.EnableCallFeatures">0</P751>
        <P792 para="Account2.ProxyRequire" />
        <P866 para="Account2.UseNatIP" />
        <P443 para="Account2.SRtpMode">{{ $settings['htek_account2_srtpmode'] ?? '' }}</P443>
        <P750 para="Account2.VAD">0</P750>
        <P460 para="Account2.SymmetricRTP">0</P460>
        <P831 para="Account2.JitterBufferType">1</P831>
        <P832 para="Account2.JitterBufferLength">1</P832>
        <P423 para="Account2.AccountRingTone">{{ $settings['htek_account2_accountringtone'] ?? '' }}</P423>
        <P816 para="Account2.RingTimeout">60</P816>
        <P772 para="Account2.Use#AsDialKey">1</P772>
        <P4201 para="Account2.DialPlan">{[x*]+}</P4201>
        <P709 para="Account2.SubscribeForMWI">1</P709>
        <P421 para="Account2.SendAnonymous">0</P421>
        <P446 para="Account2.AnonymousCallRejection">0</P446>
        <P458 para="Account2.CheckSIPUserID">1</P458>
        <P425 para="Account2.AutoAnswer">0</P425>
        <P438 para="Account2.AnswerViaCallInfo">1</P438>
        <P439 para="Account2.OffSpeakerDisconnect">1</P439>
        <P434 para="Account2.SessionExpiration">180</P434>
        <P427 para="Account2.MinSE">90</P427>
        <P428 para="Account2.CallerRequestTimer">0</P428>
        <P429 para="Account2.CalleeRequestTimer">0</P429>
        <P430 para="Account2.ForceTimer">0</P430>
        <P432 para="Account2.UACSpecifyRefresher">0</P432>
        <P433 para="Account2.UASSpecifyRefresher">1</P433>
        <P431 para="Account2.ForceINVITE">0</P431>
        <P811 para="Account2.HookFlashMinTiming">50</P811>
        <P812 para="Account2.HookFlashMaxTiming">100</P812>
        <P767 para="Account2.SpecialFeature">100</P767>
        <P444 para="Account2.EventlistBlfUrl" />
        <P8772 para="Account2.ShareLine">0</P8772>
        <P8792 para="Account2.SIPServerType">0</P8792>
        <P8812 para="Account2.100rel">0</P8812>
        <P8842 para="Account2.EarlySession">0</P8842>
        <P8846 para="Account2.RefuseReturnCode">0</P8846>
        <P4715 para="Account2.DirectCallPickupCode" />
        <P4716 para="Account2.GroupCallPickupCode" />
        <P8634 para="Account2.FeatureKeySyn">0</P8634>
        <P20005 para="Account2.ConferenceType">0</P20005>
        <P20009 para="Account2.ConferenceURI" />
        <P20054 para="Account2.SipSendMac">0</P20054>
        <P20158 para="Account2.CallerDisplaySource">0</P20158>
        <P20656 para="Account2.EnableACD">0</P20656>
        <P20662 para="Account2.ACDShowAvailable">0</P20662>
        <P20668 para="Account2.ACDInitialState">0</P20668>
        <P20674 para="Account2.ACDShowUnavailableReason">0</P20674>
        <P20905 para="Account2.ACDUnAvailableName1" />
        <P20906 para="Account2.ACDUnAvailableName2" />
        <P20907 para="Account2.ACDUnAvailableName3" />
        <P20908 para="Account2.ACDUnAvailableName4" />
        <P20909 para="Account2.ACDUnAvailableName5" />
        <P20684 para="Account2.ACDUnAvailableCode1" />
        <P20685 para="Account2.ACDUnAvailableCode2" />
        <P20686 para="Account2.ACDUnAvailableCode3" />
        <P20687 para="Account2.ACDUnAvailableCode4" />
        <P20688 para="Account2.ACDUnAvailableCode5" />
        <P20710 para="Account2.EnableHoteling">0</P20710>
        <P20716 para="Account2.HotelingAutoLogin">0</P20716>
        <P20722 para="Account2.HotelingUserId" />
        <P20728 para="Account2.HotelingPassWord" />
        <P20734 para="Account2.EnableCallCenterStatus">0</P20734>
        <P20740 para="Account2.EnableCallCenterTrace">0</P20740>
        <P20746 para="Account2.EnableCallCenterEmergencyEscalation">0</P20746>
        <P20757 para="Account2.CallCenterSupervisorCode1" />
        <P20758 para="Account2.CallCenterSupervisorCode2" />
        <P20759 para="Account2.CallCenterSupervisorCode3" />
        <P20760 para="Account2.CallCenterSupervisorCode4" />
        <P20761 para="Account2.CallCenterSupervisorCode5" />
        <P20793 para="Account2.CallCenterSupervisorName1" />
        <P20794 para="Account2.CallCenterSupervisorName2" />
        <P20795 para="Account2.CallCenterSupervisorName3" />
        <P20796 para="Account2.CallCenterSupervisorName4" />
        <P20797 para="Account2.CallCenterSupervisorName5" />
        <P20824 para="Account2.EnableCallCenterDispCode">0</P20824>
        <P20835 para="Account2.CCDispCode1" />
        <P20836 para="Account2.CCDispCode2" />
        <P20837 para="Account2.CCDispCode3" />
        <P20838 para="Account2.CCDispCode4" />
        <P20839 para="Account2.CCDispCode5" />
        <P20871 para="Account2.CCDispName1" />
        <P20872 para="Account2.CCDispName2" />
        <P20873 para="Account2.CCDispName3" />
        <P20874 para="Account2.CCDispName4" />
        <P20875 para="Account2.CCDispName5" />
        <P20971 para="Account2.SubscribeExpires">300</P20971>
        <P20977 para="Account2.SCABargeIn">0</P20977>
        <!--Account3/Basic-->
        <P501 para="Account3.Active">1</P501>
        <P502 para="Account3.Sipserver">{{ $lines['3']['server_address'] ?? '' }}</P502>
        <P988 para="Account3.FailoverSipserver" />
        <P8853 para="Account3.SecondFailoverSipserver" />
        <P4569 para="Account3.PreferPrimaryServer">0</P4569>
        <P503 para="Account3.OutboundProxy">@if(!empty($lines['3']['outbound_proxy_primary'] ?? '')){{ $lines['3']['outbound_proxy_primary'] }}:{{ $lines['3']['sip_port'] ?? '' }}@endif</P503>
        <P20049 para="Account3.BackUpOutboundProxy">@if(!empty($lines['3']['outbound_proxy_secondary'] ?? '')){{ $lines['3']['outbound_proxy_secondary'] }}:{{ $lines['3']['sip_port'] ?? '' }}@endif</P20049>

        <!-- Configure the transport type; 0-UDP (default), 1-TCP, 2-TLS, 3-DNS SRV; -->
        @if (($lines['3']['sip_transport'] ?? '') == 'udp')<P930 para="Account3.SipTransport">0</P930>@endif
        @if (($lines['3']['sip_transport'] ?? '') == 'tcp')<P930 para="Account3.SipTransport">1</P930>@endif
        @if (($lines['3']['sip_transport'] ?? '') == 'tls')<P930 para="Account3.SipTransport">2</P930>@endif
        @if (($lines['3']['sip_transport'] ?? '') == 'dns srv')<P930 para="Account3.SipTransport">3</P930>@endif

        <P514 para="Account3.NatTraversal">2</P514>
        <P20002 para="Account3.Label">{{ $lines['3']['display_name'] ?? '' }}</P20002>
        <P504 para="Account3.SipUserId">{{ $lines['3']['user_id'] ?? '' }}</P504>
        <P505 para="Account3.AuthenticateID">{{ $lines['3']['auth_id'] ?? '' }}</P505>
        <P506 para="Account3.AuthenticatePassword">{{ $lines['3']['password'] ?? '' }}</P506>
        <P507 para="Account3.DispalyName">{{ $lines['3']['display_name'] ?? '' }}</P507>
        <P508 para="Account3.DnsMode">{{ $settings['htek_dns_mode'] ?? '' }}</P508>
        <P509 para="Account3.UserIdIsPhoneNumber">0</P509>
        <P510 para="Account3.SipRegistration">1</P510>
        <P511 para="Account3.UnregisterOnReboot">0</P511>
        <P512 para="Account3.RegisterExpiration">2</P512>
        <P913 para="Account3.OutCallWithoutReg">1</P913>
        <P513 para="Account3.LocalSipPort" />
        <P578 para="Account3.UseRandomPort">1</P578>
        <P526 para="Account3.VoiceMailId">{{ $settings['voicemail_number'] ?? '' }}</P526>
        <P138 para="Account3.RPort">1</P138>
        <P1102 para="Account3.RFC2543Hold">1</P1102>
        <P8777 para="Account3.ConnectMode">0</P8777>
        <!--Account3/Codec-->
        <P551 para="Account3.Choice1">0</P551>
        <P552 para="Account3.Choice2">8</P552>
        <P553 para="Account3.Choice3">9</P553>
        <P554 para="Account3.Choice4">2</P554>
        <P555 para="Account3.Choice5">18</P555>
        <P556 para="Account3.Choice6">4</P556>
        <P537 para="Account3.VoiceFramesPerTX">2</P537>
        <P559 para="Account3.G723Rate">0</P559>
        <P396 para="Account3.LibcMode">0</P396>
        <P392 para="Account3.LibcPayloadType">97</P392>
        <!--Account3/Advance-->
        <P579 para="Account3.DtmfPayloadType">101</P579>
        <P20168 para="Account3.DtmfMode">0</P20168>
        <P775 para="Account3.SendFlashEvent">0</P775>
        <P1891 para="Account3.EnableCallFeatures">0</P1891>
        <P518 para="Account3.ProxyRequire" />
        <P566 para="Account3.UseNatIP" />
        <P543 para="Account3.SRtpMode">{{ $settings['htek_account3_srtpmode'] ?? '' }}</P543>
        <P550 para="Account3.VAD">0</P550>
        <P591 para="Account3.SymmetricRTP">0</P591>
        <P1133 para="Account3.JitterBufferType">1</P1133>
        <P1132 para="Account3.JitterBufferLength">1</P1132>
        <P523 para="Account3.AccountRingTone">{{ $settings['htek_account3_accountringtone'] ?? '' }}</P523>
        <P1885 para="Account3.RingTimeout">60</P1885>
        <P1672 para="Account3.Use#AsDialKey">1</P1672>
        <P4202 para="Account3.DialPlan">{[x*]+}</P4202>
        <P515 para="Account3.SubscribeForMWI">1</P515>
        <P521 para="Account3.SendAnonymous">0</P521>
        <P1846 para="Account3.AnonymousCallRejection">0</P1846>
        <P567 para="Account3.CheckSIPUserID">1</P567>
        <P525 para="Account3.AutoAnswer">0</P525>
        <P538 para="Account3.AnswerViaCallInfo">1</P538>
        <P539 para="Account3.OffSpeakerDisconnect">1</P539>
        <P534 para="Account3.SessionExpiration">180</P534>
        <P527 para="Account3.MinSE">90</P527>
        <P528 para="Account3.CallerRequestTimer">0</P528>
        <P529 para="Account3.CalleeRequestTimer">0</P529>
        <P530 para="Account3.ForceTimer">0</P530>
        <P532 para="Account3.UACSpecifyRefresher">0</P532>
        <P533 para="Account3.UASSpecifyRefresher">1</P533>
        <P531 para="Account3.ForceINVITE">0</P531>
        <P1811 para="Account3.HookFlashMinTiming">50</P1811>
        <P1812 para="Account3.HookFlashMaxTiming">100</P1812>
        <P524 para="Account3.SpecialFeature">100</P524>
        <P544 para="Account3.EventlistBlfUrl" />
        <P8773 para="Account3.ShareLine">0</P8773>
        <P8793 para="Account3.SIPServerType">0</P8793>
        <P8813 para="Account3.100rel">0</P8813>
        <P8843 para="Account3.EarlySession">0</P8843>
        <P8847 para="Account3.RefuseReturnCode">0</P8847>
        <P4725 para="Account3.DirectCallPickupCode" />
        <P4726 para="Account3.GroupCallPickupCode" />
        <P8635 para="Account3.FeatureKeySyn">0</P8635>
        <P20006 para="Account3.ConferenceType">0</P20006>
        <P20010 para="Account3.ConferenceURI" />
        <P20055 para="Account3.SipSendMac">0</P20055>
        <P20159 para="Account3.CallerDisplaySource">0</P20159>
        <P20657 para="Account3.EnableACD">0</P20657>
        <P20663 para="Account3.ACDShowAvailable">0</P20663>
        <P20669 para="Account3.ACDInitialState">0</P20669>
        <P20675 para="Account3.ACDShowUnavailableReason">0</P20675>
        <P20910 para="Account3.ACDUnAvailableName1" />
        <P20911 para="Account3.ACDUnAvailableName2" />
        <P20912 para="Account3.ACDUnAvailableName3" />
        <P20913 para="Account3.ACDUnAvailableName4" />
        <P20914 para="Account3.ACDUnAvailableName5" />
        <P20689 para="Account3.ACDUnAvailableCode1" />
        <P20690 para="Account3.ACDUnAvailableCode2" />
        <P20691 para="Account3.ACDUnAvailableCode3" />
        <P20692 para="Account3.ACDUnAvailableCode4" />
        <P20693 para="Account3.ACDUnAvailableCode5" />
        <P20711 para="Account3.EnableHoteling">0</P20711>
        <P20717 para="Account3.HotelingAutoLogin">0</P20717>
        <P20723 para="Account3.HotelingUserId" />
        <P20729 para="Account3.HotelingPassWord" />
        <P20735 para="Account3.EnableCallCenterStatus">0</P20735>
        <P20741 para="Account3.EnableCallCenterTrace">0</P20741>
        <P20747 para="Account3.EnableCallCenterEmergencyEscalation">0</P20747>
        <P20763 para="Account3.CallCenterSupervisorCode1" />
        <P20764 para="Account3.CallCenterSupervisorCode2" />
        <P20765 para="Account3.CallCenterSupervisorCode3" />
        <P20766 para="Account3.CallCenterSupervisorCode4" />
        <P20767 para="Account3.CallCenterSupervisorCode5" />
        <P20799 para="Account3.CallCenterSupervisorName1" />
        <P20800 para="Account3.CallCenterSupervisorName2" />
        <P20801 para="Account3.CallCenterSupervisorName3" />
        <P20802 para="Account3.CallCenterSupervisorName4" />
        <P20803 para="Account3.CallCenterSupervisorName5" />
        <P20825 para="Account3.EnableCallCenterDispCode">0</P20825>
        <P20841 para="Account3.CCDispCode1" />
        <P20842 para="Account3.CCDispCode2" />
        <P20843 para="Account3.CCDispCode3" />
        <P20844 para="Account3.CCDispCode4" />
        <P20845 para="Account3.CCDispCode5" />
        <P20877 para="Account3.CCDispName1" />
        <P20878 para="Account3.CCDispName2" />
        <P20879 para="Account3.CCDispName3" />
        <P20880 para="Account3.CCDispName4" />
        <P20881 para="Account3.CCDispName5" />
        <P20972 para="Account3.SubscribeExpires">300</P20972>
        <P20978 para="Account3.SCABargeIn">0</P20978>
        <!--Account4/Basic-->
        <P601 para="Account4.Active">1</P601>
        <P602 para="Account4.Sipserver" />
        <P989 para="Account4.FailoverSipserver" />
        <P8854 para="Account4.SecondFailoverSipserver" />
        <P4570 para="Account4.PreferPrimaryServer">0</P4570>
        <P603 para="Account4.OutboundProxy" />
        <P20050 para="Account4.BackUpOutboundProxy" />
        <P1030 para="Account4.SipTransport">0</P1030>
        <P614 para="Account4.NatTraversal">2</P614>
        <P20003 para="Account4.Label" />
        <P604 para="Account4.SipUserId" />
        <P605 para="Account4.AuthenticateID" />
        <P606 para="Account4.AuthenticatePassword" />
        <P607 para="Account4.DispalyName" />
        <P608 para="Account4.DnsMode">0</P608>
        <P609 para="Account4.UserIdIsPhoneNumber">0</P609>
        <P610 para="Account4.SipRegistration">1</P610>
        <P611 para="Account4.UnregisterOnReboot">0</P611>
        <P612 para="Account4.RegisterExpiration">15</P612>
        <P1013 para="Account4.OutCallWithoutReg">1</P1013>
        <P613 para="Account4.LocalSipPort">5360</P613>
        <P678 para="Account4.UseRandomPort">0</P678>
        <P626 para="Account4.VoiceMailId" />
        <P139 para="Account4.RPort">0</P139>
        <P1103 para="Account4.RFC2543Hold">1</P1103>
        <P8778 para="Account4.ConnectMode">0</P8778>
        <!--Account4/Codec-->
        <P651 para="Account4.Choice1">0</P651>
        <P652 para="Account4.Choice2">8</P652>
        <P653 para="Account4.Choice3">9</P653>
        <P654 para="Account4.Choice4">20</P654>
        <P655 para="Account4.Choice5">3</P655>
        <P656 para="Account4.Choice6">2</P656>
        <P637 para="Account4.VoiceFramesPerTX">2</P637>
        <P659 para="Account4.G723Rate">0</P659>
        <P397 para="Account4.LibcMode">0</P397>
        <P393 para="Account4.LibcPayloadType">97</P393>
        <!--Account4/Advance-->
        <P679 para="Account4.DtmfPayloadType">101</P679>
        <P20169 para="Account4.DtmfMode">0</P20169>
        <P776 para="Account4.SendFlashEvent">0</P776>
        <P1991 para="Account4.EnableCallFeatures">0</P1991>
        <P618 para="Account4.ProxyRequire" />
        <P666 para="Account4.UseNatIP" />
        <P643 para="Account4.SRtpMode">{{ $settings['htek_account4_srtpmode'] ?? '' }}</P643>
        <P695 para="Account4.VAD">0</P695>
        <P691 para="Account4.SymmetricRTP">0</P691>
        <P1233 para="Account4.JitterBufferType">1</P1233>
        <P1232 para="Account4.JitterBufferLength">1</P1232>
        <P623 para="Account4.AccountRingTone">{{ $settings['htek_account4_accountringtone'] ?? '' }}</P623>
        <P1886 para="Account4.RingTimeout">60</P1886>
        <P1772 para="Account4.Use#AsDialKey">1</P1772>
        <P4203 para="Account4.DialPlan">{[x*]+}</P4203>
        <P615 para="Account4.SubscribeForMWI">1</P615>
        <P621 para="Account4.SendAnonymous">0</P621>
        <P1946 para="Account4.AnonymousCallRejection">0</P1946>
        <P667 para="Account4.CheckSIPUserID">0</P667>
        <P625 para="Account4.AutoAnswer">0</P625>
        <P638 para="Account4.AnswerViaCallInfo">1</P638>
        <P639 para="Account4.OffSpeakerDisconnect">1</P639>
        <P634 para="Account4.SessionExpiration">180</P634>
        <P627 para="Account4.MinSE">90</P627>
        <P628 para="Account4.CallerRequestTimer">0</P628>
        <P629 para="Account4.CalleeRequestTimer">0</P629>
        <P630 para="Account4.ForceTimer">0</P630>
        <P632 para="Account4.UACSpecifyRefresher">0</P632>
        <P633 para="Account4.UASSpecifyRefresher">1</P633>
        <P631 para="Account4.ForceINVITE">0</P631>
        <P1911 para="Account4.HookFlashMinTiming">50</P1911>
        <P1912 para="Account4.HookFlashMaxTiming">100</P1912>
        <P624 para="Account4.SpecialFeature">100</P624>
        <P644 para="Account4.EventlistBlfUrl" />
        <P8774 para="Account4.ShareLine">0</P8774>
        <P8794 para="Account4.SIPServerType">0</P8794>
        <P8814 para="Account4.100rel">0</P8814>
        <P8844 para="Account4.EarlySession">0</P8844>
        <P8848 para="Account4.RefuseReturnCode">0</P8848>
        <P4735 para="Account4.DirectCallPickupCode" />
        <P4736 para="Account4.GroupCallPickupCode" />
        <P8656 para="Account4.FeatureKeySyn">0</P8656>
        <P20007 para="Account4.ConferenceType">0</P20007>
        <P20011 para="Account4.ConferenceURI" />
        <P20056 para="Account4.SipSendMac">0</P20056>
        <P20160 para="Account4.CallerDisplaySource">0</P20160>
        <P20658 para="Account4.EnableACD">0</P20658>
        <P20664 para="Account4.ACDShowAvailable">0</P20664>
        <P20670 para="Account4.ACDInitialState">0</P20670>
        <P20676 para="Account4.ACDShowUnavailableReason">0</P20676>
        <P20915 para="Account4.ACDUnAvailableName1" />
        <P20916 para="Account4.ACDUnAvailableName2" />
        <P20917 para="Account4.ACDUnAvailableName3" />
        <P20918 para="Account4.ACDUnAvailableName4" />
        <P20919 para="Account4.ACDUnAvailableName5" />
        <P20694 para="Account4.ACDUnAvailableCode1" />
        <P20695 para="Account4.ACDUnAvailableCode2" />
        <P20696 para="Account4.ACDUnAvailableCode3" />
        <P20697 para="Account4.ACDUnAvailableCode4" />
        <P20698 para="Account4.ACDUnAvailableCode5" />
        <P20712 para="Account4.EnableHoteling">0</P20712>
        <P20718 para="Account4.HotelingAutoLogin">0</P20718>
        <P20724 para="Account4.HotelingUserId" />
        <P20730 para="Account4.HotelingPassWord" />
        <P20736 para="Account4.EnableCallCenterStatus">0</P20736>
        <P20742 para="Account4.EnableCallCenterTrace">0</P20742>
        <P20748 para="Account4.EnableCallCenterEmergencyEscalation">0</P20748>
        <P20769 para="Account4.CallCenterSupervisorCode1" />
        <P20770 para="Account4.CallCenterSupervisorCode2" />
        <P20771 para="Account4.CallCenterSupervisorCode3" />
        <P20772 para="Account4.CallCenterSupervisorCode4" />
        <P20773 para="Account4.CallCenterSupervisorCode5" />
        <P20805 para="Account4.CallCenterSupervisorName1" />
        <P20806 para="Account4.CallCenterSupervisorName2" />
        <P20807 para="Account4.CallCenterSupervisorName3" />
        <P20808 para="Account4.CallCenterSupervisorName4" />
        <P20809 para="Account4.CallCenterSupervisorName5" />
        <P20826 para="Account4.EnableCallCenterDispCode">0</P20826>
        <P20847 para="Account4.CCDispCode1" />
        <P20848 para="Account4.CCDispCode2" />
        <P20849 para="Account4.CCDispCode3" />
        <P20850 para="Account4.CCDispCode4" />
        <P20851 para="Account4.CCDispCode5" />
        <P20883 para="Account4.CCDispName1" />
        <P20884 para="Account4.CCDispName2" />
        <P20885 para="Account4.CCDispName3" />
        <P20886 para="Account4.CCDispName4" />
        <P20887 para="Account4.CCDispName5" />
        <P20973 para="Account4.SubscribeExpires">300</P20973>
        <P20979 para="Account4.SCABargeIn">0</P20979>
        <!--Network/Basic-->
        <P8 para="IPv4WanMode">0</P8>
        <P146 para="DHCPHostName">UC903-{{ $mac }}</P146>
        <P147 para="DHCPDomain" />
        <P148 para="DHCPVendor" />
        <P149 para="DHCPUserClass" />
        <P9 para="IpAddrFirst8Bit">0</P9>
        <P10 para="IpAddrSecond8Bit">0</P10>
        <P11 para="IpAddrThird8Bit">0</P11>
        <P12 para="IpAddrLast8Bit">0</P12>
        <P13 para="SubMaskFirst8Bit">0</P13>
        <P14 para="SubMaskSecond8Bit">0</P14>
        <P15 para="SubMaskThird8Bit">0</P15>
        <P16 para="SubMaskLast8Bit">0</P16>
        <P17 para="GatewayFirst8Bit">0</P17>
        <P18 para="GatewaySecond8Bit">0</P18>
        <P19 para="GatewayThird8Bit">0</P19>
        <P20 para="GatewayLast8Bit">0</P20>
        <P21 para="PrimaryDnsFirst8Bit">8</P21>
        <P22 para="PrimaryDnsSecond8Bit">8</P22>
        <P23 para="PrimaryDnsThird8Bit">8</P23>
        <P24 para="PrimaryDnsLast8Bit">8</P24>
        <P25 para="SecondaryDnsFirst8Bit">4</P25>
        <P26 para="SecondaryDnsSecond8Bit">2</P26>
        <P27 para="SecondaryDnsThird8Bit">2</P27>
        <P28 para="SecondaryDnsLast8Bit">2</P28>
        <P82 para="PPPoEAccountID" />
        <P83 para="PPPoEPassword" />
        <P269 para="PPPoEServiceName" />
        <P92 para="DNSServerFirst8Bit">8</P92>
        <P93 para="DNSServerSecond8Bit">8</P93>
        <P94 para="DNSServerThird8Bit">8</P94>
        <P95 para="DNSServerLast8Bit">8</P95>
        <P189 para="ReplyToICMP">1</P189>
        <P190 para="HttpAccess">1</P190>
        @if(isset($settings['htek_pcport_mode']))
        <P231 para="PcPort">{{ $settings['htek_pcport_mode'] }}</P231>
        @else
        <P231 para="PcPort">1</P231>
        @endif
        <P111 para="IPAddress">192.168.22.1</P111>
        <P110 para="SubnetMask">255.255.255.0</P110>
        <P112 para="IPLeaseTime">24</P112>
        <P8639 para="DhcpServerFlag">0</P8639>
        <P113 para="DMZIpAddr" />
        <P150 para="PortMap.WanPort1">0</P150>
        <P158 para="PortMap.LanIp1" />
        <P166 para="PortMap.LanPort1">0</P166>
        <P174 para="PortMap.Protocol1">0</P174>
        <P151 para="PortMap.WanPort2">0</P151>
        <P159 para="PortMap.LanIp2" />
        <P167 para="PortMap.LanPort2">0</P167>
        <P175 para="PortMap.Protocol2">0</P175>
        <P152 para="PortMap.WanPort3">0</P152>
        <P160 para="PortMap.LanIp3" />
        <P168 para="PortMap.LanPort3">0</P168>
        <P176 para="PortMap.Protocol3">0</P176>
        <P153 para="PortMap.WanPort4">0</P153>
        <P161 para="PortMap.LanIp4" />
        <P169 para="PortMap.LanPort4">0</P169>
        <P177 para="PortMap.Protocol4">0</P177>
        <P154 para="PortMap.WanPort5">0</P154>
        <P162 para="PortMap.LanIp5" />
        <P170 para="PortMap.LanPort5">0</P170>
        <P178 para="PortMap.Protocol5">0</P178>
        <P155 para="PortMap.WanPort6">0</P155>
        <P163 para="PortMap.LanIp6" />
        <P171 para="PortMap.LanPort6">0</P171>
        <P179 para="PortMap.Protocol6">0</P179>
        <P156 para="PortMap.WanPort7">0</P156>
        <P164 para="PortMap.LanIp7" />
        <P172 para="PortMap.LanPort7">0</P172>
        <P180 para="PortMap.Protocol7">0</P180>
        <P157 para="PortMap.WanPort8">0</P157>
        <P165 para="PortMap.LanIp8" />
        <P173 para="PortMap.LanPort8">0</P173>
        <P181 para="PortMap.Protocol8">0</P181>
        <!--Network/Advance-->
        <!--Network/Advance/LLDP-->
        <P5438 para="Active">0</P5438>
        <P5439 para="PackedInterval">120</P5439>
        <!--Network/Advance/Qos Set -->
        <P38 para="Layer3QoS">48</P38>
        <P51 para="Layer2QoS.802.1Q/VLANTag">{{ $settings['htek_layer2qos_802_1qvlantag'] ?? '' }}</P51>
        <P87 para="Layer2QoS.802.1pPriorityValue">0</P87>
        <P229 para="DataVLANTag">0</P229>
        <!--Network/Advance/NTP Server-->
        <P30 para="UrlOrIpAddress">{{ $settings['ntp_server_primary'] ?? '' }}</P30>
        <P144 para="DHCPOverrideNTP">0</P144>
        <!--Network/Advance/VPN-->
        <P8629 para="Active">0</P8629>
        <!--Network/Advance/Web Server-->
        <P901 para="HTTPPort">80</P901>
        <P8724 para="HTTPSPort">443</P8724>
        <P8725 para="Type">1</P8725>
        <!--Network/Advance/802.1X-->
        <P8626 para="802.1XMode">0</P8626>
        <P8627 para="Identity" />
        <P8628 para="MD5Password" />
        <!--Network/Advance/Others-->
        <P76 para="STUN Server" />
        <P84 para="KeepAtiveInterval">20</P84>
        <P8684 para="DhcpVlan">0</P8684>
        <!--FunctionKeys-->

        <!--FunctionKeys/LineKey1-->
        <P41200 para="linekey1.Type">{{ $settings['line_key_type_1'] ?? '' }}</P41200>
        <P20600 para="LineKey1.Mode">0</P20600>
        <P41300 para="linekey1.Value">{{ $settings['line_key_value_1'] ?? '' }}</P41300>
        <P41400 para="linekey1.Label">{{ $settings['line_key_label_1'] ?? '' }}</P41400>
        <P41500 para="linekey1.Account">{{ $settings['line_key_line_1'] ?? '' }}</P41500>
        <P41600 para="linekey1.PickupCode">**</P41600>
        <!--FunctionKeys/LineKey2-->
        <P41201 para="LineKey2.Type">{{ $settings['line_key_type_2'] ?? '' }}</P41201>
        <P41301 para="LineKey2.Value">{{ $settings['line_key_value_2'] ?? '' }}</P41301>
        <P41401 para="LineKey2.Label">{{ $settings['line_key_label_2'] ?? '' }}</P41401>
        <P41501 para="LineKey2.Account">{{ $settings['line_key_line_2'] ?? '' }}</P41501>
        <P41601 para="LineKey2.PickupCode">**</P41601>
        <!--FunctionKeys/LineKey3-->
        <P41202 para="LineKey3.Type">{{ $settings['line_key_type_3'] ?? '' }}</P41202>
        <P41302 para="LineKey3.Value">{{ $settings['line_key_value_3'] ?? '' }}</P41302>
        <P41402 para="LineKey3.Label">{{ $settings['line_key_label_3'] ?? '' }}</P41402>
        <P41502 para="LineKey3.Account">{{ $settings['line_key_line_3'] ?? '' }}</P41502>
        <P41602 para="LineKey3.PickupCode" />
        <!--FunctionKeys/LineKey4-->
        <P41203 para="LineKey4.Type">{{ $settings['line_key_type_4'] ?? '' }}</P41203>
        <P41303 para="LineKey4.Value">{{ $settings['line_key_value_4'] ?? '' }}</P41303>
        <P41403 para="LineKey4.Label">{{ $settings['line_key_label_4'] ?? '' }}</P41403>
        <P41503 para="LineKey4.Account">{{ $settings['line_key_line_4'] ?? '' }}</P41503>
        <P41603 para="LineKey4.PickupCode" />
        <!--FunctionKeys/LineKey5-->
        <P20200 para="LineKey5.Type">{{ $settings['line_key_type_5'] ?? '' }}</P20200>
        <P20201 para="LineKey5.Value">{{ $settings['line_key_value_5'] ?? '' }}</P20201>
        <P20202 para="LineKey5.Label">{{ $settings['line_key_label_5'] ?? '' }}</P20202>
        <P20203 para="LineKey5.Account">{{ $settings['line_key_line_5'] ?? '' }}</P20203>
        <P20204 para="LineKey5.PickupCode" />
        <!--FunctionKeys/LineKey6-->
        <P20205 para="LineKey6.Type">{{ $settings['line_key_type_6'] ?? '' }}</P20205>
        <P20206 para="LineKey6.Value">{{ $settings['line_key_value_6'] ?? '' }}</P20206>
        <P20207 para="LineKey6.Label">{{ $settings['line_key_label_6'] ?? '' }}</P20207>
        <P20208 para="LineKey6.Account">{{ $settings['line_key_line_6'] ?? '' }}</P20208>
        <P20209 para="LineKey6.PickupCode" />
        <!--FunctionKeys/LineKey7-->
        <P20210 para="LineKey7.Type">{{ $settings['line_key_type_7'] ?? '' }}</P20210>
        <P20211 para="LineKey7.Value">{{ $settings['line_key_value_7'] ?? '' }}</P20211>
        <P20212 para="LineKey7.Label">{{ $settings['line_key_label_7'] ?? '' }}</P20212>
        <P20213 para="LineKey7.Account">{{ $settings['line_key_line_7'] ?? '' }}</P20213>
        <P20214 para="LineKey7.PickupCode" />
        <!--FunctionKeys/LineKey8-->
        <P20215 para="LineKey8.Type">{{ $settings['line_key_type_8'] ?? '' }}</P20215>
        <P20216 para="LineKey8.Value">{{ $settings['line_key_value_8'] ?? '' }}</P20216>
        <P20217 para="LineKey8.Label">{{ $settings['line_key_label_8'] ?? '' }}</P20217>
        <P20218 para="LineKey8.Account">{{ $settings['line_key_line_8'] ?? '' }}</P20218>
        <P20219 para="LineKey8.PickupCode" />
        <!--FunctionKeys/LineKey9-->
        <P20220 para="LineKey9.Type">{{ $settings['line_key_type_9'] ?? '' }}</P20220>
        <P20221 para="LineKey9.Value">{{ $settings['line_key_value_9'] ?? '' }}</P20221>
        <P20222 para="LineKey9.Label">{{ $settings['line_key_label_9'] ?? '' }}</P20222>
        <P20223 para="LineKey9.Account">{{ $settings['line_key_line_9'] ?? '' }}</P20223>
        <P20224 para="LineKey9.PickupCode" />
        <!--FunctionKeys/LineKey10-->
        <P20225 para="LineKey10.Type">{{ $settings['line_key_type_10'] ?? '' }}</P20225>
        <P20226 para="LineKey10.Value">{{ $settings['line_key_value_10'] ?? '' }}</P20226>
        <P20227 para="LineKey10.Label">{{ $settings['line_key_label_10'] ?? '' }}</P20227>
        <P20228 para="LineKey10.Account">{{ $settings['line_key_line_10'] ?? '' }}</P20228>
        <P20229 para="LineKey10.PickupCode" />
        <!--FunctionKeys/LineKey11-->
        <P20230 para="LineKey11.Type">{{ $settings['line_key_type_11'] ?? '' }}</P20230>
        <P20231 para="LineKey11.Value">{{ $settings['line_key_value_11'] ?? '' }}</P20231>
        <P20232 para="LineKey11.Label">{{ $settings['line_key_label_11'] ?? '' }}</P20232>
        <P20233 para="LineKey11.Account">{{ $settings['line_key_line_11'] ?? '' }}</P20233>
        <P20234 para="LineKey11.PickupCode" />
        <!--FunctionKeys/LineKey12-->
        <P20235 para="LineKey12.Type">{{ $settings['line_key_type_12'] ?? '' }}</P20235>
        <P20236 para="LineKey12.Value">{{ $settings['line_key_value_12'] ?? '' }}</P20236>
        <P20237 para="LineKey12.Label">{{ $settings['line_key_label_12'] ?? '' }}</P20237>
        <P20238 para="LineKey12.Account">{{ $settings['line_key_line_12'] ?? '' }}</P20238>
        <P20239 para="LineKey12.PickupCode" />

        <!--FunctionKeys/MemoryKey-->
        @foreach(($keys['memory'] ?? []) as $row)
        @php $id = $row['device_key_line'] - 1; @endphp
        <P4220{{ $id }} para="MemoryKey{{ $row['device_key_id'] }}.Type">{{ $row['device_key_type'] }}</P4220{{ $id }}>
        <P4230{{ $id }} para="MemoryKey{{ $row['device_key_id'] }}.Value">{{ $row['memory_key_value'] }}</P4230{{ $id }}>
        <P4240{{ $id }} para="MemoryKey{{ $row['device_key_id'] }}.Account">{{ $row['device_key_line'] }} </P4240{{ $id }}>
        <P4250{{ $id }} para="MemoryKey{{ $row['device_key_id'] }}.PickupCode" />
        @endforeach

        <!--FunctionKeys/ProgrammableKey-->
        <P43200 para="SoftKey1.Type">36</P43200>
        <P43300 para="SoftKey1.Account">0</P43300>
        <P43400 para="SoftKey1.Value" />
        <P43201 para="SoftKey2.Type">37</P43201>
        <P43301 para="SoftKey2.Account">0</P43301>
        <P43401 para="SoftKey2.Value" />
        <P43202 para="SoftKey3.Type">21</P43202>
        <P43302 para="SoftKey3.Account">0</P43302>
        <P43402 para="SoftKey3.Value" />
        <P43203 para="SoftKey4.Type">38</P43203>
        <P43303 para="SoftKey4.Account">0</P43303>
        <P43403 para="SoftKey4.Value" />
        <P43204 para="UpKey.Type">36</P43204>
        <P43304 para="UpKey.Account">0</P43304>
        <P43404 para="UpKey.Value" />
        <P43205 para="DownKey.Type">37</P43205>
        <P43305 para="DownKey.Account">0</P43305>
        <P43405 para="DownKey.Value" />
        <P43206 para="LeftKey.Type">41</P43206>
        <P43306 para="LeftKey.Account">0</P43306>
        <P43406 para="LeftKey.Value" />
        <P43207 para="RightKey.Type">42</P43207>
        <P43307 para="RightKey.Account">0</P43307>
        <P43407 para="RightKey.Value" />
        <P43208 para="OKKey.Type">40</P43208>
        <P43308 para="OKKey.Account">0</P43308>
        <P43408 para="OKKey.Value" />
        <P43209 para="CancelKey.Type">0</P43209>
        <P43309 para="CancelKey.Account">0</P43309>
        <P43409 para="CancelKey.Value" />
        <P43212 para="MuteKey.Type">0</P43212>
        <P43312 para="MuteKey.Account">0</P43312>
        <P43412 para="MuteKey.Value" />
        <P43210 para="ConfKey.Type">0</P43210>
        <P43310 para="ConfKey.Account">0</P43310>
        <P43410 para="ConfKey.Value" />
        <P43213 para="TranKey.Type">18</P43213>
        <P43313 para="TranKey.Account">0</P43313>
        <P43413 para="TranKey.Value" />
        <P43211 para="HoldKey.Type">0</P43211>
        <P43311 para="HoldKey.Account">0</P43311>
        <P43411 para="HoldKey.Value" />
        <!--Setting-->
        <!--Setting/Preference-->
        <P2525 para="Preference.WebLanguage">0</P2525>
        <P64 para="Preference.TimeZone">{{ $settings['htek_time_zone'] ?? '' }}</P64>
        <P143 para="Preference.DHCPTime">0</P143>

        @if(isset($settings['htek_dst']))
        <P75 para="Preference.DaylightSavingTime">{{ $settings['htek_dst'] }}</P75>
        @else
        <P75 para="Preference.DaylightSavingTime">0</P75>
        @endif

        <P23117 para="Preference.DaylightSavingTimeType">0</P23117>
        <P23118 para="Preference.DSTStartWeekMonth">1</P23118>
        <P23119 para="Preference.DSTStartWeeknum">1</P23119>
        <P23120 para="Preference.DSTStartWeekday">0</P23120>
        <P23121 para="Preference.DSTStartWeekhour">0</P23121>
        <P23122 para="Preference.DSTEndWeekMonth">12</P23122>
        <P23123 para="Preference.DSTEndWeeknum">1</P23123>
        <P23124 para="Preference.DSTEndWeekday">0</P23124>
        <P23125 para="Preference.DSTEndWeekhour">23</P23125>
        <P102 para="Preference.DateDisplayFormat">{{ $settings['htek_date_display_format'] ?? '' }}</P102>

        @if(isset($settings['htek_time_format']))
        <P8624 para="Preference.TimeFormat">{{ $settings['htek_time_format'] }}</P8624>
        @else
        <P8624 para="Preference.TimeFormat">0</P8624>
        @endif

        <P88 para="Preference.LockKeypadUpdate">0</P88>
        <P1300 para="Preference.KeypadDTMFTone">0</P1300>
        <P249 para="Preference.MICVolumeAmplification">0</P249>
        <P8683 para="Preference.BacklightTime">0</P8683>
        @if(isset($settings['htek_screentimeout']))
        <P8940 para="Preference.ScreenTimeOut">{{ $settings['htek_screentimeout'] }}</P8940>
        @else
        <P8940 para="Preference.ScreenTimeOut">4</P8940>
        @endif
        <P8950 para="Preference.ScreenSaverType">1</P8950>
        @if(isset($settings['htek_account1_ringtone']))
        <P8721 para="Account1.AccountRingTone">{{ $settings['htek_account1_ringtone'] }}</P8721>
        @else
        <P8721 para="Account1.AccountRingTone">2</P8721>
        @endif
        <P85 para="Preference.NoKeyEntryTimeout">{{ $settings['htek_preference_nokeyentrytimeout'] ?? '' }}</P85>
        <P3734 para="Preference.LEDPowerStatus">1</P3734>
        <P3735 para="Preference.LEDRingingStatus">1</P3735>
        <P3736 para="Preference.LEDMissCallsStatus">0</P3736>
        <P8672 para="Preference.IncomingCallShowMode">0</P8672>
        <P8678 para="Preference.PhoneFontHeightSize">1</P8678>
        <P8680 para="Preference.WatchDogEnable">1</P8680>
        <P2532 para="Preference.DisplayMode">1</P2532>
        <P8660 para="Preference.WallPaper">1</P8660>
        <P20018 para="Preference.DialFirstDigit">1</P20018>
        <P1399 para="Preference.AlertInternalText" />
        <P1402 para="Preference.AlertInternalRinger">0</P1402>
        <P1400 para="Preference.AlertExternalText" />
        <P1403 para="Preference.AlertExternalText">0</P1403>
        <P1401 para="Preference.AlertGroupText" />
        <P1404 para="Preference.AlertGroupText">0</P1404>
        <P20017 para="Preference.RefreshCallerIdViaContact">0</P20017>
        <P20019 para="Preference.HeadSetPriority">0</P20019>
        <P20020 para="Preference.RingerDeviceForHeadSet">0</P20020>
        <P8621 para="Preference.LcdLanguage">0</P8621>
        <P56203 para="Preference.BusyToneTimer">4</P56203>
        <P23126 para="Preference.Autologouttime">6</P23126>
        <P23131 para="Preference.Rebootintalking">0</P23131>
        <!--Setting/Features-->
        <P53100 para="ForwardAlways.OnOff">0</P53100>
        <P53101 para="ForwardAlways.Target" />
        <P53102 para="ForwardAlways.OnCode" />
        <P53103 para="ForwardAlways.OffCode" />
        <P53110 para="ForwardBusy.OnOff">0</P53110>
        <P53111 para="ForwardBusy.Target" />
        <P53112 para="ForwardBusy.OnCode" />
        <P53113 para="ForwardBusy.OffCode" />
        <P53120 para="ForwardNoAnswer.OnOff">0</P53120>
        <P53124 para="ForwardNoAnswer.AfterRingTime">60</P53124>
        <P53121 para="ForwardNoAnswer.Target" />
        <P53122 para="ForwardNoAnswer.OnCode" />
        <P53123 para="ForwardNoAnswer.OffCode" />
        <P53200 para="DND.OnCode" />
        <P53201 para="DND.OffCode" />
        <P53202 para="DND.AuthNum" />
        <P4210 para="Hotline.Number" />
        <P8638 para="Hotline.TimeOut">0</P8638>
        <P3201 para="Transfer.BlindTransferOnHook">1</P3201>
        <P3202 para="Transfer.Semi-AttendedTransfer">1</P3202>
        <P3204 para="Transfer.AttendedTransferOnHook">1</P3204>
        <P3205 para="Transfer.TransferModeviaDSSkey">0</P3205>
        <P3207 para="Transfer.HoldTransferOnHook">0</P3207>
        <P4701 para="CallPickup.DirectCallPickup">0</P4701>
        <P4745 para="CallPickup.DirectCallPickupCode" />
        <P4702 para="CallPickup.GroupCallPickup">0</P4702>
        <P4746 para="CallPickup.GroupCallPickupCode" />
        <P4703 para="CallPickup.VisualAlertForBLFPickup">0</P4703>
        <P4704 para="CallPickup.AudioAlertForBLFPickup">0</P4704>
        <P8630 para="PhoneLock.KeypadLock">0</P8630>
        <P5730 para="PhoneLock.PhoneUnlockPin" />
        <P5731 para="PhoneLock.AutoLockTime">15</P5731>
        <P5732 para="PhoneLock.Emergency" />
        <P8849 para="CallWaiting.OnOff">1</P8849>
        <P8850 para="CallWaiting.Tone">1</P8850>
        <P56204 para="AutoRedial.OnOff">0</P56204>
        <P56205 para="AutoRedial.Interval">3</P56205>
        <P56206 para="AutoRedial.Times">3</P56206>
        <P20930 para="CallPark.Flag">0</P20930>
        <P20931 para="CallPark.Code" />
        <P24011 para="RemoteControl.httppost" />
        <P24012 para="RemoteControl.Sipnotify">1</P24012>
        <!--Setting/Tone-->
        <P4000 para="Tones.DialTone">f1=350@-13,f2=440@-13,c=0/0;</P4000>
        <P4001 para="Tones.RingbackTone">f1=440@-19,f2=480@-19,c=2000/4000;</P4001>
        <P4002 para="Tones.BusyTone">f1=480@-24,f2=620@-24,c=500/500;</P4002>
        <P4003 para="Tones.ReorderTone">f1=480@-24,f2=620@-24,c=250/250;</P4003>
        <P4004 para="Tones.ConfirmationTone">f1=350@-11,f2=440@-11,c=100/100-100/100-100/100;</P4004>
        <P4005 para="Tones.CallWaitingTone">f1=440@-13,c=300/10000-300/10000-0/0;</P4005>
        <!--Setting/SMS-->
        <P58100 para="SMS.Account" />
        <P58101 para="SMS.Number" />
        <P58102 para="SMS.Message" />
        <!--Setting/ActionUrl-->
        <P3701 para="ActionUrl.SetupCompleted" />
        <P3702 para="ActionUrl.LogOn" />
        <P3703 para="ActionUrl.LogOff" />
        <P3704 para="ActionUrl.RegisterFailed" />
        <P3705 para="ActionUrl.OffHook" />
        <P3706 para="ActionUrl.OnfHook" />
        <P3707 para="ActionUrl.IncomingCall" />
        <P3708 para="ActionUrl.OutgoingCall" />
        <P3709 para="ActionUrl.CallEstablished" />
        <P3710 para="ActionUrl.CallTerminated" />
        <P3711 para="ActionUrl.OpenDND" />
        <P3712 para="ActionUrl.CloseDND" />
        <P3713 para="ActionUrl.OpenAlwaysForward" />
        <P3714 para="ActionUrl.CloseAlwaysForward" />
        <P3715 para="ActionUrl.OpenBusyForward" />
        <P3716 para="ActionUrl.CloseBusyForward" />
        <P3717 para="ActionUrl.OpenNoAnswerForward" />
        <P3718 para="ActionUrl.CloseNoAnswerForward" />
        <P3719 para="ActionUrl.TransferCall" />
        <P3720 para="ActionUrl.BlindTrandfercall" />
        <P3721 para="ActionUrl.AttendedTransferCall" />
        <P3722 para="ActionUrl.Hold" />
        <P3723 para="ActionUrl.Unhold" />
        <P3724 para="ActionUrl.Mute" />
        <P3725 para="ActionUrl.Unmute" />
        <P3726 para="ActionUrl.MissedCall" />
        <P3727 para="ActionUrl.IdleToBusy" />
        <P3728 para="ActionUrl.BusyToIdel" />
        <P3729 para="ActionUrl.ForwardIncomingCall" />
        <P3730 para="ActionUrl.RejectIncomingCall" />
        <P3731 para="ActionUrl.AnswerNewIncomingCall" />
        <P3732 para="ActionUrl.TransferFinished" />
        <P3733 para="ActionUrl.TransfeFailed" />
        <!--Setting/SoftKeyLayout-->
        <P8751 para="SoftKeyLayout.CustomSoftkey">1</P8751>
        <!--Directory-->
        
        <!--Directory/LDAP-->
        <P5430 para="LDAP.NameFilter">(cn=%)</P5430>
        <P5431 para="LDAP.NumberFilter">(|(telephoneNumber=%)(Mobile=%))</P5431>
        <P5432 para="LDAP.ServerAddress" />
        <P5433 para="LDAP.Port">389</P5433>
        <P5434 para="LDAP.Base" />
        <P5435 para="LDAP.UserName" />
        <P5436 para="LDAP.Password" />
        <P5437 para="LDAP.Max.Hits">32000</P5437>
        <P23136 para="LDAP.NameAttributes" />
        <P23137 para="LDAP.NumberAttributes" />
        <P5440 para="LDAP.DisplayName">cn</P5440>
        <P5442 para="LDAP.SearchDelay">0</P5442>
        <P5441 para="LDAP.Protocol">0</P5441>
        <P5443 para="LDAP.LookupForIncomingCall">0</P5443>
        <P5444 para="LDAP.SortingResults">0</P5444>
        <!--Directory/BroadSoft-->
        <P8519 para="BroadSoftItem" />
        <P8520 para="BroadSoft1.DisplayName" />
        <P8521 para="BroadSoft1.Server" />
        <P8522 para="BroadSoft1.Port">0</P8522>
        <P8523 para="BroadSoft1.User" />
        <P8524 para="BroadSoft1.PassWord" />
        <P8530 para="BroadSoft2.DisplayName" />
        <P8531 para="BroadSoft2.Server" />
        <P8532 para="BroadSoft2.Port">0</P8532>
        <P8533 para="BroadSoft2.User" />
        <P8534 para="BroadSoft2.PassWord" />
        <P8540 para="BroadSoft3.DisplayName" />
        <P8541 para="BroadSoft3.Server" />
        <P8542 para="BroadSoft3.Port">0</P8542>
        <P8543 para="BroadSoft3.User" />
        <P8544 para="BroadSoft3.PassWord" />
        <P8550 para="BroadSoft4.DisplayName" />
        <P8551 para="BroadSoft4.Server" />
        <P8552 para="BroadSoft4.Port">0</P8552>
        <P8553 para="BroadSoft4.User" />
        <P8554 para="BroadSoft4.PassWord" />
        <P8560 para="BroadSoft5.DisplayName" />
        <P8561 para="BroadSoft5.Server" />
        <P8562 para="BroadSoft5.Port">0</P8562>
        <P8563 para="BroadSoft5.User" />
        <P8564 para="BroadSoft5.PassWord" />
        <P8570 para="BroadSoft6.DisplayName" />
        <P8571 para="BroadSoft6.Server" />
        <P8572 para="BroadSoft6.Port">0</P8572>
        <P8573 para="BroadSoft6.User" />
        <P8574 para="BroadSoft6.PassWord" />
        <!--Directory/CallLog-->
        <P8579 para="CallLogItem" />
        <P8580 para="CallLog1.DisplayName" />
        <P8581 para="CallLog1.Server" />
        <P8582 para="CallLog1.Port">0</P8582>
        <P8583 para="CallLog1.User" />
        <P8584 para="CallLog1.PassWord" />
        <P8585 para="CallLog2.DisplayName" />
        <P8586 para="CallLog2.Server" />
        <P8587 para="CallLog2.Port">0</P8587>
        <P8588 para="CallLog2.User" />
        <P8589 para="CallLog2.PassWord" />
        <P8590 para="CallLog3.DisplayName" />
        <P8591 para="CallLog3.Server" />
        <P8592 para="CallLog3.Port">0</P8592>
        <P8593 para="CallLog3.User" />
        <P8594 para="CallLog3.PassWord" />
        <!--Management-->
        <!--Management/PassWord-->
        <P8681 para="LogUser.Admin">admin</P8681>
        <P8682 para="LogUser.User">user</P8682>
        <P2 para="AdminPassword">{{ $settings['admin_password'] ?? '' }}</P2>
        <P196 para="UserPassword" />
        <!--Management/AutoProvision-->
        <P212 para="FirmwareUpGrade.UrgrateMode">3</P212>
        <P192 para="FirmwareUpGrade.FirmwareServerPath">http://h-tek.com/fm</P192>
        <P237 para="FirmwareUpGrade.ConfigServerPath">{{ $settings['provision_base_url'] ?? ''}}</P237>
        <P1145 para="FirmwareUpGrade.AllowDHCPOption">66</P1145>
        <P145 para="FirmwareUpGrade.ToOverrideServer">1</P145>
        <P194 para="FirmwareUpGrade.AutoUpgrade">1</P194>
        <P193 para="FirmwareUpGrade.CheckUpgradeTimes">10080</P193>
        <P23132 para="FirmwareUpGrade.UpgradeEXPRom">0</P23132>
        <P1360 para="FirmwareUpGrade.UserName">{{ $settings['http_auth_username'] ?? '' }}</P1360>
        <P1361 para="FirmwareUpGrade.Password">{{ $settings['http_auth_password'] ?? '' }}</P1361>
        <P232 para="FirmwareUpGrade.FilePrefix" />
        <P233 para="FirmwareUpGrade.FilePostfix" />
        <P238 para="FirmwareUpGrade.CheckMode">0</P238>
        <P240 para="FirmwareUpGrade.AuthenticateCfgFile">0</P240>
        <P8631 para="FirmwareUpGrade.SetCommonAESKey" />
        <P331 para="PhonebookXmlDownload.ServerPath" />
        <P332 para="PhonebookXmlDownload.Interval">0</P332>
        <P333 para="PhonebookXmlDownload.RemoveMEOnDownload">0</P333>
        <P330 para="PhonebookXmlDownload.Enable">0</P330>
        <P8150 para="SNMPService.Enable">0</P8150>
        <P8151 para="SNMPService.GetCommunity" />
        <P8152 para="SNMPService.SetCommunity" />
        <P8153 para="SNMPService.ManagerIP1" />
        <P8154 para="SNMPService.ManagerIP2" />
        <P8155 para="SNMPService.ManagerIP3" />
        <P8156 para="SNMPService.ManagerIP4" />
        <!--Management/SystemLog-->
        <P207 para="SNMPService.SyslogServer" />
        <P208 para="SNMPService.Sysloglevel">0</P208>
        <!--Global Config-->
        <P8685 para="GlobalConfig.CopyRight">2005-2016 All Rights Reserved</P8685>
        <P8686 para="GlobalConfig.OEMTag">HANLONG</P8686>
        <P8951 para="GlobalConfig.LogoText" />
        <P40000 para="GlobalConfig.UserAgent" />
        
        <!--Add Config for Expansion 1-->
        <!-- Optimized Expansion Key Loop (221 Keys) -->
        @php
            $exp_modules = [
                ['name' => 'Exp1_1', 'count' => 20],
                ['name' => 'Exp1_2', 'count' => 20],
                ['name' => 'Exp2_1', 'count' => 20],
                ['name' => 'Exp2_2', 'count' => 20],
                ['name' => 'Exp3_1', 'count' => 20],
                ['name' => 'Exp3_2', 'count' => 20],
                ['name' => 'Exp4_1', 'count' => 20],
                ['name' => 'Exp4_2', 'count' => 20],
                ['name' => 'Exp5_1', 'count' => 20],
                ['name' => 'Exp5_2', 'count' => 20],
                ['name' => 'Exp6_1', 'count' => 20],
                ['name' => 'Exp6_2', 'count' => 20],
            ];
            $current_p = 60000;
            $total_key_index = 1;
        @endphp

        @foreach($exp_modules as $mod)
            @for($i = 1; $i <= $mod['count']; $i++)
<P{{ $current_p++ }} para="{{ $mod['name'] }}_{{ $i }}.KeyType">{{ $settings['expansion_key_type_' . $total_key_index] ?? '' }}</P{{ $current_p - 1 }}>
<P{{ $current_p++ }} para="{{ $mod['name'] }}_{{ $i }}.Value">{{ $settings['expansion_key_value_' . $total_key_index] ?? '' }}</P{{ $current_p - 1 }}>
<P{{ $current_p++ }} para="{{ $mod['name'] }}_{{ $i }}.Label">{{ $settings['expansion_key_label_' . $total_key_index] ?? '' }}</P{{ $current_p - 1 }}>
<P{{ $current_p++ }} para="{{ $mod['name'] }}_{{ $i }}.Account">{{ $settings['expansion_key_line_' . $total_key_index] ?? '' }}</P{{ $current_p - 1 }}>
<P{{ $current_p++ }} para="{{ $mod['name'] }}_{{ $i }}.PickupCode">{{ $settings['expansion_key_pickup_' . $total_key_index] ?? '' }}</P{{ $current_p - 1 }}>
            @php $total_key_index++; @endphp
            @endfor
        @endforeach

        <!--Add Config for Multicast Paging-->
        <P20021 para="MulticastPaging1.Value" />
        <P20022 para="MulticastPaging1.Label" />
        <P20023 para="MulticastPaging2.Value" />
        <P20024 para="MulticastPaging2.Label" />
        <P20025 para="MulticastPaging3.Value" />
        <P20026 para="MulticastPaging3.Label" />
        <P20027 para="MulticastPaging4.Value" />
        <P20028 para="MulticastPaging4.Label" />
        <P20029 para="MulticastPaging5.Value" />
        <P20030 para="MulticastPaging5.Label" />
        <P20031 para="MulticastPaging6.Value" />
        <P20032 para="MulticastPaging6.Label" />
        <P20033 para="MulticastPaging7.Value" />
        <P20034 para="MulticastPaging7.Label" />
        <P20035 para="MulticastPaging8.Value" />
        <P20036 para="MulticastPaging8.Label" />
        <P20037 para="MulticastPaging9.Value" />
        <P20038 para="MulticastPaging9.Label" />
        <P20039 para="MulticastPaging10.Value" />
        <P20040 para="MulticastPaging10.Label" />
        <P20041 para="MulticastPaging.Barge">0</P20041>
        <P20042 para="MulticastPaging.PriorityActive">0</P20042>
        <P20043 para="ProgrammableKey1.Label" />
        <P20044 para="ProgrammableKey2.Label" />
        <P20045 para="ProgrammableKey3.Label" />
        <P20046 para="ProgrammableKey4.Label" />
        <P20058 para="ToneSelectCountry">0</P20058>
        <P20059 para="AlertRingText4" />
        <P20060 para="AlertRingText5" />
        <P20061 para="AlertRingText6" />
        <P20062 para="AlertRingText7" />
        <P20063 para="AlertRingText8" />
        <P20064 para="AlertRingText9" />
        <P20065 para="AlertRingText10" />
        <P20066 para="AlertRingFile4">0</P20066>
        <P20067 para="AlertRingFile5">0</P20067>
        <P20068 para="AlertRingFile6">0</P20068>
        <P20069 para="AlertRingFile7">0</P20069>
        <P20070 para="AlertRingFile8">0</P20070>
        <P20071 para="AlertRingFile9">0</P20071>
        <P20072 para="AlertRingFile10">0</P20072>
        <P20073 para="RedialType">0</P20073>
        <P1085 para="DialNow-TimeOut">{{ $settings['htek_dialnow_timeout'] ?? '' }}</P1085>
        <P39 para="MinRtpPort">12100</P39>
        <P739 para="MaxRtpPort">12200</P739>
        <P20074 para="InterComBarge">0</P20074>
        <P20075 para="LcdShowMissCall">0</P20075>
        <P20076 para="DSTStartTime-Month">{{ $settings['htek_dststarttime-month'] ?? '' }}</P20076>
        <P20077 para="DSTStartTime-Day">{{ $settings['htek_dststarttime-day'] ?? '' }}</P20077>
        <P20078 para="DSTStartTime-Hour">{{ $settings['htek_dststarttime-hour'] ?? '' }}</P20078>
        <P20079 para="DSTEndTime-Month">{{ $settings['htek_dstendttime-month'] ?? '' }}</P20079>
        <P20080 para="DSTEndTime-Day">{{ $settings['htek_dstsendtime-day'] ?? '' }}</P20080>
        <P20081 para="DSTEndTime-Hour">{{ $settings['htek_dstsendtime-hour'] ?? '' }}</P20081>
        <P20082 para="VoiceMesgStatus">0</P20082>
        <P20083 para="HandSetSendVolume">0</P20083>
        <P20084 para="HeadSetSendVolume">0</P20084>
        <P20085 para="BLFSelectType">0</P20085>
        <P20086 para="CurrentCallStatus-1">0</P20086>
        <P20087 para="CurrentCallStatus-2">1</P20087>
        <P20088 para="CurrentCallStatus-3">2</P20088>
        <P20089 para="CurrentCallStatus-4">3</P20089>
        <P20090 para="CurrentCallStatus-5">4</P20090>
        <P20091 para="CurrentCallStatus-6">5</P20091>
        <P20092 para="CurrentCallStatus-7">6</P20092>
        <P20093 para="CurrentCallStatus-8">0</P20093>
        <P20094 para="CurrentCallStatus-9">0</P20094>
        <P20095 para="CurrentCallStatus-10">0</P20095>
        <P20096 para="BlfStatusText-1">terminated</P20096>
        <P20097 para="BlfStatusText-2">early</P20097>
        <P20098 para="BlfStatusText-3">confirmed</P20098>
        <P20099 para="BlfStatusText-4">confirmed</P20099>
        <P20100 para="BlfStatusText-5">confirmed</P20100>
        <P20101 para="BlfStatusText-6">confirmed</P20101>
        <P20102 para="BlfStatusText-7">unknown</P20102>
        <P20103 para="BlfStatusText-8" />
        <P20104 para="BlfStatusText-9" />
        <P20105 para="BlfStatusText-10" />
        <P20106 para="BlfLedMode-1">2</P20106>
        <P20107 para="BlfLedMode-2">6</P20107>
        <P20108 para="BlfLedMode-3">1</P20108>
        <P20109 para="BlfLedMode-4">1</P20109>
        <P20110 para="BlfLedMode-5">1</P20110>
        <P20111 para="BlfLedMode-6">1</P20111>
        <P20112 para="BlfLedMode-7">0</P20112>
        <P20113 para="BlfLedMode-8">0</P20113>
        <P20114 para="BlfLedMode-9">0</P20114>
        <P20115 para="BlfLedMode-10">0</P20115>
        <P20116 para="SuppressDTMF">1</P20116>
        <P20117 para="SuppressDTMFDelay">1</P20117>
        <P20118 para="VoiceMailTone">0</P20118>
        <P20119 para="RTCPReportFlag">0</P20119>
        <P20120 para="RTCPReportCollector" />
        <P20121 para="RTCPReportFormat" />
        <P20155 para="ServerCaType">0</P20155>
        <P20156 para="TrustedCaType">2</P20156>
        <P20163 para="NetWork.StaticDNS">0</P20163>
        <P20164 para="MulticastPaging.MulticatCodec">0</P20164>
        <P20165 para="FirmwareUpGrade.PnPActive">1</P20165>
        <P20172 para="Preference.VmTransfer">0</P20172>
        <P20173 para="Preference.VmTransferCode" />
        <P20174 para="FirmwareUpGrade.RingUrl" />
        <P20175 para="FirmwareUpGrade.LanguageUrl" />
        <P20176 para="FirmwareUpGrade.HlpresUrl" />
        <P20177 para="FirmwareUpGrade.ExpPresUrl" />
        <P20178 para="FirmwareUpGrade.VPNUrl" />
        <P20179 para="FirmwareUpGrade.TCAUrl" />
        <P20180 para="FirmwareUpGrade.SCAUrl" />
        <P20051 para="FirmwareUpGrade.ScreensaverServerURL" />
        <P20052 para="FirmwareUpGrade.WallpaperServerURL" />
        <P8622 para="DateTime.BackUpNTPServer">{{ $settings['ntp_server_secondary'] ?? '' }}</P8622>
        <P40002 para="FirmwareUpGrade.ExpServer" />
        <P8620 para="Preference.HistoryRecord">0</P8620>
        <P20932 para="FirmwareUpGrade.ZeroTouchTime">5</P20932>
        <P20933 para="FirmwareUpGrade.ZeroTouchEnable">0</P20933>
        <P20934 para="Preference.ZeroTouchType">0</P20934>
        <P20935 para="Preference.ConfReleaseType">0</P20935>
        <P20943 para="Preference.DetectIPConflict">1</P20943>
        <P20944 para="BwXsiDir.AllowSipAuthForXsi">0</P20944>
        <P20945 para="BwXsiDir.Host" />
        <P20946 para="BwXsiDir.Port">80</P20946>
        <P20947 para="BwXsiDir.ServerType">0</P20947>
        <P20948 para="BwXsiDir.UserId" />
        <P20949 para="BwXsiDir.PassWord" />
        <P20950 para="BwXsiDir.UCUser" />
        <P20951 para="BwXsiDir.UCPassWord" />
        <P20952 para="LcdItemLevelEnable">0</P20952>
        <P20953 para="BwXsiDir.Group">1</P20953>
        <P20954 para="BwXsiDir.GroupCommon">1</P20954>
        <P20955 para="BwXsiDir.Enterprise">1</P20955>
        <P20956 para="BwXsiDir.EnterpriseCommon">1</P20956>
        <P20957 para="BwXsiDir.Personal">1</P20957>
        <P20958 para="BwXsiDir.CustomDir">1</P20958>
        <P20959 para="BwXsiDir.CallLogs">1</P20959>
        <P20960 para="BwXsiDir.GroupName">Group</P20960>
        <P20961 para="BwXsiDir.GroupCName">GroupCommon</P20961>
        <P20962 para="BwXsiDir.EnterpriseName">Interoperability</P20962>
        <P20963 para="BwXsiDir.EnterpriseCName">EnterpriseCommon</P20963>
        <P20964 para="BwXsiDir.PersonalName">Personal</P20964>
        <P20982 para="Preference.SpeedDialDetectDigitMap">0</P20982>
        <P20983 para="Preference.CheckSyncWithAuth">0</P20983>
        <P20984 para="Features.CallBackCode" />
        <P23204 para="Preference.PlayHoldTone">0</P23204>
        <P23205 para="Preference.PlayHoldToneDelay">0</P23205>
        <P20992 para="Preference.OpenSideTone">0</P20992>
        <P23127 para="DateTime.SIPDateOverideTime">0</P23127>
        <P23128 para="DateTime.SIPDateSelectedAccount">0</P23128>
        <P23139 para="BwXsiDir.EnableUCOne">0</P23139>
        <P23140 para="Preference.EXPBacklightLevel">8</P23140>
        <P23253 para="Preference.LabelScroll">0</P23253>
        <P23376 para="TLSCommenNameValidation">1</P23376>
        <P23377 para="TLSOnlyAcceptTrustCA">1</P23377>
        <P24015 para="TLSChooseTLSVersion">1</P24015>
        <P20995 para="AutoProvision.SoftkeyLayoutUrl" />
        <P23169 para="Features.Popups.MissedCall">0</P23169>
        <P23170 para="Features.Popups.ForwardCall">0</P23170>
        <P23171 para="Features.Popups.VoiceMail">0</P23171>
        <P23172 para="Features.Popups.TextMessage">0</P23172>
        <P24053 para="Network.Advanced.Vlan.WANVlan">{{ $settings['htek_network_advanced_vlan_wanvlan'] ?? '' }}</P24053>
        <P24054 para="Network.Advanced.Vlan.PCVlan">1</P24054>
        <P24064 para="Setting.DateTime.DHCPOPTION100">0</P24064>
        <P24065 para="Account1.Basic.DHCPSIPServer">0</P24065>
        <P24744 para="Setting.Preference.DisplayDefaultAid">0</P24744>
    </config>
</hl_provision>

@break

@endswitch