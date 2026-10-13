{{-- version: 1.0.4 --}}
{{-- Shared S300/S500/S700 hl_provision parameter map. S-series is separate from P-series XML. --}}

@php
    $accounts = collect($lines)->keyBy('line_number')->filter(fn ($line, $number) =>
        $number >= 1 && ($line['user_id'] ?? $line['auth_id'] ?? '') !== ''
    );
    // These are the six account parameter banks in the S-series format, not model-specific limits.
    $accountParameters = [
        'Active' => [271, 401, 501, 601, 20360, 20361],
        'Sipserver' => [47, 747, 502, 602, 20362, 20363],
        'FailoverSipserver' => [967, 987, 988, 989, 20364, 20365],
        'SecondFailoverSipserver' => [8851, 8852, 8853, 8854, 20366, 20367],
        'OutboundProxy' => [48, 748, 503, 603, 20370, 20371],
        'BackUpOutboundProxy' => [20047, 20048, 20049, 20050, 20372, 20373],
        'SipTransport' => [130, 830, 930, 1030, 20374, 20375],
        'NatTraversal' => [52, 730, 514, 614, 20376, 20377],
        'Lable' => [20000, 20001, 20002, 20003, 20378, 20379],
        'SipUserId' => [35, 735, 504, 604, 1704, 1804],
        'AuthenticateID' => [36, 736, 505, 605, 1705, 1805],
        'AuthenticatePassword' => [34, 734, 506, 606, 1706, 1806],
        'DispalyName' => [3, 703, 507, 607, 1707, 1807],
        'DnsMode' => [103, 702, 508, 608, 20380, 20381],
        'SipRegistration' => [31, 731, 510, 610, 20384, 20385],
        'UnregisterOnReboot' => [81, 752, 511, 611, 20386, 20387],
        'RegisterExpiration' => [32, 732, 512, 612, 20388, 20389],
        'UseRandomPort' => [78, 778, 578, 678, 20390, 20391],
        'VoiceMailId' => [33, 426, 526, 626, 1726, 1826],
        'RPort' => [136, 137, 138, 139, 140, 141],
        'SubscribeForMWI' => [99, 709, 515, 615, 1715, 1815],
        'SpecialFeature' => [198, 767, 524, 624, 20452, 20453],
        'DtmfPayloadType' => [79, 779, 579, 679, 20416, 20417],
        'DtmfMode' => [20166, 20167, 20168, 20169, 20170, 20171],
        'DialPlan' => [4200, 4201, 4202, 4203, 4204, 4205],
        'Choice1' => [57, 757, 551, 651, 20392, 20393],
        'Choice2' => [58, 758, 552, 652, 20394, 20395],
        'Choice3' => [59, 759, 553, 653, 20396, 20397],
    ];
    $withPort = static function ($host, $port) {
        $host = trim((string) $host);
        if ($host === '' || preg_match('/^(?:\[[^\]]+\]|[^:]+):\d+$/', $host)) {
            return $host;
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $host = '[' . $host . ']';
        }
        return $host . ':' . $port;
    };
    $buttonKeys = collect($main_keys ?? [])->keyBy('id');
    if ($buttonKeys->isEmpty()) {
        $buttonKeys = $accounts->map(fn ($line, $number) => [
            'type' => 'line', 'line' => $number, 'label' => $line['display_name'] ?? $line['user_id'] ?? '',
        ]);
    }
    $defaultLine = (int) ($accounts->keys()->sort()->first() ?? 1);
    $keyTypes = ['line' => 1, 'speed_dial' => 2, 'blf' => 3, 'park' => 8];
@endphp

@switch($flavor)
@case('cfgmac.xml')
{!! '<' . '?xml version="1.0" encoding="UTF-8" ?' . '>' !!}
<hl_provision version="1">
    <config version="1">
    @for ($number = 1; $number <= 6; $number++)
        @php
            $line = $accounts->get($number, []);
            $enabled = $line !== [];
            $username = $line['user_id'] ?? $line['auth_id'] ?? '';
            $label = ($line['display_name'] ?? '') ?: $username;
            $transport = strtolower(trim((string) ($line['sip_transport'] ?? 'udp')));
            $port = ($line['sip_port'] ?? '') ?: ($transport === 'tls' ? 5061 : 5060);
            $server = ($line['server_address'] ?? '') ?: $domain_name;
            $primaryProxy = ($line['outbound_proxy_primary'] ?? '') ?: ($line['server_address_primary'] ?? '');
            $secondaryProxy = ($line['outbound_proxy_secondary'] ?? '') ?: ($line['server_address_secondary'] ?? '');
            $expires = ($line['register_expires'] ?? '') ?: ($settings['register_expires'] ?? 300);
            $values = [
                'Active' => $enabled ? 1 : 0,
                // Keep the tenant SIP domain; route to the PBX using its outbound proxy.
                'Sipserver' => $enabled ? $withPort($server, $port) : '',
                'FailoverSipserver' => '', 'SecondFailoverSipserver' => '',
                'OutboundProxy' => $enabled ? $withPort($primaryProxy, $port) : '',
                'BackUpOutboundProxy' => $enabled ? $withPort($secondaryProxy, $port) : '',
                'SipTransport' => ['udp' => 0, 'tcp' => 1, 'tls' => 2, 'dns srv' => 3][$transport] ?? 0,
                'NatTraversal' => 2, 'Lable' => $label, 'SipUserId' => $username,
                'AuthenticateID' => ($line['auth_id'] ?? '') ?: $username,
                'AuthenticatePassword' => $line['password'] ?? '', 'DispalyName' => $label,
                'DnsMode' => $settings['sangoma_dns_mode'] ?? 0,
                'SipRegistration' => $enabled ? 1 : 0,
                'UnregisterOnReboot' => 1,
                // S-series registration expiration is expressed in minutes.
                'RegisterExpiration' => max(1, (int) ceil((int) $expires / 60)),
                'UseRandomPort' => 1, 'VoiceMailId' => $settings['voicemail_number'] ?? '*97',
                'RPort' => 1, 'SubscribeForMWI' => 1, 'SpecialFeature' => 100,
                'DtmfPayloadType' => 101, 'DtmfMode' => 0,
                'DialPlan' => $settings['sangoma_s_dial_plan'] ?? '{[x*]+}',
                'Choice1' => 9, 'Choice2' => 0, 'Choice3' => 8,
            ];
        @endphp
        <!-- Account {{ $number }} -->
        @foreach ($accountParameters as $name => $parameters)
        <P{{ $parameters[$number - 1] }} para="Account{{ $number }}.{{ $name }}">{{ $values[$name] }}</P{{ $parameters[$number - 1] }}>
        @endforeach
    @endfor

        <!-- Main keys: the shared format defines positions 1-45 across three parameter banks. -->
    @for ($number = 1; $number <= 45; $number++)
        @php
            $key = $buttonKeys->get($number, []);
            $type = strtolower((string) ($key['type'] ?? ''));
            $mode = $keyTypes[$type] ?? 0;
            $lineNumber = (int) (($key['line'] ?? 0) ?: $defaultLine);
            $value = (string) ($key['value'] ?? '');
            if (!$accounts->has($lineNumber) || ($type !== 'line' && $value === '')) {
                $mode = 0;
            }
            $label = (string) (($key['label'] ?? '') ?: $value);
            if ($number <= 4) {
                $offset = $number - 1;
                $parameters = [41200 + $offset, 20600 + $offset, 41300 + $offset, 41400 + $offset, 41500 + $offset, 41600 + $offset];
            } elseif ($number <= 36) {
                $base = 20200 + ($number - 5) * 5;
                $parameters = [$base, 20600 + $number - 1, $base + 1, $base + 2, $base + 3, $base + 4];
            } else {
                $base = 23000 + ($number - 37) * 6;
                $parameters = range($base, $base + 5);
            }
            // The phone's account selector is zero-based; preserve the configured key position.
            $values = [$mode, 0, $mode && $type !== 'line' ? $value : '', $mode ? $label : '', $mode ? $lineNumber - 1 : 0, ''];
            $names = ['Type', 'Mode', 'Value', 'Label', 'Account', 'PickupCode'];
        @endphp
        @foreach ($parameters as $index => $parameter)
        <P{{ $parameter }} para="LineKey{{ $number }}.{{ $names[$index] }}">{{ $values[$index] }}</P{{ $parameter }}>
        @endforeach
    @endfor

        <!-- Common preferences; retain the handset's provisioning URL and firmware settings. -->
        <P30 para="UrlOrIpAddress">{{ $settings['ntp_server_primary'] ?? '0.pool.ntp.org' }}</P30>
        <P8622 para="DateTime.BackUpNTPServer">{{ $settings['ntp_server_secondary'] ?? '1.pool.ntp.org' }}</P8622>
        <!-- Use provisioned clock settings instead of DHCP time-zone and NTP overrides. -->
        <P143 para="Preference.DHCPTime">0</P143>
        <P144 para="DHCPOverrideNTP">0</P144>
        <P64 para="Preference.TimeZone">{{ $settings['sangoma_s_time_zone'] ?? '6' }}</P64>
        <P75 para="Preference.DaylightSavingTime">{{ $settings['sangoma_s_daylight_saving_time'] ?? '2' }}</P75>
        <P8624 para="Preference.TimeFormat">{{ $settings['sangoma_s_time_format'] ?? '1' }}</P8624>
        <P102 para="Preference.DateDisplayFormat">{{ $settings['sangoma_s_date_format'] ?? '1' }}</P102>
        <P84 para="KeepAtiveInterval">30</P84>
        <P24012 para="RemoteControl.Sipnotify">1</P24012>
        <P23279 para="Preference.WebSettingHighPriority">0</P23279>
        <!-- These certificate-validation switches use 0 = On, 1 = Off. -->
        <P23376 para="TLSCommenNameValidation">1</P23376>
        <P23377 para="TLSOnlyAcceptTrustCA">1</P23377>
        @if (!empty($settings['admin_password']))
        <P2 para="AdminPassword">{{ $settings['admin_password'] }}</P2>
        @endif
    </config>
</hl_provision>
@break
@default
    @php abort(404); @endphp
@endswitch
