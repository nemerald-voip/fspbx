{{-- version: 1.0.1 --}}# PCC Standard Format File # DO NOT CHANGE THIS LINE!
{{-- Panasonic KX-TGP600 / TPA6x. Settings reference and official CFG sample:
https://docs.connect.panasonic.com/pcc/support/sipphone/technic/TGP6/configuration_file.html
--}}
@php
    // Quoted Panasonic CFG values are plain text, not HTML. Reject delimiters
    // instead of silently changing credentials or allowing another directive.
    $cfg = static function ($value): string {
        $value = (string) ($value ?? '');
        if (strpbrk($value, "\"\r\n\0") !== false) {
            throw new \InvalidArgumentException('Panasonic configuration values cannot contain quotes or line breaks.');
        }
        return $value;
    };
    $setting = static function (string $name, $default = '') use ($settings) {
        $value = $settings[$name] ?? $default;
        if (is_array($value)) {
            foreach ($value as $candidate) {
                if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                    return $candidate;
                }
            }
            return $default;
        }
        return $value === '' || $value === null ? $default : $value;
    };
    $yn = static fn ($value): string => in_array(strtolower((string) $value), ['1', 'true', 'yes', 'y', 'on'], true) ? 'Y' : 'N';
    $slots = [];
    foreach ($lines ?? [] as $line) {
        $n = (int) ($line['line_number'] ?? 0);
        if ($n < 1 || $n > 8 || isset($slots[$n])) {
            throw new \InvalidArgumentException('KX-TGP600 requires unique line numbers from 1 through 8.');
        }
        if ((($line['user_id'] ?? '') ?: ($line['auth_id'] ?? '')) !== '' && ($line['password'] ?? '') !== '') {
            $slots[$n] = $line;
        }
    }
    ksort($slots);
    $firstLine = array_key_first($slots);
    $selection = static function ($value): string {
        if (!preg_match('/^[01](?:,[01]){7}$/', (string) $value)) {
            throw new \InvalidArgumentException('Panasonic handset and line selections require eight comma-separated 0 or 1 values.');
        }
        return (string) $value;
    };
    $oneSlot = static fn ($selected): string => implode(',', array_map(static fn ($n) => $n === $selected ? '1' : '0', range(1, 8)));
    $provisionUrl = rtrim((string) $setting('provision_base_url'), '/') . '/' . strtolower((string) $mac) . '.cfg';
@endphp

# Web administration: account/device settings supply the credentials.
ADMIN_ID="{!! $cfg($setting('admin_username', 'admin')) !!}"
@if ($setting('admin_password') !== '')
ADMIN_PASS="{!! $cfg($setting('admin_password')) !!}"
@endif

# Native provisioning; intervals are in minutes.
CFG_STANDARD_FILE_PATH="{!! $cfg($provisionUrl) !!}"
CFG_MASTER_FILE_PATH=""
CFG_PRODUCT_FILE_PATH=""
HTTP_AUTH_ID="{!! $cfg($setting('http_auth_username')) !!}"
HTTP_AUTH_PASS="{!! $cfg($setting('http_auth_password')) !!}"
CFG_CYCLIC="{!! $yn($setting('panasonic_cfg_cyclic', 'Y')) !!}"
CFG_CYCLIC_INTVL="{!! $cfg($setting('panasonic_cfg_cyclic_interval', 3)) !!}"
CFG_RTRY_INTVL="{!! $cfg($setting('panasonic_cfg_retry_interval', 3)) !!}"
CFG_RESYNC_FROM_SIP="check-sync"
SIPPNP_PROV_ENABLE="{!! $yn($setting('panasonic_sippnp_provision_enable', 'Y')) !!}"
@if ($setting('panasonic_firmware_url') !== '' && $setting('panasonic_firmware_version') !== '')
FIRM_UPGRADE_ENABLE="Y"
FIRM_VERSION="{!! $cfg($setting('panasonic_firmware_version')) !!}"
FIRM_FILE_PATH="{!! $cfg($setting('panasonic_firmware_url')) !!}"
@endif

# Network and media.
NW_SETTING_ENABLE="N"
DHCP_DNS_ENABLE="Y"
USER_DNS1_ADDR="{!! $cfg($setting('dns_server_primary', '8.8.8.8')) !!}"
USER_DNS2_ADDR="{!! $cfg($setting('dns_server_secondary', '4.2.2.2')) !!}"
RTP_PORT_MIN="{!! $cfg($setting('panasonic_rtp_port_min', 10000)) !!}"
RTP_PORT_MAX="{!! $cfg($setting('panasonic_rtp_port_max', 20000)) !!}"
SIP_ADD_RPORT="Y"
VOICE_MESSAGE_AVAILABLE="Y"

# Clock. The UTC offset and DST times are expressed in minutes.
NTP_ADDR="{!! $cfg($setting('ntp_server_primary', 'pool.ntp.org')) !!}"
TIME_ZONE="{!! $cfg($setting('panasonic_time_zone', -480)) !!}"
DST_ENABLE="{!! $yn($setting('panasonic_dst_enable', 'Y')) !!}"
DST_START_MONTH="{!! $cfg($setting('panasonic_dst_start_month', 3)) !!}"
DST_START_ORDINAL_DAY="{!! $cfg($setting('panasonic_dst_start_ordinal_day', 2)) !!}"
DST_START_DAY_OF_WEEK="{!! $cfg($setting('panasonic_dst_start_day_of_week', 0)) !!}"
DST_START_TIME="{!! $cfg($setting('panasonic_dst_start_time', 180)) !!}"
DST_STOP_MONTH="{!! $cfg($setting('panasonic_dst_stop_month', 11)) !!}"
DST_STOP_ORDINAL_DAY="{!! $cfg($setting('panasonic_dst_stop_ordinal_day', 1)) !!}"
DST_STOP_DAY_OF_WEEK="{!! $cfg($setting('panasonic_dst_stop_day_of_week', 0)) !!}"
DST_STOP_TIME="{!! $cfg($setting('panasonic_dst_stop_time', 60)) !!}"

# Eight SIP accounts. Empty slots clear old account credentials.
@for ($n = 1; $n <= 8; $n++)
@php
    $line = $slots[$n] ?? [];
    $enabled = $line !== [];
    $user = ($line['user_id'] ?? '') ?: ($line['auth_id'] ?? '');
    $realm = $enabled ? (($line['server_address'] ?? '') ?: ($domain_name ?? '')) : '';
    $proxy = $enabled ? (($line['outbound_proxy_primary'] ?? '') ?: $setting('outbound_proxy_primary')) : '';
    $server = $enabled ? ($proxy ?: (($line['server_address_primary'] ?? '') ?: $realm)) : '';
    $transport = match (strtolower(trim((string) ($line['sip_transport'] ?? 'udp')))) {
        'udp', '0' => 0,
        'tcp', '1' => 1,
        'tls', '2' => 2,
        default => throw new \InvalidArgumentException('Panasonic SIP transport must be UDP, TCP or TLS.'),
    };
    $port = ($line['sip_port'] ?? '') ?: ($transport === 2 ? 5061 : 5060);
@endphp

# Line {{ $n }}
DISPLAY_NAME_{{ $n }}="{!! $cfg(($line['display_name'] ?? '') ?: $user) !!}"
PHONE_NUMBER_{{ $n }}="{!! $cfg($user) !!}"
SIP_AUTHID_{{ $n }}="{!! $cfg(($line['auth_id'] ?? '') ?: $user) !!}"
SIP_PASS_{{ $n }}="{!! $cfg($line['password'] ?? '') !!}"
SIP_SVCDOMAIN_{{ $n }}="{!! $cfg($realm) !!}"
SIP_RGSTR_ADDR_{{ $n }}="{!! $cfg($server) !!}"
SIP_RGSTR_PORT_{{ $n }}="{!! $cfg($port) !!}"
SIP_PRXY_ADDR_{{ $n }}="{!! $cfg($server) !!}"
SIP_PRXY_PORT_{{ $n }}="{!! $cfg($port) !!}"
SIP_PRSNC_ADDR_{{ $n }}="{!! $cfg($server) !!}"
SIP_PRSNC_PORT_{{ $n }}="{!! $cfg($port) !!}"
SIP_OUTPROXY_ADDR_{{ $n }}="{!! $cfg($proxy) !!}"
SIP_OUTPROXY_PORT_{{ $n }}="{!! $cfg($port) !!}"
SIP_SRC_PORT_{{ $n }}="{{ 5060 + (($n - 1) * 10) }}"
SIP_TRANSPORT_{{ $n }}="{{ $transport }}"
REG_EXPIRE_TIME_{{ $n }}="{!! $cfg(($line['register_expires'] ?? '') ?: 120) !!}"
SIP_DNSSRV_ENA_{{ $n }}="Y"
PORT_PUNCH_INTVL_{{ $n }}="25"
SIP_DETECT_SSAF_{{ $n }}="Y"
# DSCP 46 is the expedited-forwarding equivalent of ToS 184.
DSCP_SIP_{{ $n }}="46"
DSCP_RTP_{{ $n }}="46"
OUTBANDDTMF_{{ $n }}="Y"
CODEC_ENABLE0_{{ $n }}="Y"
CODEC_PRIORITY0_{{ $n }}="4"
CODEC_ENABLE1_{{ $n }}="Y"
CODEC_PRIORITY1_{{ $n }}="1"
CODEC_ENABLE2_{{ $n }}="N"
CODEC_PRIORITY2_{{ $n }}="5"
CODEC_ENABLE3_{{ $n }}="Y"
CODEC_PRIORITY3_{{ $n }}="3"
CODEC_ENABLE4_{{ $n }}="Y"
CODEC_PRIORITY4_{{ $n }}="2"
VM_NUMBER_{{ $n }}="{!! $cfg($enabled ? $setting('voicemail_number', '*97') : '') !!}"
INCOMING_CALL_GROUP_{{ $n }}="{!! $selection($enabled ? $setting('panasonic_incoming_call_group_' . $n, $oneSlot($n)) : $oneSlot(null)) !!}"
@endfor

# Handset-to-line selection. Device settings can override each eight-slot list.
MULTI_NUMBER_ENABLE="Y"
@for ($handset = 1; $handset <= 8; $handset++)
@php $selected = isset($slots[$handset]) ? $handset : $firstLine; @endphp
KEY_PAD_TONE_HS{{ $handset }}="{!! $cfg($setting('panasonic_key_pad_tone', 0)) !!}"
OUTGOING_CALL_LINE_HS{{ $handset }}="{!! $selection($setting('panasonic_outgoing_call_line_hs' . $handset, $oneSlot($selected))) !!}"
DEFAULT_LINE_SELECT_HS{{ $handset }}="{!! $selection($setting('panasonic_default_line_select_hs' . $handset, $oneSlot($selected))) !!}"
@endfor

