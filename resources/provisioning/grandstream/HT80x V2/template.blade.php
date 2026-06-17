{{-- version: 1.0.4 --}}

@switch($flavor)

{{-- ================= Grandstream HT80x V2 cfgmac.xml ================= --}}
@case('cfgmac.xml')
@php
    $transportMap = ['tcp' => '1', 'tls' => '2', 'tls or tcp' => '2'];
@endphp
<?xml version="1.0" encoding="UTF-8" ?>
<!-- Grandstream HT80x V2 XML provisioning configuration -->
<gs_provision version="1">
  <config version="1">

    <!-- ###################################################################### -->
    <!-- ## System Settings / Basic Settings                                  ## -->
    <!-- ###################################################################### -->

    <!-- Enable Voice Prompt. 0 - Yes, 1 - No. Default is 0. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P253>0</P253>

    <!-- Enable Direct IP Call. 0 - Yes, 1 - No. Default is 0. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P277>0</P277>

    <!-- Lock Keypad Update. 0 - No, 1 - Yes, 2 - Reset button ISP data reset only. Default is 0. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P88>0</P88>

    <!-- STUN Server. -->
    <!-- Server address -->
    <P76></P76>

    <!-- Keep-Alive Interval, in seconds. Default is 20. -->
    <!-- Number: 10 to 160 -->
    <!-- Mandatory -->
    <P84>20</P84>

    <!-- Use STUN to detect network connectivity. 0 - No, 1 - Yes. Default is 0. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P474>0</P474>

    <!-- ###################################################################### -->
    <!-- ## System Settings / Security Settings / User Info Management        ## -->
    <!-- ###################################################################### -->

    <!-- New Admin Password. Uses the same admin_password setting as other Grandstream templates. -->
    <!-- String: 4 to 30 characters -->
    <!-- Mandatory -->
    @if (array_key_exists('admin_password', $settings))
    <P2>{{ $settings['admin_password'] }}</P2>
    @endif

    <!-- End User Password. Uses the same user_password setting as other Grandstream templates. -->
    <!-- String: 4 to 30 characters -->
    @if (array_key_exists('user_password', $settings))
    <P196>{{ $settings['user_password'] }}</P196>
    @endif

    <!-- ###################################################################### -->
    <!-- ## System Settings / Time and Language                               ## -->
    <!-- ###################################################################### -->

    <!-- NTP Server. -->
    <!-- Server address -->
    <P30>{{ $settings['ntp_server_primary'] ?? 'pool.ntp.org' }}</P30>

    <!-- Secondary NTP Server. -->
    <!-- Server address -->
    <P8333>{{ $settings['ntp_server_secondary'] ?? '' }}</P8333>

    <!-- Allow DHCP Option 42 to Override NTP Server. 0 - No, 1 - Yes. Default is 1. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P144>1</P144>

    <!-- Time Zone. Grandstream accepts named timezone values such as auto/customize or POSIX timezone strings. -->
    <!-- String -->
    <!-- Mandatory -->
    <P64>{{ $settings['grandstream_time_zone'] ?? 'auto' }}</P64>

    <!-- Self-Defined Time Zone. Used when P64 is customize. -->
    <!-- String -->
    <P246>MTZ+6MDT+5,M3.2.0,M11.1.0</P246>

    <!-- Allow DHCP Option 2 to Override Time Zone Setting. 0 - No, 1 - Yes. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P143>1</P143>

    <!-- ###################################################################### -->
    <!-- ## Maintenance / Upgrade / Firmware                                  ## -->
    <!-- ###################################################################### -->

    <!-- Firmware Upgrade via. 0 - TFTP, 1 - HTTP, 2 - HTTPS, 3 - FTP, 4 - FTPS. Default is 2. -->
    <!-- Number: 0, 1, 2, 3, 4 -->
    <!-- Mandatory -->
    <P6767>{{ $settings['grandstream_firmware_upgrade_protocol'] ?? '2' }}</P6767>

    <!-- Firmware Server Path. Default is fm.grandstream.com/gs. -->
    <!-- Server address -->
    <P192>{{ trim(str_replace(['http://', 'https://'], '', $settings['grandstream_firmware_path'] ?? 'fm.grandstream.com/gs'), " /") }}</P192>

    <!-- Firmware File Prefix. -->
    <!-- String: max length 32 characters -->
    <P232></P232>

    <!-- Firmware File Postfix. -->
    <!-- String: max length 32 characters -->
    <P233></P233>

    <!-- ###################################################################### -->
    <!-- ## Maintenance / Upgrade / Config File                               ## -->
    <!-- ###################################################################### -->

    <!-- Config Upgrade via. 0 - TFTP, 1 - HTTP, 2 - HTTPS, 3 - FTP, 4 - FTPS. Default is 2. -->
    <!-- Number: 0, 1, 2, 3, 4 -->
    <!-- Mandatory -->
    <P212>2</P212>

    <!-- Config Server Path. Default is fm.grandstream.com/gs. -->
    <!-- Server address -->
    <P237>{{ trim(str_replace(['http://', 'https://'], '', $settings['provision_base_url'] ?? ''), " /") }}</P237>

    <!-- XML Config File Password. Used only when encrypted XML config files are provided. -->
    <!-- String: max length 40 characters -->
    <!-- Mandatory -->
    <P1359></P1359>

    <!-- Config File Prefix. -->
    <!-- String: max length 32 characters -->
    <P234></P234>

    <!-- Config File Postfix. -->
    <!-- String: max length 32 characters -->
    <P235></P235>

    <!-- Authenticate Config File. 0 - No, 1 - Yes. Default is 0. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P240>0</P240>

    <!-- Allow DHCP Option 43, 66, or 160 to Override Server. 0 - No, 1 - Yes. Default is 1. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory; reboot required -->
    <P145>0</P145>

    <!-- Auto Provision. 0 - No, 1 - Yes. Default is 1. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P1414>1</P1414>

    <!-- Additional DHCP Option. 0 - None, 1 - Option 150. Default is 0. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P8337>0</P8337>

    <!-- Automatic Upgrade. 0 - No, 1 - Every interval, 2 - Every day, 3 - Every week. Default is 0. -->
    <!-- Number: 0, 1, 2, 3 -->
    <!-- Mandatory -->
    <P22296>{{ $settings['grandstream_automatic_provisioning'] ?? '0' }}</P22296>

    <!-- Automatic Upgrade: Every X minutes. Default is 10080 minutes. -->
    <!-- Number: 30 to 5256000 -->
    <!-- Mandatory -->
    <P193>10080</P193>

    <!-- Automatic Upgrade: Daily start hour. Default is 1. -->
    <!-- Number: 0 to 23 -->
    <!-- Mandatory -->
    <P285>1</P285>

    <!-- Automatic Upgrade: Daily end hour. Default is 22. -->
    <!-- Number: 0 to 23 -->
    <!-- Mandatory -->
    <P8459>22</P8459>

    <!-- Automatic Upgrade: Weekly day. 0 - Sunday through 6 - Saturday. Default is 1. -->
    <!-- Number: 0 to 6 -->
    <!-- Mandatory -->
    <P286>1</P286>

    <!-- Firmware Upgrade and Provisioning. 0 - Always check at boot, 1 - Check only on prefix/suffix change, 2 - Always skip firmware check. Default is 0. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P238>0</P238>

    <!-- Download and Process All Available Config Files. 0 - No, 1 - Yes. Default is 0. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P8467>0</P8467>

    <!-- Config Provision Order. Default is cfg$mac.xml;cfg$mac;cfg$product.xml;cfg.xml. -->
    <!-- String: semicolon separated config file tokens -->
    <P8501>cfg$mac.xml;cfg$mac;cfg$product.xml;cfg.xml</P8501>

    <!-- Configuration File Types Allowed. 47 - All, 46 - XML only. Default is 47. -->
    <!-- Number: 46, 47 -->
    <!-- Mandatory -->
    <P8601>47</P8601>

    <!-- ###################################################################### -->
    <!-- ## Maintenance / System Diagnosis / Syslog                            ## -->
    <!-- ###################################################################### -->

    <!-- Syslog Server. -->
    <!-- String: server address, max length 64 characters -->
    <P207>{{ $settings['grandstream_syslog_server'] ?? '' }}</P207>

    <!-- Syslog Level. 0 - NONE, 1 - DEBUG, 2 - INFO, 3 - WARNING, 4 - ERROR, 5 - EXTRA DEBUG. Default is 0. -->
    <!-- Number: 0, 1, 2, 3, 4, 5 -->
    <!-- Mandatory -->
    <P208>{{ $settings['grandstream_syslog_level'] ?? '0' }}</P208>

    <!-- Send SIP Log. 0 - No, 1 - Yes. Default is 0. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P1387>{{ $settings['grandstream_send_sip_log'] ?? '0' }}</P1387>

@foreach ($lines as $line)
    @php
        $n = (int)($line['line_number'] ?? 0);
        $sipTransport = $transportMap[strtolower((string) ($line['sip_transport'] ?? 'tcp'))] ?? '1';
        $srtpMode = ($line['sip_transport'] ?? 'TCP') === 'Tls Or Tcp' ? '1' : '0';
    @endphp
    @continue($n <= 0 || $n > 2)

    @if ($n === 1)
    <!-- ###################################################################### -->
    <!-- ## Port Settings / FXS Port 1 / Account Registration                  ## -->
    <!-- ###################################################################### -->

    <!-- Account Active. 0 - No, 1 - Yes. Default is 0. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P271>1</P271>

    <!-- Primary SIP Server. Uses line server_address. -->
    <!-- Server address -->
    <P47>{{ $line['server_address'] ?? '' }}</P47>

    <!-- Failover SIP Server. -->
    <!-- Server address -->
    <P967></P967>

    <!-- Prefer Primary SIP Server. 0 - No, 1 - Use primary when failover registration expires, 2 - Use primary when it responds. Default is 0. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P4567>0</P4567>

    <!-- Outbound Proxy. Uses line outbound_proxy_primary and sip_port. -->
    <!-- Server address -->
    <P48>{{ $line['outbound_proxy_primary'] ?? '' }}:{{ $line['sip_port'] ?? '' }}</P48>

    <!-- Backup Outbound Proxy. -->
    <!-- Server address -->
    <P2333></P2333>

    <!-- Prefer Primary Outbound Proxy. 0 - No, 1 - Yes. Default is 0. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P28096>0</P28096>

    <!-- From Domain. Uses line server_address. -->
    <!-- String -->
    <P8617>{{ $line['server_address'] ?? '' }}</P8617>

    <!-- Allow DHCP Option 120 to Override SIP Server. 0 - No, 1 - Yes. Default is 0. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory; reboot required -->
    <P1411>0</P1411>

    <!-- SIP User ID. Uses line auth_id. -->
    <!-- String -->
    <P35>{{ $line['auth_id'] ?? '' }}</P35>

    <!-- SIP Authentication ID. Uses line auth_id. -->
    <!-- String -->
    <P36>{{ $line['auth_id'] ?? '' }}</P36>

    <!-- SIP Authentication Password. Uses line password. -->
    <!-- String -->
    <P34>{{ $line['password'] ?? '' }}</P34>

    <!-- Name. Uses line display_name, falling back to auth_id. -->
    <!-- String -->
    <P3>{{ $line['display_name'] ?? $line['auth_id'] }}</P3>

    <!-- Tel URI. 0 - Disabled, 1 - User=Phone, 2 - Enabled. Default is 0. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P63>0</P63>

    <!-- Layer 3 QoS SIP DSCP. Default is 26. -->
    <!-- Number: 0 to 63 -->
    <!-- Mandatory -->
    <P5046>26</P5046>

    <!-- Layer 3 QoS RTP DSCP. Default is 46. -->
    <!-- Number: 0 to 63 -->
    <!-- Mandatory -->
    <P5050>46</P5050>

    <!-- DNS Mode. 0 - A Record, 1 - SRV, 2 - NAPTR/SRV, 3 - Use Configured IP. Default is 0. -->
    <!-- Number: 0, 1, 2, 3 -->
    <!-- Mandatory -->
    <P103>{{ $settings['grandstream_dns_mode'] ?? '0' }}</P103>

    <!-- NAT Traversal. 0 - No, 1 - STUN, 2 - Keep-Alive, 3 - UPnP, 4 - Auto, 5 - VPN. Default is 0. -->
    <!-- Number: 0, 1, 2, 3, 4, 5 -->
    <!-- Mandatory -->
    <P52>2</P52>

    <!-- SIP Registration. 0 - No, 1 - Yes. Default is 1. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P31>1</P31>

    <!-- SIP Transport. 0 - UDP, 1 - TCP, 2 - TLS. Default is 0. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P130>{{ $sipTransport }}</P130>

    <!-- Unregister On Reboot. 0 - No, 1 - All, 2 - Instance. Default is 0. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P81>0</P81>

    <!-- Outgoing Call Without Registration. 0 - No, 1 - Yes. Default is 1. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P109>1</P109>

    <!-- Register Expiration, in minutes. FS PBX stores line register_expires in seconds. Default is 60. -->
    <!-- Number: 1 to 64800 -->
    <!-- Mandatory -->
    <P32>{{ ($line['register_expires'] ?? 3600) / 60 }}</P32>

    <!-- SIP Registration Failure Retry Wait Time, in seconds. Default is 20. -->
    <!-- Number: 1 to 3600 -->
    <!-- Mandatory -->
    <P138>20</P138>

    <!-- Enable SIP OPTIONS/NOTIFY Keep Alive. 0 - No, 1 - OPTIONS, 2 - NOTIFY. Default is 0. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P2397>1</P2397>

    <!-- SIP OPTIONS/NOTIFY Keep Alive Interval, in seconds. Default is 30. -->
    <!-- Number: 1 to 64800 -->
    <!-- Mandatory -->
    <P2398>30</P2398>

    <!-- SIP OPTIONS/NOTIFY Keep Alive Max Lost. Default is 3. -->
    <!-- Number: 3 to 10 -->
    <!-- Mandatory -->
    <P2399>3</P2399>

    <!-- SUBSCRIBE for MWI. 0 - No, 1 - Yes. Default is 0. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P99>1</P99>

    <!-- Local SIP Port. Default is 5060. -->
    <!-- Number: 1 to 65535 -->
    <!-- Mandatory -->
    <P40>5060</P40>

    <!-- Use Random SIP Port. 0 - No, 1 - Yes. Default is 1. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P20501>1</P20501>

    <!-- SRTP Mode. 0 - Disabled, 1 - Enabled but not forced, 2 - Enabled and forced, 4 - Follow SIP Transport. Default is 0. -->
    <!-- Number: 0, 1, 2, 4 -->
    <!-- Mandatory -->
    <P183>{{ $srtpMode }}</P183>

    <!-- SRTP Key Length. 0 - AES 128 and 256 bit, 1 - AES 128 bit, 2 - AES 256 bit. Default is 0. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P2383>0</P2383>

    <!-- Offhook Auto-Dial. -->
    <!-- String: 0-9, *, # -->
    <P71></P71>

    <!-- Offhook Auto-Dial Delay, in seconds. Default is 0. -->
    <!-- Number: 0 to 60 -->
    <P4045>0</P4045>

    <!-- Dial Plan Prefix. This prefix string is added to each dialed number. -->
    <!-- String -->
    <P66></P66>

    <!-- Dial Plan. -->
    <!-- String: max length 1024 -->
    <!-- Mandatory -->
    <P4200>{{ $settings['grandstream_dial_plan'] ?? '{ x+ | \\+x+ | *x+ | *xx*x+ }' }}</P4200>

    <!-- Enable Local Call Features. 0 - No, 1 - Yes, 2 - Enable All. Default is 1. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P191>0</P191>
    @elseif ($n === 2)
    <!-- ###################################################################### -->
    <!-- ## Port Settings / FXS Port 2 / Account Registration                  ## -->
    <!-- ###################################################################### -->

    <!-- Account Active. 0 - No, 1 - Yes. Default is 0. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P401>1</P401>

    <!-- Primary SIP Server. Uses line server_address. -->
    <!-- Server address -->
    <P747>{{ $line['server_address'] ?? '' }}</P747>

    <!-- Failover SIP Server. -->
    <!-- Server address -->
    <P987></P987>

    <!-- Prefer Primary SIP Server. 0 - No, 1 - Use primary when failover registration expires, 2 - Use primary when it responds. Default is 0. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P4568>0</P4568>

    <!-- Outbound Proxy. Uses line outbound_proxy_primary and sip_port. -->
    <!-- Server address -->
    <P748>{{ $line['outbound_proxy_primary'] ?? '' }}:{{ $line['sip_port'] ?? '' }}</P748>

    <!-- Backup Outbound Proxy. -->
    <!-- Server address -->
    <P2433></P2433>

    <!-- Prefer Primary Outbound Proxy. 0 - No, 1 - Yes. Default is 0. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P28097>0</P28097>

    <!-- From Domain. Uses line server_address. -->
    <!-- String -->
    <P8618>{{ $line['server_address'] ?? '' }}</P8618>

    <!-- SIP User ID. Uses line auth_id. -->
    <!-- String -->
    <P735>{{ $line['auth_id'] ?? '' }}</P735>

    <!-- SIP Authentication ID. Uses line auth_id. -->
    <!-- String -->
    <P736>{{ $line['auth_id'] ?? '' }}</P736>

    <!-- SIP Authentication Password. Uses line password. -->
    <!-- String -->
    <P734>{{ $line['password'] ?? '' }}</P734>

    <!-- Name. Uses line display_name, falling back to auth_id. -->
    <!-- String -->
    <P703>{{ $line['display_name'] ?? $line['auth_id'] }}</P703>

    <!-- Tel URI. 0 - Disabled, 1 - User=Phone, 2 - Enabled. Default is 0. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P763>0</P763>

    <!-- Layer 3 QoS SIP DSCP. Default is 26. -->
    <!-- Number: 0 to 63 -->
    <!-- Mandatory -->
    <P5047>26</P5047>

    <!-- Layer 3 QoS RTP DSCP. Default is 46. -->
    <!-- Number: 0 to 63 -->
    <!-- Mandatory -->
    <P5051>46</P5051>

    <!-- DNS Mode. 0 - A Record, 1 - SRV, 2 - NAPTR/SRV, 3 - Use Configured IP. Default is 0. -->
    <!-- Number: 0, 1, 2, 3 -->
    <!-- Mandatory -->
    <P702>{{ $settings['grandstream_dns_mode'] ?? '0' }}</P702>

    <!-- NAT Traversal. 0 - No, 1 - STUN, 2 - Keep-Alive, 3 - UPnP, 4 - Auto, 5 - VPN. Default is 0. -->
    <!-- Number: 0, 1, 2, 3, 4, 5 -->
    <!-- Mandatory -->
    <P730>2</P730>

    <!-- SIP Registration. 0 - No, 1 - Yes. Default is 1. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P731>1</P731>

    <!-- SIP Transport. 0 - UDP, 1 - TCP, 2 - TLS. Default is 0. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P830>{{ $sipTransport }}</P830>

    <!-- Unregister On Reboot. 0 - No, 1 - All, 2 - Instance. Default is 0. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P752>0</P752>

    <!-- Outgoing Call Without Registration. 0 - No, 1 - Yes. Default is 1. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P813>1</P813>

    <!-- Register Expiration, in minutes. FS PBX stores line register_expires in seconds. Default is 60. -->
    <!-- Number: 1 to 64800 -->
    <!-- Mandatory -->
    <P732>{{ ($line['register_expires'] ?? 3600) / 60 }}</P732>

    <!-- SIP Registration Failure Retry Wait Time, in seconds. Default is 20. -->
    <!-- Number: 1 to 3600 -->
    <!-- Mandatory -->
    <P471>20</P471>

    <!-- Enable SIP OPTIONS/NOTIFY Keep Alive. 0 - No, 1 - OPTIONS, 2 - NOTIFY. Default is 0. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P2497>1</P2497>

    <!-- SIP OPTIONS/NOTIFY Keep Alive Interval, in seconds. Default is 30. -->
    <!-- Number: 1 to 64800 -->
    <!-- Mandatory -->
    <P2498>30</P2498>

    <!-- SIP OPTIONS/NOTIFY Keep Alive Max Lost. Default is 3. -->
    <!-- Number: 3 to 10 -->
    <!-- Mandatory -->
    <P2499>3</P2499>

    <!-- SUBSCRIBE for MWI. 0 - No, 1 - Yes. Default is 0. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P709>1</P709>

    <!-- Local SIP Port. Default is 6060. -->
    <!-- Number: 1 to 65535 -->
    <!-- Mandatory -->
    <P740>6060</P740>

    <!-- Use Random SIP Port. 0 - No, 1 - Yes. Default is 1. -->
    <!-- Number: 0, 1 -->
    <!-- Mandatory -->
    <P20502>1</P20502>

    <!-- SRTP Mode. 0 - Disabled, 1 - Enabled but not forced, 2 - Enabled and forced, 4 - Follow SIP Transport. Default is 0. -->
    <!-- Number: 0, 1, 2, 4 -->
    <!-- Mandatory -->
    <P443>{{ $srtpMode }}</P443>

    <!-- SRTP Key Length. 0 - AES 128 and 256 bit, 1 - AES 128 bit, 2 - AES 256 bit. Default is 0. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P2483>0</P2483>

    <!-- Offhook Auto-Dial. -->
    <!-- String: 0-9, *, # -->
    <P771></P771>

    <!-- Offhook Auto-Dial Delay, in seconds. Default is 0. -->
    <!-- Number: 0 to 60 -->
    <P4046>0</P4046>

    <!-- Dial Plan Prefix. This prefix string is added to each dialed number. -->
    <!-- String -->
    <P766></P766>

    <!-- Dial Plan. -->
    <!-- String: max length 1024 -->
    <P4201>{{ $settings['grandstream_dial_plan'] ?? '{ x+ | \\+x+ | *x+ | *xx*x+ }' }}</P4201>

    <!-- Enable Local Call Features. 0 - No, 1 - Yes, 2 - Enable All. Default is 1. -->
    <!-- Number: 0, 1, 2 -->
    <!-- Mandatory -->
    <P751>0</P751>
    @endif
@endforeach

  </config>
</gs_provision>
@break

@endswitch
