{{-- version: 1.0.0 --}}
{{-- HT841 / HT881. Parameter reference: ht8x1_config_1.0.11.8.txt from https://www.grandstream.com/support/tools --}}
{{-- Line 1 = FXS; lines 2-5 = HT841 FXO 1-4; lines 2-9 = HT881 FXO 1-8. --}}

@switch($flavor)
@case('cfgmac.xml')
@php
    // Live requests identify the gateway. Previews also have its saved model/last contact.
    $model = '';
    foreach ([$settings['grandstream_ht8x1_model'] ?? '', $user_agent ?? '', $device_model ?? '', $device_provisioned_agent ?? ''] as $candidate) {
        if (preg_match('/\bHT(841|881)\b/i', (string) $candidate, $match)) {
            $model = 'HT' . $match[1];
            break;
        }
    }
    $model = $model ?: 'HT841';
    $fxoCount = $model === 'HT881' ? 8 : 4;
    $lineByNumber = collect($lines)->keyBy(fn ($line) => (int) ($line['line_number'] ?? 0))->sortKeys();
    $accounts = $lineByNumber->filter(fn ($line, $number) =>
        $number >= 1 && $number <= $fxoCount + 1
        && trim((string) (($line['user_id'] ?? '') ?: ($line['auth_id'] ?? ''))) !== ''
    );

    // Selected active vendor defaults. Network addressing, certificates, and unrelated
    // device features are deliberately outside this template's managed settings.
    $vendorDefaults = <<<'PVALUES'
## System and time
# Voice prompts: 0 = enabled.
P253=0
# Direct IP calls: 0 = enabled.
P277=0
# Keypad configuration lock: 0 = unlocked.
P88=0
# STUN server.
P76=
# NAT keep-alive interval, seconds.
P84=20
# NTP server.
P30=pool.ntp.org
# Secondary NTP server.
P8333=0
# Time zone.
P64=customize
## Provisioning and firmware
# Config protocol: 0 = TFTP, 1 = HTTP, 2 = HTTPS, 3 = FTP, 4 = FTPS.
P212=2
# Config server path, without scheme.
P237=fm.grandstream.com/gs
# Config server authentication username.
P1360=
# Config server authentication password.
P1361=
# Config filename prefix.
P234=
# Config filename suffix.
P235=
# Authenticate config file: 0 = disabled.
P240=0
# DHCP provisioning override: 0 = disabled, 1 = enabled.
P145=1
# Automatic provisioning.
P1414=1
# Automatic upgrade: 0 = disabled, 1 = interval, 2 = daily, 3 = weekly.
P22296=0
# Upgrade interval, minutes.
P193=10080
# Firmware protocol: 2 = HTTPS.
P6767=2
# Firmware server path, without scheme.
P192=fm.grandstream.com/gs
## Diagnostics
# Syslog server.
P207=
# Syslog level: 0 = disabled.
P208=0
# Include SIP messages in syslog: 0 = disabled.
P1387=0
## FXS calling
# Analog phone dial plan.
P4200={ x+ | \+x+ | *x+ | *xx*x+ }
# Local call features: 0 = disabled, 1 = enabled, 2 = all.
P191=1
# SIP message waiting subscription.
P99=0
## FXO calling
# Ring the attached FXS phone on PSTN calls: 0 = disabled, 1 = enabled.
P842=1
# FXO profile 1 stage method: 1 = direct dialing, 2 = second dial tone.
P3304=2
# FXO profile 2 stage method.
P92921=2
# FXO profile 1 wait for PSTN dial tone.
P3303=1
# FXO profile 2 wait for PSTN dial tone.
P92917=1
# FXO profile 1 rings before answering.
P798=4
# FXO profile 2 rings before answering.
P92925=4
# FXO profile 1 current-disconnect detection.
P893=1
# FXO profile 2 current-disconnect detection.
P593=1
# FXO profile 1 current-disconnect threshold, milliseconds.
P857=100
# FXO profile 2 current-disconnect threshold, milliseconds.
P657=100
# FXO profile 1 disconnect tone detection.
P712=0
# FXO profile 2 disconnect tone detection.
P92861=0
# FXO profile 1 polarity reversal detection.
P28101=0
# FXO profile 2 polarity reversal detection.
P92805=0
PVALUES;

    $pValues = [];
    $pComments = [];
    $pendingComments = [];
    foreach (preg_split('/\R/', $vendorDefaults) as $assignment) {
        if (preg_match('/^(P\d+)\s*=\s?(.*)$/', $assignment, $match)) {
            $pValues[$match[1]] = trim($match[2]);
            $pComments[$match[1]] = $pendingComments;
            $pendingComments = [];
        } elseif (str_starts_with($assignment, '#')) {
            $pendingComments[] = $assignment;
        }
    }
    $set = static function (string $code, mixed $value, ?string $comment = null) use (&$pValues, &$pComments): void {
        $pValues[$code] = (string) ($value ?? '');
        if ($comment !== null) {
            $pComments[$code] = [$comment];
        }
    };
    $serverPath = static fn ($value) => trim(preg_replace('#^https?://#i', '', (string) $value), ' /');
    $transportMap = ['udp' => '0', 'tcp' => '1', 'tls' => '2', 'tls or tcp' => '2'];
    $withPort = static function ($host, $port): string {
        $host = trim((string) $host);
        $port = trim((string) $port);
        if ($host === '' || $port === '') {
            return $host;
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $host = '[' . $host . ']';
        }
        return preg_match('/:\d+$/', $host) ? $host : $host . ':' . $port;
    };

    if (array_key_exists('admin_password', $settings)) {
        $set('P28792', '15', 'Allow special characters in the provisioned admin password.');
        $set('P2', $settings['admin_password'], 'Administrator password.');
    }
    if (array_key_exists('user_password', $settings)) {
        $set('P196', $settings['user_password'], 'End-user password.');
    }
    $set('P76', $settings['grandstream_stun_server'] ?? '');
    $set('P30', $settings['ntp_server_primary'] ?? 'pool.ntp.org');
    $set('P8333', $settings['ntp_server_secondary'] ?? '');
    $set('P64', $settings['grandstream_time_zone'] ?? 'auto');
    $set('P212', str_starts_with(strtolower((string) ($settings['provision_base_url'] ?? '')), 'http://') ? '1' : '2');
    $set('P237', $serverPath($settings['provision_base_url'] ?? ''));
    $set('P1360', $settings['http_auth_username'] ?? '');
    $set('P1361', $settings['http_auth_password'] ?? '');
    $set('P145', '0');
    $set('P22296', $settings['grandstream_automatic_provisioning'] ?? '0');
    $set('P6767', $settings['grandstream_firmware_upgrade_protocol'] ?? '2');
    $set('P192', $serverPath($settings['grandstream_firmware_path'] ?? 'fm.grandstream.com/gs'));
    $set('P207', $settings['grandstream_syslog_server'] ?? '');
    $set('P208', $settings['grandstream_syslog_level'] ?? '0');
    $set('P1387', $settings['grandstream_send_sip_log'] ?? '0');
    $set('P4200', $settings['grandstream_dial_plan'] ?? $pValues['P4200']);
    $set('P191', '0'); // Let FS PBX handle feature codes.
    $set('P99', '1');
    $set('P842', $settings['grandstream_ht8x1_pstn_ring_thru_fxs'] ?? '0');

    foreach ([
        'stage_method' => ['2', 'P3304', 'P92921'],
        'wait_for_dial_tone' => ['1', 'P3303', 'P92917'],
        'number_of_rings' => ['4', 'P798', 'P92925'],
        'current_disconnect' => ['1', 'P893', 'P593'],
        'current_disconnect_threshold' => ['100', 'P857', 'P657'],
        'disconnect_tone_detection' => ['0', 'P712', 'P92861'],
        'polarity_reversal' => ['0', 'P28101', 'P92805'],
    ] as $setting => [$default, $first, $second]) {
        $value = $settings['grandstream_ht8x1_fxo_' . $setting] ?? $default;
        $set($first, $value);
        $set($second, $value);
    }
    if (array_key_exists('grandstream_ht8x1_fxo_disconnect_tone', $settings)) {
        $set('P841', $settings['grandstream_ht8x1_fxo_disconnect_tone'], 'PSTN disconnect tone, using Grandstream fxoch channel syntax.');
    }

    $nat = (string) ($settings['grandstream_nat_traversal'] ?? '2');
    $nat = in_array($nat, ['0', '1', '2', '3', '4', '5'], true) ? $nat : '2';
    $profileFields = [
        'active' => ['P271', 'P401', 'P501'],
        'server' => ['P47', 'P747', 'P547'],
        'failover' => ['P967', 'P987', 'P997'],
        'outbound' => ['P48', 'P748', 'P548'],
        'backup_outbound' => ['P2333', 'P2433', 'P2533'],
        'from_domain' => ['P8617', 'P8618', 'P8619'],
        'dns' => ['P103', 'P702', 'P502'],
        'nat' => ['P52', 'P730', 'P630'],
        'register' => ['P31', 'P731', 'P531'],
        'transport' => ['P130', 'P830', 'P530'],
        'unregister' => ['P81', 'P752', 'P552'],
        'expires' => ['P32', 'P732', 'P532'],
        'keep_alive' => ['P2397', 'P2497', 'P2597'],
        'keep_interval' => ['P2398', 'P2498', 'P2598'],
        'keep_max_lost' => ['P2399', 'P2499', 'P2599'],
        'local_port' => ['P40', 'P740', 'P640'],
        'random_port' => ['P20501', 'P20502', 'P20503'],
        'srtp' => ['P183', 'P443', 'P543'],
    ];

    // Credentials belong to ports. Only server/transport settings consume an FXO profile.
    $profiles = [];
    $fxoProfiles = [];
    $portProfiles = [];
    foreach ($accounts as $number => $line) {
        $transport = strtolower(trim((string) ($line['sip_transport'] ?? 'tcp')));
        $sipPort = (string) (($line['sip_port'] ?? '') ?: (($transportMap[$transport] ?? '1') === '2' ? '5061' : '5060'));
        $profile = [
            'server' => $withPort(($line['server_address_primary'] ?? '') ?: ($line['server_address'] ?? ''), $sipPort),
            'failover' => $withPort($line['server_address_secondary'] ?? '', $sipPort),
            'outbound' => $withPort($line['outbound_proxy_primary'] ?? '', $sipPort),
            'backup_outbound' => $withPort($line['outbound_proxy_secondary'] ?? '', $sipPort),
            'from_domain' => (string) ($line['server_address'] ?? ''),
            'transport' => $transportMap[$transport] ?? '1',
            'expires' => (string) min(64800, max(1, (int) ceil((($line['register_expires'] ?? '') ?: 3600) / 60))),
            'srtp' => $transport === 'tls or tcp' ? '1' : '0',
        ];
        if ($number === 1) {
            $profileId = 0;
        } else {
            $key = json_encode($profile);
            if (!isset($fxoProfiles[$key])) {
                if (count($fxoProfiles) === 2) {
                    throw new \InvalidArgumentException('HT8x1 supports at most two different FXO SIP profiles. Use matching server, proxy, transport, and registration settings for ports sharing a profile.');
                }
                $fxoProfiles[$key] = count($fxoProfiles) + 1;
            }
            $profileId = $fxoProfiles[$key];
        }
        $profiles[$profileId] = $profile;
        $portProfiles[$number - 1] = $profileId;
    }
    for ($id = 0; $id <= 2; $id++) {
        $profile = $profiles[$id] ?? [];
        $values = $profile + [
            'active' => $profile ? '1' : '0', 'register' => $profile ? '1' : '0',
            'server' => '', 'failover' => '', 'outbound' => '', 'backup_outbound' => '', 'from_domain' => '',
            'transport' => '1', 'expires' => '60', 'srtp' => '0',
            'dns' => $settings['grandstream_dns_mode'] ?? '0', 'nat' => $nat, 'unregister' => '2',
            'keep_alive' => '1', 'keep_interval' => '30', 'keep_max_lost' => '3',
            'local_port' => 5060 + ($id * 1000), 'random_port' => '0',
        ];
        foreach ($profileFields as $field => $codes) {
            $label = $id === 0 ? 'FXS profile' : 'FXO profile ' . $id;
            $set($codes[$id], $values[$field], $label . ': ' . str_replace('_', ' ', $field) . '.');
        }
    }

    // Forwarding codes are not contiguous across ports in the vendor reference.
    $forwardCodes = [
        1 => ['P3305', 'P3306', 'P3307', 'P769'],
        2 => ['P92829', 'P92833', 'P92837', 'P92825'],
        3 => ['P92830', 'P92834', 'P92838', 'P92826'],
        4 => ['P92831', 'P92835', 'P92839', 'P92827'],
        5 => ['P92988', 'P92982', 'P92996', 'P92984'],
        6 => ['P92989', 'P92993', 'P92997', 'P92985'],
        7 => ['P92990', 'P92994', 'P92998', 'P92986'],
        8 => ['P92991', 'P92995', 'P92999', 'P92987'],
    ];
    for ($port = 0; $port <= $fxoCount; $port++) {
        $line = $accounts->get($port + 1);
        $label = $port === 0 ? 'FXS' : 'FXO ' . $port;
        $user = ($line['user_id'] ?? '') ?: ($line['auth_id'] ?? '');
        $set('P' . (4060 + $port), $user, $label . ': SIP user ID.');
        $set('P' . (4090 + $port), ($line['auth_id'] ?? '') ?: $user, $label . ': authentication ID.');
        $set('P' . (4120 + $port), $line['password'] ?? '', $label . ': authentication password.');
        $set('P' . (4180 + $port), ($line['display_name'] ?? '') ?: $user, $label . ': display name.');
        $set('P' . (4150 + $port), $portProfiles[$port] ?? ($port === 0 ? '0' : '1'), $label . ': profile ID.');
        $set('P' . (4595 + $port), $line ? '1' : '0', $label . ': port enabled.');
        if ($port === 0) {
            continue;
        }
        $set('P' . (4300 + $port), '0', $label . ': hunting group disabled; each port has its own account.');
        [$destinationCode, $serverCode, $portCode, $pstnCode] = $forwardCodes[$port];
        $destination = $line ? trim((string) ($settings['grandstream_ht8x1_fxo_' . $port . '_destination'] ?? '')) : '';
        $set($destinationCode, $destination, $label . ': incoming PSTN call destination in FS PBX.');
        $set($serverCode, $destination !== '' ? ($line['server_address'] ?? '') : '', $label . ': incoming call SIP server.');
        $transport = strtolower((string) ($line['sip_transport'] ?? 'tcp'));
        $set($portCode, $destination !== '' ? (($line['sip_port'] ?? '') ?: (in_array($transport, ['tls', 'tls or tcp'], true) ? '5061' : '5060')) : '5060', $label . ': incoming call SIP server port.');
        $set($pstnCode, '', $label . ': clear unconditional forwarding to a fixed PSTN number.');
    }
@endphp
<?xml version="1.0" encoding="UTF-8"?>
<!-- Grandstream {{ $model }}: Line 1 = FXS; Lines 2-{{ $fxoCount + 1 }} = FXO 1-{{ $fxoCount }}. -->
<gs_provision version="1">
  <config version="1">
@foreach ($pValues as $code => $value)
@foreach ($pComments[$code] ?? [] as $comment)
    <!-- {{ $comment }} -->
@endforeach
    <{{ $code }}>{{ $value }}</{{ $code }}>
@endforeach
  </config>
</gs_provision>
@break
@endswitch
