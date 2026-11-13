{{-- version: 1.0.3 --}}
{{-- VTech SIP Next Gen: NG-S3111, NG-S3112, NG-S3211, NG-S3211W, NG-S3212, NG-S3311, NG-S3411, NG-S3411W, NG-S3412. --}}
{{-- Reference: VTech SIP Phone Configuration Guide, 10 January 2024, sections 4.4.1 and 4.6. --}}
{{-- https://dl.businesscom.nl/Public/Vtech/Documentation/Configuration%20Guides/SIP%20Phones/SIP%20Phone%20Configuration%20Guide/SIP%20Phone%20Configuration%20Guide_10JAN2024.pdf --}}

@php
    // Live requests identify the phone; previews can use its saved model/last contact.
    $model = 'NG-S3212';
    foreach ([$settings['vtech_model'] ?? '', $user_agent ?? '', $device_model ?? '', $device_provisioned_agent ?? ''] as $candidate) {
        if (preg_match('/\bNG[-_]?S(3111|3112|3211W?|3212|3311|3411W?|3412)\b/i', (string) $candidate, $match)) {
            $model = 'NG-S' . strtoupper($match[1]);
            break;
        }
    }
    // CFG is plain text: preserve password punctuation, but never emit extra directives via newlines.
    $text = static function ($value): string {
        $value = str_replace(["\r", "\n", "\0"], '', (string) ($value ?? ''));
        return trim($value) === '' ? '%NULL' : $value;
    };
    $line = collect($lines ?? [])->firstWhere('line_number', 1) ?? [];
    $username = ($line['user_id'] ?? '') ?: ($line['auth_id'] ?? '');
    $enabled = $username !== '' && ($line['password'] ?? '') !== '';
    $server = ($line['server_address'] ?? '') ?: ($domain_name ?? '');
    $primaryProxy = ($line['outbound_proxy_primary'] ?? '') ?: ($line['server_address_primary'] ?? '');
    $backupProxy = ($line['outbound_proxy_secondary'] ?? '') ?: ($line['server_address_secondary'] ?? '');
    $transport = strtolower(trim((string) ($line['sip_transport'] ?? 'udp')));
    $transport = in_array($transport, ['udp', 'tcp', 'tls'], true) ? $transport : 'udp';
    $port = ($line['sip_port'] ?? '') ?: ($transport === 'tls' ? 5061 : 5060);
    $expires = ($line['register_expires'] ?? '') ?: (($settings['register_expires'] ?? '') ?: 300);
    $expires = max(30, min(7200, (int) $expires));
    $mainKeys = collect($main_keys ?? [])->keyBy('id');
    $cordless = in_array($model, ['NG-S3112', 'NG-S3411', 'NG-S3411W', 'NG-S3412'], true);
    // NG-S3111's colour handset uses a separate speed-dial configuration (guide section 6.3).
    $keySlots = $model === 'NG-S3111' ? [] : range(1, 10);
    if ($cordless) {
        $keySlots = array_merge($keySlots, [13, 14]);
    }
@endphp
# Model Number = {!! $model !!}
@if (!empty($device_firmware_version))
# SW Version = {!! $text($device_firmware_version) !!}
@endif

# One SIP account shared by the base and its handsets.
sip_account.1.sip_account_enable = {{ $enabled ? 1 : 0 }}
sip_account.1.label = {!! $text(($line['display_name'] ?? '') ?: $username) !!}
sip_account.1.display_name = {!! $text($line['display_name'] ?? '') !!}
sip_account.1.user_id = {!! $text($username) !!}
sip_account.1.authentication_name = {!! $text(($line['auth_id'] ?? '') ?: $username) !!}
sip_account.1.authentication_access_password = {!! $text($line['password'] ?? '') !!}
sip_account.1.primary_sip_server_address = {!! $text($server) !!}
sip_account.1.primary_sip_server_port = {{ $port }}
sip_account.1.primary_registration_server_address = {!! $text($server) !!}
sip_account.1.primary_registration_server_port = {{ $port }}
sip_account.1.primary_registration_expires = {{ $expires }}
sip_account.1.unregister_after_reboot_enable = 1
sip_account.1.primary_outbound_proxy_server_address = {!! $text($primaryProxy) !!}
sip_account.1.primary_outbound_proxy_server_port = {{ $port }}
sip_account.1.backup_outbound_proxy_server_address = {!! $text($backupProxy) !!}
sip_account.1.backup_outbound_proxy_server_port = {{ $port }}
sip_account.1.transport_mode = {{ $transport }}
sip_account.1.dtmf_transport_method = rfc2833
sip_account.1.dial_plan = {!! $text(($settings['vtech_dial_plan'] ?? '') ?: 'x+P') !!}
sip_account.1.inter_digit_timeout = 3
sip_account.1.share_line_enable = {{ !empty($line['shared_line']) ? 1 : 0 }}
sip_account.1.mwi_enable = 1
sip_account.1.mwi_ignore_unsolicited = 0
sip_account.1.mwi_uri = {!! $text($username !== '' && $server !== '' ? $username . '@' . $server : '') !!}
sip_account.1.access_code_retrieve_voicemail = {!! $text(($settings['voicemail_number'] ?? '') ?: '*97') !!}
sip_account.1.keep_alive_enable = 1
sip_account.1.keep_alive_interval = 15
sip_account.1.keep_alive_ignore_failure = 1
sip_account.1.nat_traversal_stun_keep_alive_enable = 1
sip_account.1.nat_traversal_stun_keep_alive_interval = 30

# Recheck configuration on boot and when FS PBX sends check-sync.
provisioning.resync_mode = config_only
provisioning.bootup_check_enable = 1
provisioning.remote_check_sync_enable = 1

# Apply VLAN settings only when configured; an explicit 0 disables WAN VLAN tagging.
@if (isset($settings['vtech_vlan_wan_enable']) && trim((string) $settings['vtech_vlan_wan_enable']) !== '')
network.vlan.wan.enable = {!! $text($settings['vtech_vlan_wan_enable']) !!}
@endif
@if (isset($settings['vtech_vlan_wan_id']) && trim((string) $settings['vtech_vlan_wan_id']) !== '')
network.vlan.wan.id = {!! $text($settings['vtech_vlan_wan_id']) !!}
@endif
@if (isset($settings['vtech_vlan_wan_priority']) && trim((string) $settings['vtech_vlan_wan_priority']) !== '')
network.vlan.wan.priority = {!! $text($settings['vtech_vlan_wan_priority']) !!}
@endif

time_date.ntp_server = 1
time_date.ntp_server_addr = {!! $text(($settings['ntp_server_primary'] ?? '') ?: 'pool.ntp.org') !!}
@if (!empty($settings['vtech_time_zone']))
time_date.selected_timezone = {!! $text($settings['vtech_time_zone']) !!}
time_date.timezone_dhcp_option = 0
time_date.daylight_saving_auto_adjust = 1
time_date.daylight_saving_user_defined = 0
@endif
@if (!empty($settings['admin_password']))
profile.admin.access_password = {!! $text($settings['admin_password']) !!}
@endif

# Speed-dial slots use %NULL to remove previously assigned numbers.
@foreach ($keySlots as $slot)
@php
    $key = $mainKeys->get($slot, []);
    $type = strtolower(trim((string) ($key['type'] ?? '')));
    $value = $enabled && $type === 'speed_dial' ? ($key['value'] ?? '') : '';
@endphp
pfk.{{ $slot }}.quick_dial = {!! $text($value) !!}
pfk.{{ $slot }}.account = 1
@endforeach
