{{-- version: 1.0.0 --}}

@switch($flavor)

{{-- ================= Grandstream GXP17xx cfgmac.xml ================= --}}
@case('cfgmac.xml')
@php
    $transportMap = ['udp' => '0', 'tcp' => '1', 'tls' => '2', 'tls or tcp' => '2'];
    $dialPlan = $settings['grandstream_dial_plan'] ?? '{ x+ | \+x+ | *x+ | *xx*x+ }';

    $lineByNumber = collect($lines)->keyBy(fn ($line) => (int) ($line['line_number'] ?? 0));

    $accountCodes = [
        1 => ['active' => 'P271', 'account' => 'P270', 'server' => 'P47',  'outbound' => 'P48',  'blf' => 'P2375', 'user' => 'P35',  'auth' => 'P36',  'password' => 'P34',  'name' => 'P3',   'voicemail' => 'P33',  'dns' => 'P103', 'nat' => 'P52',  'register' => 'P31',  'expires' => 'P32',  'subscribe' => 'P26051', 'keepAlive' => 'P2397', 'keepInterval' => 'P2398', 'keepMaxLost' => 'P2399', 'localPort' => 'P40',  'retry' => 'P138', 'transport' => 'P130', 'mwi' => 'P99',  'srtp' => 'P183', 'dialPlan' => 'P290', 'features' => 'P191', 'xGrandstream' => 'P26054', 'pani' => 'P26058', 'pei' => 'P26059'],
        2 => ['active' => 'P401', 'account' => 'P417', 'server' => 'P402', 'outbound' => 'P403', 'blf' => 'P2475', 'user' => 'P404', 'auth' => 'P405', 'password' => 'P406', 'name' => 'P407', 'voicemail' => 'P426', 'dns' => 'P408', 'nat' => 'P414', 'register' => 'P410', 'expires' => 'P412', 'subscribe' => 'P26151', 'keepAlive' => 'P2497', 'keepInterval' => 'P2498', 'keepMaxLost' => 'P2499', 'localPort' => 'P413', 'retry' => 'P471', 'transport' => 'P448', 'mwi' => 'P415', 'srtp' => 'P443', 'dialPlan' => 'P459', 'features' => 'P420', 'xGrandstream' => 'P26154', 'pani' => 'P26158', 'pei' => 'P26159'],
        3 => ['active' => 'P501', 'account' => 'P517', 'server' => 'P502', 'outbound' => 'P503', 'blf' => 'P2575', 'user' => 'P504', 'auth' => 'P505', 'password' => 'P506', 'name' => 'P507', 'voicemail' => 'P526', 'dns' => 'P508', 'nat' => 'P514', 'register' => 'P510', 'expires' => 'P512', 'subscribe' => 'P26251', 'keepAlive' => 'P2597', 'keepInterval' => 'P2598', 'keepMaxLost' => 'P2599', 'localPort' => 'P513', 'retry' => 'P571', 'transport' => 'P548', 'mwi' => 'P515', 'srtp' => 'P543', 'dialPlan' => 'P559', 'features' => 'P520', 'xGrandstream' => 'P26254', 'pani' => 'P26258', 'pei' => 'P26259'],
        4 => ['active' => 'P601', 'account' => 'P617', 'server' => 'P602', 'outbound' => 'P603', 'blf' => 'P2675', 'user' => 'P604', 'auth' => 'P605', 'password' => 'P606', 'name' => 'P607', 'voicemail' => 'P626', 'dns' => 'P608', 'nat' => 'P614', 'register' => 'P610', 'expires' => 'P612', 'subscribe' => 'P26351', 'keepAlive' => 'P2697', 'keepInterval' => 'P2698', 'keepMaxLost' => 'P2699', 'localPort' => 'P613', 'retry' => 'P671', 'transport' => 'P648', 'mwi' => 'P615', 'srtp' => 'P643', 'dialPlan' => 'P659', 'features' => 'P620', 'xGrandstream' => 'P26354', 'pani' => 'P26358', 'pei' => 'P26359'],
    ];

    $vpkModeMap = [
        'none' => -1,
        'line' => 0,
        'sharedline' => 1,
        'speed dial' => 10,
        'blf' => 11,
        'presence watcher' => 12,
        'eventlist blf' => 13,
        'speed dial via active account' => 14,
        'dial dtmf' => 15,
        'voicemail' => 16,
        'transfer' => 18,
        'call park' => 19,
        'intercom' => 20,
        'monitored call park' => 26,
    ];

    $vpkCodes = [
        1 => ['mode' => 'P1363', 'account' => 'P1364', 'label' => 'P1465', 'value' => 'P1466'],
        2 => ['mode' => 'P1365', 'account' => 'P1366', 'label' => 'P1467', 'value' => 'P1468'],
        3 => ['mode' => 'P1367', 'account' => 'P1368', 'label' => 'P1469', 'value' => 'P1470'],
        4 => ['mode' => 'P1369', 'account' => 'P1370', 'label' => 'P1471', 'value' => 'P1472'],
        5 => ['mode' => 'P1371', 'account' => 'P1372', 'label' => 'P1473', 'value' => 'P1474'],
        6 => ['mode' => 'P1373', 'account' => 'P1374', 'label' => 'P1475', 'value' => 'P1476'],
    ];

    for ($slot = 7; $slot <= 32; $slot++) {
        $base = 23800 + (($slot - 7) * 4);
        $vpkCodes[$slot] = ['mode' => 'P'.$base, 'account' => 'P'.($base + 1), 'label' => 'P'.($base + 2), 'value' => 'P'.($base + 3)];
    }

    $mainKeyById = collect($main_keys)->keyBy(fn ($key) => (int) ($key['id'] ?? 0));
@endphp
<?xml version="1.0" encoding="UTF-8" ?>
<!-- Grandstream GXP1782/1780/1760 XML provisioning configuration -->
<gs_provision version="1">
  <config version="1">

    <!-- System / Security -->
    @if (array_key_exists('admin_password', $settings))
    <P2>{{ $settings['admin_password'] }}</P2>
    @endif
    @if (array_key_exists('user_password', $settings))
    <P196>{{ $settings['user_password'] }}</P196>
    @endif

    <!-- System / Time and Language -->
    <P30>{{ $settings['ntp_server_primary'] ?? 'pool.ntp.org' }}</P30>
    <P8333>{{ $settings['ntp_server_secondary'] ?? '' }}</P8333>
    <P64>{{ $settings['grandstream_time_zone'] ?? 'auto' }}</P64>
    <P246>MTZ+6MDT+5,M3.2.0,M11.1.0</P246>

    <!-- Maintenance / Upgrade and Provisioning -->
    <P238>0</P238>
    <P145>0</P145>
    <P1411>0</P1411>
    <P1414>1</P1414>
    <P194>{{ $settings['grandstream_automatic_provisioning'] ?? '0' }}</P194>
    <P193>10080</P193>
    <P285>1</P285>
    <P286>1</P286>
    <P212>2</P212>
    <P237>{{ trim(str_replace(['http://', 'https://'], '', $settings['provision_base_url'] ?? ''), " /") }}</P237>
    <P1360>{{ $settings['http_auth_username'] ?? '' }}</P1360>
    <P1361>{{ $settings['http_auth_password'] ?? '' }}</P1361>
    <P240>0</P240>
    <P6767>{{ $settings['grandstream_firmware_upgrade_protocol'] ?? '2' }}</P6767>
    <P192>{{ trim(str_replace(['http://', 'https://'], '', $settings['grandstream_firmware_path'] ?? 'fm.grandstream.com/gs'), " /") }}</P192>

    <!-- Maintenance / Syslog -->
    <P207>{{ $settings['grandstream_syslog_server'] ?? '' }}</P207>
    <P208>{{ $settings['grandstream_syslog_level'] ?? '0' }}</P208>

    <!-- Settings / Tones -->
    <P2909>f1=540,f2=516,c=70/16-55/16-70/300;</P2909>

@foreach ($accountCodes as $n => $codes)
    @php
        $line = $lineByNumber->get($n);
    @endphp

    @if (!$line)
    <!-- Account {{ $n }} disabled because no FS PBX line is assigned. -->
    <{{ $codes['active'] }}>0</{{ $codes['active'] }}>
    @else
    @php
        $sipTransport = $transportMap[strtolower((string) ($line['sip_transport'] ?? 'tcp'))] ?? '1';
        $srtpMode = ($line['sip_transport'] ?? 'TCP') === 'Tls Or Tcp' ? '1' : '0';
        $displayName = $line['display_name'] ?? $line['auth_id'] ?? '';
        $registerExpires = max(1, (int) (($line['register_expires'] ?? 3600) / 60));
        $outboundProxy = trim((string) ($line['outbound_proxy_primary'] ?? ''));
        $sipPort = trim((string) ($line['sip_port'] ?? ''));
    @endphp
    <!-- Account {{ $n }} -->
    <{{ $codes['active'] }}>1</{{ $codes['active'] }}>
    <{{ $codes['account'] }}>{{ $displayName }}</{{ $codes['account'] }}>
    <{{ $codes['server'] }}>{{ $line['server_address'] ?? '' }}</{{ $codes['server'] }}>
    <{{ $codes['outbound'] }}>{{ $outboundProxy }}{{ $outboundProxy !== '' && $sipPort !== '' ? ':'.$sipPort : '' }}</{{ $codes['outbound'] }}>
    <{{ $codes['blf'] }}>{{ $line['server_address'] ?? '' }}</{{ $codes['blf'] }}>
    <{{ $codes['user'] }}>{{ $line['auth_id'] ?? '' }}</{{ $codes['user'] }}>
    <{{ $codes['auth'] }}>{{ $line['auth_id'] ?? '' }}</{{ $codes['auth'] }}>
    <{{ $codes['password'] }}>{{ $line['password'] ?? '' }}</{{ $codes['password'] }}>
    <{{ $codes['name'] }}>{{ $displayName }}</{{ $codes['name'] }}>
    <{{ $codes['voicemail'] }}>{{ $settings['voicemail_number'] ?? '' }}</{{ $codes['voicemail'] }}>
    <{{ $codes['dns'] }}>{{ $settings['grandstream_dns_mode'] ?? '0' }}</{{ $codes['dns'] }}>
    <{{ $codes['nat'] }}>2</{{ $codes['nat'] }}>
    <{{ $codes['register'] }}>1</{{ $codes['register'] }}>
    <{{ $codes['expires'] }}>{{ $registerExpires }}</{{ $codes['expires'] }}>
    <{{ $codes['subscribe'] }}>60</{{ $codes['subscribe'] }}>
    <{{ $codes['keepAlive'] }}>1</{{ $codes['keepAlive'] }}>
    <{{ $codes['keepInterval'] }}>30</{{ $codes['keepInterval'] }}>
    <{{ $codes['keepMaxLost'] }}>3</{{ $codes['keepMaxLost'] }}>
    <{{ $codes['localPort'] }}>{{ 5060 + (($n - 1) * 2) }}</{{ $codes['localPort'] }}>
    <{{ $codes['retry'] }}>20</{{ $codes['retry'] }}>
    <{{ $codes['transport'] }}>{{ $sipTransport }}</{{ $codes['transport'] }}>
    <{{ $codes['mwi'] }}>1</{{ $codes['mwi'] }}>
    <{{ $codes['srtp'] }}>{{ $srtpMode }}</{{ $codes['srtp'] }}>
    <{{ $codes['dialPlan'] }}>{{ $dialPlan }}</{{ $codes['dialPlan'] }}>
    <{{ $codes['features'] }}>0</{{ $codes['features'] }}>
    <{{ $codes['xGrandstream'] }}>1</{{ $codes['xGrandstream'] }}>
    <{{ $codes['pani'] }}>1</{{ $codes['pani'] }}>
    <{{ $codes['pei'] }}>1</{{ $codes['pei'] }}>
    @endif
@endforeach

    <!-- Programmable Keys / Virtual Multi-Purpose Keys -->
@foreach ($vpkCodes as $slot => $codes)
    @php
        $key = $mainKeyById->get($slot);
        $type = strtolower(trim((string) ($key['type'] ?? 'none')));
        $mode = $vpkModeMap[$type] ?? -1;
        $account = max(0, (int) ($key['line'] ?? 0));
    @endphp
    <{{ $codes['mode'] }}>{{ $mode }}</{{ $codes['mode'] }}>
    <{{ $codes['account'] }}>{{ $account }}</{{ $codes['account'] }}>
    <{{ $codes['label'] }}>{{ $key['label'] ?? '' }}</{{ $codes['label'] }}>
    <{{ $codes['value'] }}>{{ $key['value'] ?? '' }}</{{ $codes['value'] }}>
@endforeach

  </config>
</gs_provision>
@break

@endswitch
