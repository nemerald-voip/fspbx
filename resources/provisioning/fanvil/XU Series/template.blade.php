{{-- version: 1.1.3 --}}

@switch($flavor)

{{-- Fanvil X3U, X4U, X5U, and X6U per-device configuration --}}
@case('mac.cfg')
@php
    $setting = static fn (string $key, mixed $default = '') => $settings[$key] ?? $default;

    $enabled = static function (string $key, bool $default = false) use ($settings): int {
        if (!array_key_exists($key, $settings)) {
            return $default ? 1 : 0;
        }

        return in_array(
            strtolower(trim((string) $settings[$key])),
            ['1', 'true', 'yes', 'on'],
            true
        ) ? 1 : 0;
    };

    $transportCode = static function ($transport): int {
        return match (strtolower(trim((string) $transport))) {
            'tcp' => 1,
            'tls' => 3,
            default => 0,
        };
    };

    $fanvilKey = static function (array $key) use ($lines): array {
        $type = strtolower(trim((string) ($key['type'] ?? '')));
        $line = max(1, (int) ($key['line'] ?? 1));
        $value = (string) ($key['value'] ?? '');

        if ($type === 'line') {
            foreach ($lines as $lineNumber => $lineData) {
                if ($value !== '' && $value === (string) ($lineData['auth_id'] ?? '')) {
                    $line = (int) $lineNumber;
                    break;
                }
            }

            return [2, 'SIP' . $line];
        }

        if (in_array($type, ['', '3', 'unassigned', 'n/a'], true)) {
            return [0, ''];
        }

        if ($type === 'dtmf') {
            return [5, $value];
        }

        $typeCode = match ($type) {
            'speed_dial', 'speeddial' => 'f',
            'park' => 'c',
            'blf' => 'ba',
            'check_voicemail' => 'bc',
            default => $type,
        };

        return [1, $value === '' ? '' : "{$value}@{$line}/{$typeCode}"];
    };

@endphp
<?xml version="1.0" encoding="UTF-8"?>
<sysConf>
    <Version>2.0000000000</Version>
    <net>
        <WANTYPE>0</WANTYPE>
        <PrimaryDNS>{{ $setting('dns_server_primary', '8.8.8.8') }}</PrimaryDNS>
        <SecondaryDNS>{{ $setting('dns_server_secondary', '208.67.222.222') }}</SecondaryDNS>
        <EnableDHCP>1</EnableDHCP>
        <DHCPAutoDNS>1</DHCPAutoDNS>
        <DHCPAutoTime>1</DHCPAutoTime>
        <DHCPOption100-101>1</DHCPOption100-101>
        <UseVendorClassID>0</UseVendorClassID>
        <VendorClassID>Fanvil XU</VendorClassID>
        <EnablePPPoE>0</EnablePPPoE>
        <ARPCacheLife>2</ARPCacheLife>
        <MTU>1500</MTU>
        <EnableDHCP6>1</EnableDHCP6>
        <DHCP6AutoDNS>1</DHCP6AutoDNS>
        <UseVendor6ClassID>0</UseVendor6ClassID>
        <Vendor6ClassID>Fanvil XU</Vendor6ClassID>
        <wifi>
            <WIFIEnable>{{ $enabled('fanvil_wifi_enable') }}</WIFIEnable>
            <EnableDHCP>1</EnableDHCP>
            <DHCPAutoDNS>1</DHCPAutoDNS>
            <UseVendorClassID>0</UseVendorClassID>
            <VendorClassID>Fanvil XU</VendorClassID>
        </wifi>
        <NetGlobal>
            <NetPriority>10</NetPriority>
        </NetGlobal>
    </net>
    <mm>
        <G723BitRate>1</G723BitRate>
        <ILBCPayloadType>97</ILBCPayloadType>
        <ILBCPayloadLen>20</ILBCPayloadLen>
        <G726-16PayloadType>103</G726-16PayloadType>
        <G726-24PayloadType>104</G726-24PayloadType>
        <G726-32PayloadType>102</G726-32PayloadType>
        <G726-40PayloadType>105</G726-40PayloadType>
        <DtmfPayloadType>101</DtmfPayloadType>
        <OpusPayloadType>107</OpusPayloadType>
        <RTPInitialPort>10000</RTPInitialPort>
        <RTPPortQuantity>1000</RTPPortQuantity>
        <RTPKeepAlive>1</RTPKeepAlive>
        <SelectYourTone>{{ $setting('fanvil_country_toneset', 11) }}</SelectYourTone>
        <capability>
            <AudioCodecSets>OPUS,PCMU,PCMA,G726-16,G726-24,G726-32,G726-40,G729,G723,iLBC,G722</AudioCodecSets>
            <VideoCodecSets>{{ $setting('fanvil_video_codec', 'H264') }}</VideoCodecSets>
            <VideoFrameRate>25</VideoFrameRate>
            <VideoBitRate>2000000</VideoBitRate>
            <VideoResolution>4</VideoResolution>
            <VideoNegotiateDir>2</VideoNegotiateDir>
        </capability>
    </mm>
    <sip>
        <SIPPort>{{ $setting('sip_port', 5060) }}</SIPPort>
        <STUNServer>{{ $setting('fanvil_stun_server') }}</STUNServer>
        <STUNPort>{{ $setting('fanvil_stun_port', 3478) }}</STUNPort>
        <STUNRefreshTime>50</STUNRefreshTime>
        <RegFailInterval>32</RegFailInterval>
        <EnableRFC4475>1</EnableRFC4475>
        <NotifyReboot>1</NotifyReboot>
@foreach ($lines as $line)
@php
    $lineNumber = max(1, (int) ($line['line_number'] ?? $loop->iteration));
    $transport = strtolower(trim((string) ($line['sip_transport'] ?? 'udp')));
    $sipPort = (int) ($line['sip_port'] ?? ($transport === 'tls' ? 5061 : 5060));
    $registerExpires = (int) ($line['register_expires'] ?? 3600);
    $hasPassword = trim((string) ($line['password'] ?? '')) !== '';
    $hasBackup = trim((string) ($line['server_address_secondary'] ?? '')) !== '';
    $hasBackupProxy = trim((string) ($line['outbound_proxy_secondary'] ?? '')) !== '';
    $usesDnsSrv = in_array($transport, ['dns srv', 'dnssrv', 'dnsnaptr'], true);
@endphp
        <line index="{{ $lineNumber }}">
            <PhoneNumber>{{ $line['user_id'] ?? $line['auth_id'] ?? '' }}</PhoneNumber>
            <DisplayName>{{ $line['display_name'] ?? $line['auth_id'] ?? '' }}</DisplayName>
            <SipName>{{ $line['auth_id'] ?? '' }}</SipName>
            <RegisterAddr>{{ $line['server_address_primary'] ?? $line['server_address'] ?? $domain_name ?? '' }}</RegisterAddr>
            <RegisterPort>{{ $sipPort }}</RegisterPort>
            <RegisterUser>{{ $line['auth_id'] ?? '' }}</RegisterUser>
            <RegisterPswd>{{ $line['password'] ?? '' }}</RegisterPswd>
            <RegisterTTL>{{ $registerExpires }}</RegisterTTL>
            <NeedRegOn>0</NeedRegOn>
            <BackupAddr>{{ $line['server_address_secondary'] ?? '' }}</BackupAddr>
            <BackupPort>{{ $sipPort }}</BackupPort>
            <BackupTransport>{{ $transportCode($transport) }}</BackupTransport>
            <BackupTTL>{{ $registerExpires }}</BackupTTL>
            <BackupNeedRegOn>0</BackupNeedRegOn>
            <EnableReg>{{ $hasPassword ? 1 : 0 }}</EnableReg>
            <ProxyAddr>{{ $line['outbound_proxy_primary'] ?? '' }}</ProxyAddr>
            <ProxyPort>{{ $sipPort }}</ProxyPort>
            <ProxyUser>{{ $line['auth_id'] ?? '' }}</ProxyUser>
            <ProxyPswd>{{ $line['password'] ?? '' }}</ProxyPswd>
            <ProxyNeedRegOn>0</ProxyNeedRegOn>
            <BakProxyAddr>{{ $line['outbound_proxy_secondary'] ?? '' }}</BakProxyAddr>
            <BakProxyPort>{{ $sipPort }}</BakProxyPort>
            <BakProxyNeedRegOn>0</BakProxyNeedRegOn>
            <EnableFailback>{{ ($hasBackup || $hasBackupProxy) ? 1 : 0 }}</EnableFailback>
            <FailbackInterval>1800</FailbackInterval>
            <SignalRetryCounts>3</SignalRetryCounts>
            <MediaCrypto>0</MediaCrypto>
            <LocalDomain>{{ $setting('fanvil_realm', $domain_name ?? '') }}</LocalDomain>
            <AlwaysFWD>0</AlwaysFWD>
            <BusyFWD>0</BusyFWD>
            <NoAnswerFWD>0</NoAnswerFWD>
            <FWDTimer>5</FWDTimer>
            <PickupNum>{{ $setting('fanvil_pickup_num') }}</PickupNum>
            <RingType>{{ $setting("fanvil_ringtone_line{$lineNumber}", 'default') }}</RingType>
            <NATUDPUpdate>2</NATUDPUpdate>
            <UDPUpdateTTL>30</UDPUpdateTTL>
            <PRACK>0</PRACK>
            <SessionTimer>0</SessionTimer>
            <EnableRport>1</EnableRport>
            <Subscribe>1</Subscribe>
            <SubExpire>{{ $registerExpires }}</SubExpire>
            <DNSSRV>{{ $usesDnsSrv ? 1 : 0 }}</DNSSRV>
            <DNSMode>{{ $usesDnsSrv ? 1 : 0 }}</DNSMode>
            <Transport>{{ $transportCode($transport) }}</Transport>
            <MWINum>{{ $setting('voicemail_number') }}</MWINum>
            <VoiceCodecMap>OPUS,PCMU,PCMA,G726-32,G729,G723,iLBC,G722</VoiceCodecMap>
            <VideoCodecMap>{{ $setting('fanvil_video_codec') }}</VideoCodecMap>
            <CallerIdType>4</CallerIdType>
            <EnableSCA>{{ !empty($line['shared_line']) ? 1 : 0 }}</EnableSCA>
            <MissedCallLog>1</MissedCallLog>
        </line>
@endforeach
    </sip>
    <call>
        <port index="1">
            <EnableXferDPlan>1</EnableXferDPlan>
            <EnableFwdDPlan>1</EnableFwdDPlan>
            <EnableDND>1</EnableDND>
            <CallWaiting>1</CallWaiting>
            <CallTransfer>1</CallTransfer>
            <CallConference>1</CallConference>
            <EnableIntercom>1</EnableIntercom>
            <IntercomTone>1</IntercomTone>
            <DefaultAnsMode>{{ $setting('fanvil_default_answer_mode', 0) }}</DefaultAnsMode>
            <DefaultDialMode>{{ $setting('fanvil_default_dial_mode', 0) }}</DefaultDialMode>
            <EnablePreDial>1</EnablePreDial>
        </port>
        <basic>
            <DialbyPound>1</DialbyPound>
            <DialbyTimeout>1</DialbyTimeout>
            <DialTimeoutvalue>10</DialTimeoutvalue>
        </basic>
    </call>
    <phone>
        <MenuPassword>{{ $setting('fanvil_menu_password', 123) }}</MenuPassword>
        <EmergencyCall>{{ $setting('fanvil_emergency_number', 911) }}</EmergencyCall>
        <EnableCallHistory>1</EnableCallHistory>
        <LineDisplayFormat>$name</LineDisplayFormat>
        <display>
            <LCDTitle>{{ $setting('fanvil_greeting', 'FS PBX') }}</LCDTitle>
            <EnableEnergysaving>{{ $setting('fanvil_display_brightness_inactive', 4) }}</EnableEnergysaving>
            <LCDLuminanceLevel>{{ $setting('fanvil_display_brightness_active', 12) }}</LCDLuminanceLevel>
            <BacklightOffTime>{{ $setting('fanvil_display_inactivity_time', 45) }}</BacklightOffTime>
            <DefaultLanguage>{{ $setting('language', 'en') }}</DefaultLanguage>
        </display>
        <blfLed>
            <BLFIdleColor>0</BLFIdleColor>
            <BLFIdleCtl>1</BLFIdleCtl>
            <BLFIdleText>terminated</BLFIdleText>
            <BLFRingColor>1</BLFRingColor>
            <BLFRingCtl>2</BLFRingCtl>
            <BLFRingText>early</BLFRingText>
            <BLFTalkingColor>1</BLFTalkingColor>
            <BLFTalkingCtl>1</BLFTalkingCtl>
            <BLFTalkingText>confirmed</BLFTalkingText>
            <BLFFailedColor>2</BLFFailedColor>
            <BLFFailedText>failed</BLFFailedText>
            <BLFParkedColor>0</BLFParkedColor>
            <BLFParkedCtl>3</BLFParkedCtl>
            <BLFParkedText>parked</BLFParkedText>
        </blfLed>
        <volume>
            <RingType>{{ $setting('fanvil_default_ringtone', 'Type 2') }}</RingType>
        </volume>
        <date>
            <EnableSNTP>{{ $enabled('fanvil_enable_sntp', true) }}</EnableSNTP>
            <SNTPServer>{{ $setting('ntp_server_primary', 'pool.ntp.org') }}</SNTPServer>
            <SecondSNTPServer>{{ $setting('ntp_server_secondary') }}</SecondSNTPServer>
            <TimeZone>{{ $setting('fanvil_time_zone', 0) }}</TimeZone>
            <TimeZoneName>{{ $setting('fanvil_time_zone_name', 'UTC') }}</TimeZoneName>
            <Enable_DST>{{ $enabled('fanvil_enable_dst') }}</Enable_DST>
            <DST_Fixed_Type>{{ $setting('fanvil_dst_fixed_type', 0) }}</DST_Fixed_Type>
            <DSTLocation>{{ $setting('fanvil_location', 4) }}</DSTLocation>
            <DSTMinOffset>{{ $setting('fanvil_dst_minute_offset', 60) }}</DSTMinOffset>
        </date>
        <timeDisplay>
            <TimeDisplayStyle>{{ $setting('fanvil_time_display', 0) }}</TimeDisplayStyle>
            <DateDisplayStyle>{{ $setting('fanvil_date_display', 6) }}</DateDisplayStyle>
            <DateSeparator>{{ $setting('fanvil_date_separator', 0) }}</DateSeparator>
        </timeDisplay>
        <softkey>
            <SoftkeyMode>0</SoftkeyMode>
            <SoftKeyExitStyle>{{ $setting('fanvil_softkey_exit', 2) }}</SoftKeyExitStyle>
            <DesktopSoftkey>{{ $setting('fanvil_softkey_desktopsoftkey', 'history;contact;dnd;menu;') }}</DesktopSoftkey>
            <TalkingSoftkey>{{ $setting('fanvil_softkey_talkingsoftkey', 'hold;xfer;conf;end;') }}</TalkingSoftkey>
            <RingingSoftkey>{{ $setting('fanvil_softkey_ringingsoftkey', 'accept;none;forward;reject;') }}</RingingSoftkey>
            <DesktopClick>{{ $setting('fanvil_softkey_desktopclick', 'history;status;none;none;none;') }}</DesktopClick>
        </softkey>
    </phone>
    <dsskey>
        <SelectDsskeyAction>0</SelectDsskeyAction>
        <MemoryKeytoBXfer>3</MemoryKeytoBXfer>
@php
    $sideKeys = [];
    foreach (($side_keys ?? []) as $key) {
        $index = max(1, (int) ($key['id'] ?? count($sideKeys) + 1));
        $sideKeys[$index] = $key;
    }

    $sideKeyCount = max(5, $sideKeys === [] ? 0 : max(array_keys($sideKeys)));

    $functionKeys = [];
    foreach ($main_keys as $key) {
        $index = max(1, (int) ($key['id'] ?? count($functionKeys) + 1));
        $functionKeys[$index] = $key;
    }

    $functionKeysPerPage = max(1, (int) $setting('fanvil_function_keys_per_page', 6));
    $functionKeySlotsPerPage = max(12, $functionKeysPerPage);
    $functionKeyPageCount = max(
        5,
        $functionKeys === [] ? 0 : (int) ceil(max(array_keys($functionKeys)) / $functionKeysPerPage)
    );
@endphp
        <FuncKeyPageNum>{{ $functionKeyPageCount }}</FuncKeyPageNum>
        <SideKeyPageNum>1</SideKeyPageNum>
        <dssSide index="1">
@for ($slot = 1; $slot <= $sideKeyCount; $slot++)
@if (isset($sideKeys[$slot]))
@php
    $key = $sideKeys[$slot];
    [$keyType, $keyValue] = $fanvilKey($key);
@endphp
            <Fkey index="{{ $slot }}">
                <Type>{{ $keyType }}</Type>
                <Value>{{ $keyValue }}</Value>
                <Title>{{ $keyType === 0 ? '' : ($key['label'] ?? '') }}</Title>
            </Fkey>
@else
            <Fkey index="{{ $slot }}">
                <Type>0</Type>
                <Value></Value>
                <Title></Title>
            </Fkey>
@endif
@endfor
        </dssSide>
@for ($page = 1; $page <= $functionKeyPageCount; $page++)
        <internal index="{{ $page }}">
@for ($slot = 1; $slot <= $functionKeySlotsPerPage; $slot++)
@php
    $keyIndex = $slot <= $functionKeysPerPage
        ? (($page - 1) * $functionKeysPerPage) + $slot
        : null;
    $key = $keyIndex === null ? null : ($functionKeys[$keyIndex] ?? null);
    [$keyType, $keyValue] = $key ? $fanvilKey($key) : [0, ''];
@endphp
            <Fkey index="{{ $slot }}">
                <Type>{{ $keyType }}</Type>
                <Value>{{ $keyValue }}</Value>
                <Title>{{ $keyType === 0 ? '' : ($key['label'] ?? '') }}</Title>
            </Fkey>
@endfor
        </internal>
@endfor
    </dsskey>
    <web>
        <WebServerType>0</WebServerType>
        <WebPort>80</WebPort>
        <HttpsWebPort>443</HttpsWebPort>
        <RemoteControl>1</RemoteControl>
        <EnableTelnet>0</EnableTelnet>
        <account index="1">
            <Name>{{ $setting('admin_name', $setting('http_auth_username', 'admin')) }}</Name>
            <Password>{{ $setting('admin_password', $setting('http_auth_password', 'admin')) }}</Password>
            <Level>10</Level>
        </account>
    </web>
    <log>
        <Level>INFO</Level>
        <OutputDevice>{{ $enabled('fanvil_syslog_enable') ? ',syslog' : 'stdout' }}</OutputDevice>
        <SyslogServer>{{ $setting('fanvil_syslog_server', '0.0.0.0') }}</SyslogServer>
        <SyslogServerPort>{{ $setting('fanvil_syslog_server_port', 514) }}</SyslogServerPort>
    </log>
    <ap>
        <DefaultUsername>{{ $setting('http_auth_username') }}</DefaultUsername>
        <DefaultPassword>{{ $setting('http_auth_password') }}</DefaultPassword>
        <DownloadCommonConf>1</DownloadCommonConf>
        <SaveProvisionInfo>1</SaveProvisionInfo>
        <CheckFailTimes>5</CheckFailTimes>
        <FlashServerIP>{{ $setting('fanvil_provision_url', $setting('provision_base_url')) }}</FlashServerIP>
        <FlashFileName>{{ $setting('fanvil_firmware_config') }}</FlashFileName>
        <FlashProtocol>5</FlashProtocol>
        <FlashMode>1</FlashMode>
        <FlashInterval>1</FlashInterval>
        <pnp>
            <PNPEnable>1</PNPEnable>
            <PNPIP>224.0.1.75</PNPIP>
            <PNPPort>5060</PNPPort>
            <PNPTransport>0</PNPTransport>
        </pnp>
        <opt>
            <DHCPOption>66</DHCPOption>
        </opt>
    </ap>
    <fwCheck>
        <EnableAutoUpgrade>{{ $enabled('fanvil_enable_auto_upgrade') }}</EnableAutoUpgrade>
        <UpgradeServer1>{{ $setting('fanvil_firmware_upgrade_server_1') }}</UpgradeServer1>
        <UpgradeServer2>{{ $setting('fanvil_firmware_upgrade_server_2') }}</UpgradeServer2>
        <AutoUpgradeInterval>{{ $setting('fanvil_firmware_upgrade_interval', 24) }}</AutoUpgradeInterval>
    </fwCheck>
    <qos>
        <EnableVLAN>{{ $enabled('fanvil_enable_vlan') }}</EnableVLAN>
        <VLANID>{{ $setting('fanvil_lan_port_vlan', 256) }}</VLANID>
        <EnablePVID>{{ $setting('fanvil_pc_port_vlan') !== '' ? 2 : 0 }}</EnablePVID>
        <PVIDValue>{{ $setting('fanvil_pc_port_vlan', 254) }}</PVIDValue>
        <SignallingPriority>{{ $setting('fanvil_qos_sip', 0) }}</SignallingPriority>
        <VoicePriority>{{ $setting('fanvil_qos_rtp_voice', 0) }}</VoicePriority>
        <VideoPriority>{{ $setting('fanvil_qos_rtp_video', 0) }}</VideoPriority>
        <EnablediffServ>{{ $enabled('fanvil_enable_diffserv') }}</EnablediffServ>
        <SingallingDSCP>{{ $setting('fanvil_dscp_sip', 46) }}</SingallingDSCP>
        <VoiceDSCP>{{ $setting('fanvil_dscp_rtp_voice', 46) }}</VoiceDSCP>
        <VideoDSCP>{{ $setting('fanvil_dscp_rtp_video', 34) }}</VideoDSCP>
        <LLDPTransmit>{{ $enabled('fanvil_lldp_tx_enable') }}</LLDPTransmit>
        <LLDPRefreshTime>{{ $setting('fanvil_lldp_refresh', 60) }}</LLDPRefreshTime>
        <LLDPLearnPolicy>{{ $setting('fanvil_lldp_learn', 0) }}</LLDPLearnPolicy>
    </qos>
</sysConf>
@break

@endswitch
