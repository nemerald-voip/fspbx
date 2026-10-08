{{-- version: 1.0.0 --}}
{{-- D40/D45/D50/D60/D62/D65/D70; D80 requires DPMA and is not supported. --}}
{{-- https://sangomakb.atlassian.net/wiki/spaces/Phones/pages/24510636 --}}
{{-- https://sangomakb.atlassian.net/wiki/spaces/Phones/pages/24248648 --}}

@php
    // Live requests identify the handset; previews use its last contact or saved model.
    $model = strtoupper(trim((string) ($device_model ?? '')));
    $firmware = str_replace('_', '.', (string) ($device_firmware_version ?? ''));
    foreach ([$user_agent ?? '', $device_provisioned_agent ?? ''] as $agent) {
        if (preg_match('/\b(?:Digium|Sangoma)[\s\/_-]+(D\d+)(?:[\s\/_-]+(\d+[._]\d+[._]\d+))?/i', $agent, $match)) {
            $model = strtoupper($match[1]);
            $firmware = isset($match[2]) ? str_replace('_', '.', $match[2]) : $firmware;
            break;
        }
    }
    $model = $model ?: 'D65';
    $isD6x = in_array($model, ['D60', 'D62', 'D65'], true);
    $hasSidePanel = in_array($model, ['D50', 'D70'], true);
    $perHostProxy = $isD6x && ($firmware === '' || version_compare($firmware, '2.9.15', '>='));
    $accounts = collect($lines)->keyBy('line_number')->filter(fn ($line, $number) =>
        $number >= 1 && ($line['user_id'] ?? $line['auth_id'] ?? '') !== ''
    );
    $keyAreas = [];
    foreach (['main' => $main_keys ?? [], 'side' => $side_keys ?? [], 'expansion' => $expansion_keys ?? []] as $area => $items) {
        $keyAreas[$area] = collect($items)->keyBy('id')->filter(fn ($key, $id) => $id >= 1)->sortKeys();
    }
    $mainKeys = $keyAreas['main'];
    $buttonAccounts = $mainKeys->isEmpty() ? $accounts->all() : [];
    foreach ($mainKeys as $id => $key) {
        if (($key['type'] ?? '') === 'line' && $accounts->has((int) ($key['line'] ?? 1))) {
            $buttonAccounts[$id] = $accounts->get((int) ($key['line'] ?? 1));
        }
    }
    // The first physical key must remain the primary line.
    if (!isset($buttonAccounts[1]) && $accounts->isNotEmpty()) {
        $buttonAccounts[1] = $accounts->sortKeys()->first();
    }
    $accountSlots = max(6, (int) collect($buttonAccounts)->keys()->max());
    $mainWidth = match ($model) { 'D50' => 4, 'D65', 'D70' => 6, default => 2 };
    $mainSlots = max($mainWidth, (int) $mainKeys->keys()->max());
    $sideSlots = $hasSidePanel ? max(10, (int) $keyAreas['side']->keys()->max()) : 0;
    $expansionSlots = $model === 'D65' ? (int) $keyAreas['expansion']->keys()->max() : 0;
    if ($expansionSlots > 0) {
        $expansionSlots = (int) (ceil($expansionSlots / 40) * 40);
    }
    $freeMainSlots = array_values(array_filter(range(1, $mainWidth), fn ($id) => !isset($buttonAccounts[$id])));
    $contacts = [];
    $buttons = [];
    foreach (['main' => $mainSlots, 'side' => $sideSlots, 'expansion' => $expansionSlots] as $area => $count) {
        for ($number = 1; $number <= $count; $number++) {
            if ($area === 'main' && isset($buttonAccounts[$number])) {
                continue;
            }
            $key = $keyAreas[$area]->get($number, []);
            $type = strtolower((string) ($key['type'] ?? ''));
            $value = trim((string) ($key['value'] ?? ''));
            $contactId = $area . '-' . $number;
            if (in_array($type, ['blf', 'speed_dial', 'park'], true) && $value !== '') {
                $dial = $type === 'park' ? preg_replace('/^park\+/', '', $value) : $value;
                $contacts[$contactId] = [
                    'id' => $contactId, 'label' => ($key['label'] ?? '') ?: $dial,
                    'dial' => $dial, 'subscribe' => $type === 'speed_dial' ? '' : $value,
                ];
            }
            $index = $number - 1;
            // Paged models repeat a physical index for each successive page.
            // These are address conversions, not limits on administrator-defined keys.
            if ($area === 'main' && $model === 'D65' && $number > $mainWidth && $freeMainSlots !== []) {
                $index = $freeMainSlots[($number - $mainWidth - 1) % count($freeMainSlots)] - 1;
            } elseif ($area === 'side' && $model === 'D70') {
                $index %= 10;
            } elseif ($area === 'expansion') {
                // EXP150 has two pages of twenty physical keys per node.
                $index %= 20;
            }
            $buttons[] = [
                'area' => $area, 'index' => $index, 'node' => intdiv($number - 1, 40),
                'contact' => $contacts[$contactId] ?? null,
            ];
        }
    }
    $subscriptionCount = count(array_filter($contacts, fn ($contact) => $contact['subscribe'] !== ''));
    $keyRevision = hash('sha256', json_encode([$model, $buttons, $buttonAccounts]));
@endphp

@switch($flavor)
@case('mac.cfg')
<?xml version="1.0" encoding="UTF-8"?>
@php
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
    <setting id="time_zone" value="{{ $settings['sangoma_d_time_zone'] ?? 'America/Los_Angeles' }}" />
    <setting id="time_source" value="ntp" />
    <setting id="ntp_server" value="{{ $settings['ntp_server_primary'] ?? '0.pool.ntp.org' }}" />
    <setting id="locale" value="{{ $settings['sangoma_d_locale'] ?? 'en_US' }}" />
    <setting id="transport_udp_enabled" value="1" />
    <setting id="transport_tcp_enabled" value="1" />
    @if ($isD6x)
    <setting id="transport_tls_allowed" value="1" />
    <setting id="tls_allow_wildcard_certs" value="1" />
    @endif
    <setting id="allow_insecure_ssl" value="1" />
    <setting id="udp_ka_interval" value="30" />
    @if ($hasSidePanel)
    <setting id="enable_blf_on_unused_line_keys" value="1" />
    @endif
    @if ($model === 'D65')
    <setting id="expansion_enable" value="{{ $expansionSlots > 0 ? 1 : 0 }}" type="EXP100" />
    @endif
    <setting id="contacts_max_subscriptions" value="{{ max(40, $subscriptionCount) }}" />
    <setting id="blf_contact_group" value="FS PBX Keys" />
    <contacts id="fspbx-keys" url="{{ $sangoma_key_file_urls['contacts'] ?? '' }}?v={{ $keyRevision }}" />
    <smart_blf>
        <blf_items url="{{ $sangoma_key_file_urls['smartblf'] ?? '' }}?v={{ $keyRevision }}" />
    </smart_blf>
    <accounts>
    @for ($number = 1; $number <= $accountSlots; $number++)
        @php
            $line = $buttonAccounts[$number] ?? null;
            $username = $line['user_id'] ?? $line['auth_id'] ?? '';
        @endphp
        @if (!$line || $username === '')
        <account index="{{ $number - 1 }}" status="0" register="0" />
        @else
        @php
            $server = ($line['server_address'] ?? '') ?: $domain_name;
            $primaryProxy = ($line['outbound_proxy_primary'] ?? '') ?: ($line['server_address_primary'] ?? '');
            $secondaryProxy = ($line['outbound_proxy_secondary'] ?? '') ?: ($line['server_address_secondary'] ?? '');
            $transport = strtolower(trim((string) ($line['sip_transport'] ?? 'udp')));
            $transport = in_array($transport, ['udp', 'tcp', 'tls'], true) ? $transport : 'udp';
            $port = $line['sip_port'] ?? ($transport === 'tls' ? '5061' : '5060');
            $expires = ($line['register_expires'] ?? '') ?: ($settings['register_expires'] ?? 300);
            $label = ($line['display_name'] ?? '') ?: $username;
            $accountId = isset($usedAccountIds[$username]) ? $username . '-key-' . $number : $username;
            $usedAccountIds[$username] = true;
        @endphp
        <account server_uuid="{{ $domain_uuid }}" index="{{ $number - 1 }}" status="1" register="1"
                 account_id="{{ $accountId }}" username="{{ $username }}" authname="{{ ($line['auth_id'] ?? '') ?: $username }}"
                 password="{{ $line['password'] ?? '' }}" line_label="{{ $label }}" caller_id="{{ $label }}"
                 dial_plan="{{ $settings['sangoma_d_dial_plan'] ?? 'x.T3|*x.T3|**x.T3' }}"
                 visual_voicemail="0" voicemail="{{ $settings['voicemail_number'] ?? '*97' }}" needMwiSubscription="1">
            <host_primary server="{{ $server }}" port="{{ $port }}" transport="{{ $transport }}" reregister="{{ $expires }}" retry="25">
                @if ($perHostProxy)
                <outbound_proxy server="{{ $primaryProxy }}" port="{{ $port }}" transport="{{ $transport }}" />
                @endif
            </host_primary>
            @if ($perHostProxy && $secondaryProxy !== '')
            <host_alternate server="{{ $server }}" port="{{ $port }}" transport="{{ $transport }}" reregister="{{ $expires }}" retry="25">
                <outbound_proxy server="{{ $secondaryProxy }}" port="{{ $port }}" transport="{{ $transport }}" />
            </host_alternate>
            @endif
            @if (!$perHostProxy)
            <outbound_proxy server="{{ $primaryProxy }}" port="{{ $port }}" />
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
        @foreach ($buttons as $button)
            @php($contact = $button['contact'])
            <blf_item location="{{ $button['area'] }}" index="{{ $button['index'] }}"
                      @if ($button['area'] === 'expansion') node="{{ $button['node'] }}" @else paging="1" @endif
                      @if ($contact) contact_id="{{ $contact['id'] }}" @else blank="1" @endif>
            @if ($contact)
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
            @endif
            </blf_item>
        @endforeach
        </blf_items>
    </smart_blf>
</config>
@break
@endswitch
