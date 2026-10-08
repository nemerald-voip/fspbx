{{-- version: 1.2.0 --}}
{{-- Sangoma P-series XML reference: https://sangomakb.atlassian.net/wiki/spaces/Phones/pages/732790785 --}}

@php
    $accounts = collect($lines)->keyBy('line_number')->filter(fn ($line, $number) =>
        $number >= 1 && ($line['user_id'] ?? $line['auth_id'] ?? '') !== ''
    );
    $buttonKeys = collect($main_keys ?? [])->keyBy('id')->filter(fn ($key, $id) => $id >= 1);
    // Clear unused base positions across the series; higher administrator-defined
    // positions are emitted too. The phone determines which positions it supports.
    $slotCount = max(16, (int) $accounts->keys()->max(), (int) $buttonKeys->keys()->max());
    $buttonAccounts = [];

    if ($buttonKeys->isEmpty()) {
        $buttonAccounts = $accounts->all();
    } else {
        foreach ($buttonKeys as $id => $key) {
            if (($key['type'] ?? '') === 'line' && $accounts->has((int) ($key['line'] ?? 1))) {
                $buttonAccounts[$id] = $accounts->get((int) ($key['line'] ?? 1));
            }
        }
        // The primary line owns slot zero on P-series phones.
        if (!isset($buttonAccounts[1]) && $accounts->isNotEmpty()) {
            $buttonAccounts[1] = $accounts->sortKeys()->first();
        }
    }

    $contacts = [];
    foreach ($buttonKeys as $id => $key) {
        if (isset($buttonAccounts[$id])) {
            continue;
        }
        $type = strtolower((string) ($key['type'] ?? ''));
        $value = trim((string) ($key['value'] ?? ''));
        if (!in_array($type, ['blf', 'speed_dial', 'park'], true) || $value === '') {
            continue;
        }
        // The BLF watches park+*5901; the call/transfer dials the matching *5901 feature code.
        $dial = $type === 'park' ? preg_replace('/^park\+/', '', $value) : $value;
        $contacts[$id] = [
            'id' => 'key-' . $id,
            'type' => $type,
            'label' => ($key['label'] ?? '') ?: $dial,
            'dial' => $dial,
            'subscribe' => $type === 'speed_dial' ? '' : $value,
        ];
    }
    // A key edit changes the linked URLs so the phone fetches both documents again on Sync.
    $keyRevision = hash('sha256', json_encode([$slotCount, $buttonKeys->all(), $buttonAccounts, $contacts]));
@endphp

@switch($flavor)
@case('mac.cfg')
<?xml version="1.0" encoding="UTF-8"?>
@php
    // P-series admin passwords require digits; otherwise use the phone's default PIN.
    $adminPin = (string) ($settings['admin_password'] ?? '');
    $adminPin = ctype_digit($adminPin) ? $adminPin : '789';
    $usedAccountIds = [];
@endphp
<config>
    <setting id="in_switchvox_environment" value="0" />
    <setting id="server_environment" value="" />
    <setting id="enable_check_sync" value="1" />
    <setting id="use_secure_labels" value="1" />
    <setting id="login_password" value="{{ $adminPin }}" />
    <setting id="time_zone" value="{{ $settings['sangoma_p_time_zone'] ?? 'America/Los_Angeles' }}" />
    <setting id="ntp_server" value="{{ $settings['ntp_server_primary'] ?? '0.pool.ntp.org' }}" />
    <setting id="locale" value="{{ $settings['sangoma_p_locale'] ?? 'en_US' }}" />
    <setting id="transport_udp_enabled" value="1" />
    <setting id="transport_tcp_enabled" value="1" />
    <setting id="transport_tls_allowed" value="1" />
    <setting id="tls_allow_wildcard_certs" value="1" />
    <setting id="allow_insecure_ssl" value="1" />
    <setting id="udp_ka_interval" value="30" />
    <setting id="enable_blf_on_unused_line_keys" value="1" />
    <setting id="blf_contact_group" value="FS PBX Keys" />
    <contacts id="fspbx-keys" url="{{ $sangoma_key_file_urls['contacts'] ?? '' }}?v={{ $keyRevision }}" />
    <smart_blf>
        <blf_items url="{{ $sangoma_key_file_urls['smartblf'] ?? '' }}?v={{ $keyRevision }}" />
    </smart_blf>

    <accounts>
    @for ($number = 1; $number <= $slotCount; $number++)
        @php
            $line = $buttonAccounts[$number] ?? null;
            $username = $line['user_id'] ?? $line['auth_id'] ?? '';
        @endphp
        @if (!$line || $username === '')
        <account index="{{ $number - 1 }}" status="0" register="0" />
        @else
        @php
            // Keep the account's SIP domain in REGISTER; reach each PBX through its proxy.
            $server = ($line['server_address'] ?? '') ?: $domain_name;
            $primaryProxy = ($line['outbound_proxy_primary'] ?? '') ?: ($line['server_address_primary'] ?? '');
            $secondaryProxy = ($line['outbound_proxy_secondary'] ?? '') ?: ($line['server_address_secondary'] ?? '');
            $transport = strtolower(trim((string) ($line['sip_transport'] ?? 'udp')));
            $transport = in_array($transport, ['udp', 'tcp', 'tls'], true) ? $transport : 'udp';
            $port = $line['sip_port'] ?? ($transport === 'tls' ? '5061' : '5060');
            $expires = ($line['register_expires'] ?? '') ?: ($settings['register_expires'] ?? 300);
            $label = ($line['display_name'] ?? '') ?: $username;
            // Repeated Line keys use the same SIP credentials at different physical account slots.
            $accountId = isset($usedAccountIds[$username]) ? $username . '-key-' . $number : $username;
            $usedAccountIds[$username] = true;
        @endphp
        <account server_uuid="{{ $domain_uuid }}" index="{{ $number - 1 }}" status="1" register="1"
                 account_id="{{ $accountId }}" username="{{ $username }}" authname="{{ ($line['auth_id'] ?? '') ?: $username }}"
                 password="{{ $line['password'] ?? '' }}" line_label="{{ $label }}" caller_id="{{ $label }}"
                 dial_plan="{{ $settings['sangoma_p_dial_plan'] ?? 'x.T3|*x.T3|**x.T3' }}"
                 visual_voicemail="0" voicemail="{{ $settings['voicemail_number'] ?? '*97' }}" needMwiSubscription="1">
            <host_primary server="{{ $server }}" port="{{ $port }}" transport="{{ $transport }}" reregister="{{ $expires }}" retry="25">
                <outbound_proxy server="{{ $primaryProxy }}" port="{{ $port }}" transport="{{ $transport }}" />
            </host_primary>
            @if ($secondaryProxy !== '')
            <host_alternate server="{{ $server }}" port="{{ $port }}" transport="{{ $transport }}" reregister="{{ $expires }}" retry="25">
                <outbound_proxy server="{{ $secondaryProxy }}" port="{{ $port }}" transport="{{ $transport }}" />
            </host_alternate>
            @endif
            <permission id="record_own_calls" value="0" />
            <permission id="use_voicemail" value="1" />
            <permission id="ignore_calls" value="1" />
        </account>
        @endif
    @endfor
    </accounts>
</config>
@break
@case('mac-contacts.xml')
<?xml version="1.0" encoding="UTF-8"?>
<phonebooks>
    <contacts group_name="FS PBX Keys" id="fspbx-keys" editable="0">
    @foreach ($contacts as $contact)
        <contact id="{{ $contact['id'] }}" first_name="{{ $contact['label'] }}" last_name="" contact_type="special"
                 @if ($contact['subscribe'] !== '') subscribe_to="{{ $contact['subscribe'] }}" @endif>
            <actions>
                <action id="primary" dial="{{ $contact['dial'] }}" label="{{ $contact['label'] }}" name="Dial" />
            </actions>
        </contact>
    @endforeach
    </contacts>
</phonebooks>
@break
@case('mac-smartblf.xml')
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <smart_blf>
        <blf_items>
        @for ($number = 1; $number <= $slotCount; $number++)
            @if (isset($buttonAccounts[$number]))
                @continue
            @endif
            @if (!isset($contacts[$number]))
            <blf_item location="main" index="{{ $number - 1 }}" blank="1" />
            @else
            @php($contact = $contacts[$number])
            <blf_item location="main" index="{{ $number - 1 }}" paging="0" contact_id="{{ $contact['id'] }}">
                <behaviors>
                    <behavior phone_state="all" press_action="primary" press_function="dial" />
                    <behavior phone_state="connected" press_action="primary" press_function="transfer" />
                    <behavior phone_state="hold" press_action="primary" press_function="transfer" />
                    <behavior phone_state="hold/transfer" press_action="primary" press_function="transfer" />
                </behaviors>
                @if ($contact['subscribe'] !== '')
                <indicators>
                    <indicator target_status="unknown" led_color="green" led_state="off" />
                    <indicator target_status="idle" led_color="green" led_state="on" />
                    <indicator target_status="ringing" led_color="red" led_state="fast" />
                    <indicator target_status="on_the_phone" led_color="red" led_state="on" />
                    <indicator target_status="on_hold" led_color="red" led_state="slow" />
                </indicators>
                @endif
            </blf_item>
            @endif
        @endfor
        </blf_items>
    </smart_blf>
</config>
@break
@endswitch
