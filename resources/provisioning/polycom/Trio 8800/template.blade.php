{{-- version: 1.0.0 --}}

{{-- Trio 8800 Open SIP (UC Software 5.9.6). Shared settings follow the native Edge/VVX templates. --}}

@switch($flavor)

{{-- ================= Poly Trio 8800 mac.cfg ================= --}}
@case('mac.cfg')

<?xml version="1.0" standalone="yes"?>
<APPLICATION
    APP_FILE_PATH="{{ $settings['poly_trio_8800_firmware'] ?? '' }}"
    CONFIG_FILES="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" 
    MISC_FILES="" 
    LOG_FILE_DIRECTORY="logs"
    OVERRIDES_DIRECTORY="phoneconfigs"
    CONTACTS_DIRECTORY="directories"
    LICENSE_DIRECTORY="" 
    USER_PROFILES_DIRECTORY="" 
    CALL_LISTS_DIRECTORY="calls" />
@break

{{-- ================= Poly phonemac.cfg ================= --}}
@case('phonemac.cfg')

<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<PHONE_CONFIG>
<REGISTRATION reg.limit="3"
  @foreach ($lines as $line)
    @php
        $n = (int) ($line['line_number'] ?? 0);
        $hasSecondaryServer = !empty($line['outbound_proxy_secondary']) || !empty($line['server_address_secondary']);
    @endphp
    @continue($n <= 0 || $n > 3)

    reg.{{ $n }}.server.1.register="1"
    
    reg.{{ $n }}.lineKeys="{{ $polycom_line_key_counts[$n] ?? 1 }}"

    reg.{{ $n }}.auth.password="{{ $line['password'] ?? '' }}"
    reg.{{ $n }}.auth.userId="{{ $line['auth_id'] ?? '' }}"
    reg.{{ $n }}.label="{{ $line['display_name'] ?? $line['auth_id'] }}"
    reg.{{ $n }}.displayName="{{ $line['display_name'] ?? $line['auth_id'] }}"
    reg.{{ $n }}.address="{{ (($line['user_id'] ?? '') ?: ($line['auth_id'] ?? '')) . '@' . (($line['server_address'] ?? '') ?: $domain_name) }}"

    reg.{{ $n }}.server.1.address="{{ ($line['outbound_proxy_primary'] ?? '') ?: (($line['server_address_primary'] ?? '') ?: (($line['server_address'] ?? '') ?: $domain_name)) }}"
    reg.{{ $n }}.server.1.port="{{ $line['sip_port'] ?? '' }}"
    reg.{{ $n }}.server.1.transport="{{ $line['sip_transport'] ?? 'UDPOnly' }}"
    reg.{{ $n }}.server.1.expires="{{ $line['register_expires'] ?? '3600' }}"
    reg.{{ $n }}.srtp.enable="{{ $settings['polycom_srtp_enable'] ?? '0' }}"
    reg.{{ $n }}.srtp.offer="{{ $settings['polycom_srtp_offer'] ?? '0' }}"
    reg.{{ $n }}.srtp.require="{{ $settings['polycom_srtp_require'] ?? '0' }}"


    @if ($hasSecondaryServer)
        reg.{{ $n }}.server.2.address="{{ ($line['outbound_proxy_secondary'] ?? '') ?: $line['server_address_secondary'] }}"
        reg.{{ $n }}.server.2.port="{{ $line['sip_port'] ?? '' }}"
        reg.{{ $n }}.server.2.transport="{{ $line['sip_transport'] ?? 'UDPOnly' }}"
        reg.{{ $n }}.server.2.expires="{{ $line['register_expires'] ?? '3600' }}"
    @endif
    
    {{-- handle shared lines --}}
    @if (!empty($line['shared_line']))
      reg.{{ $n }}.type="shared"
      reg.{{ $n }}.bargeInEnabled="1"
      reg.{{ $n }}.bridgeInEnabled="1"
      reg.{{ $n }}.enablePvtHoldSoftKey="1"
      reg.{{ $n }}.callsPerLineKey="1"
    @else
        reg.{{ $n }}.type="private"
        reg.{{ $n }}.callsPerLineKey="{{ $settings['polycom_calls_per_line_key'] ?? '4'  }}"
    @endif

    reg.{{ $n }}.serverFeatureControl.cf="{{ $settings['polycom_feature_key_sync'] ?? '0' }}"
    reg.{{ $n }}.serverFeatureControl.dnd="{{ $settings['polycom_feature_key_sync'] ?? '0' }}"
    reg.{{ $n }}.server.1.subscribe.expires="3600"
    reg.{{ $n }}.server.2.subscribe.expires="3600"
    
    msg.mwi.{{ $n }}.callBackMode="contact"
    msg.mwi.{{ $n }}.callBack="{{ $settings['voicemail_number'] ?? '' }}"
  @endforeach
/>

<lineKey
    @if (!empty($polycom_line_key_slots))
        lineKey.reassignment.enabled="1"
    @else 
        lineKey.reassignment.enabled="0"
    @endif

  @foreach ($polycom_line_key_slots as $keySlot)
    {{ $keySlot['attribute'] }}.{{ $keySlot['slot'] }}.category="{{ $keySlot['category'] }}"
    {{ $keySlot['attribute'] }}.{{ $keySlot['slot'] }}.index="{{ $keySlot['index'] }}"
  @endforeach
/>

<attendant
    @if (isset($settings['polycom_remotecallerid_automata']))
		attendant.behaviors.display.remoteCallerID.automata="{{ $settings['polycom_remotecallerid_automata'] }}"
	@else
		attendant.behaviors.display.remoteCallerID.automata="0"
    @endif
    
    @if (isset($settings['polycom_remotecallerid_normal']))
		attendant.behaviors.display.remoteCallerID.normal="{{ $settings['polycom_remotecallerid_normal'] }}"
	@else
		attendant.behaviors.display.remoteCallerID.normal="0"
    @endif
    
	attendant.behaviors.display.spontaneousCallAppearances.normal="{{ $settings['polycom_spontaneouscallappearances_normal'] ?? '0'}}"
    attendant.behaviors.display.spontaneousCallAppearances.automata="{{ $settings['polycom_spontaneouscallappearances_automata'] ?? '0'}}"
    
		
  @foreach ($polycom_attendant_resources as $resource)
    attendant.resourceList.{{ $resource['index'] }}.address="{{ $resource['address'] }}"
    attendant.resourceList.{{ $resource['index'] }}.label="{{ $resource['label'] }}"
    @if (!empty($resource['type']))
      attendant.resourceList.{{ $resource['index'] }}.type="{{ $resource['type'] }}"
    @endif
  @endforeach
/>

    <!-- Admin Password -->  
    <device device.set="1" 
        device.baseProfile.set="1" 
        device.baseProfile="Generic"
        device.eulaAccepted.set="1"
        device.eulaAccepted="1"
        device.sntp.serverName.set="1"
        device.sntp.serverName="{{ $settings['ntp_server_primary'] ?? 'pool.ntp.org' }}"
        device.sntp.gmtOffset.set="1"
    	device.sntp.gmtOffset="{{ $settings['polycom_gmt_offset'] ?? '0' }}"
        >

        @if (!empty($settings['admin_password']))
        <device.auth.localAdminPassword 
          device.auth.localAdminPassword.set="1"
          device.auth.localAdminPassword="{{ $settings['admin_password'] }}" 
          device.auth.localUserPassword.set="1"
          device.auth.localUserPassword="{{ $settings['admin_password'] }}" />
        @endif
    </device>

    <!-- SIP Keep alive -->
    <nat
    nat.keepalive.interval="{{ $settings['polycom_keep_alive'] ?? 25 }}">
    </nat>

    <!-- Enter vm directly by vm key -->
    <msg msg.bypassInstantMessage="1"></msg>

    <!-- Remember Volume settings user pressed -->
    <voice.volume
        voice.volume.persist.handsfree="1"
        voice.volume.persist.usb.handsfree="1"
        feature.nonVolatileRingerVolume.enabled="1"

    />
    
    <general 
        up.oneTouchVoiceMail="{{ $settings['polycom_one_touch_voicemail'] ?? '1' }}"
        up.warningLevel="2"
        se.stutterOnVoiceMail="0"
        dir.local.readonly="0"
        diags.pcap.enabled="0"
        diags.pcap.remote.enabled="0"
        sec.srtp.simplifiedBestEffort="0"
        powerSaving.enable="{{ $settings['polycom_power_saving_enable'] ?? '0' }}"
        prov.polling.enabled="{{ $settings['polycom_provision_polling_enabled'] ?? '0' }}"
		prov.polling.time="{{ $settings['polycom_provision_polling_time'] ?? '03:00' }}"
		voice.aec.hf.enable="1"
		powerSaving.idleTimeout.officeHours="480"
    />
    
    <sec
        @if (isset($settings['polycom_root_cert']))
            sec.TLS.customCaCert.5="{!! polycom_inline_cert($settings['polycom_root_cert']) !!}"
        @endif
        sec.TLS.SIP.strictCertCommonNameValidation="{{ $settings['polycom_cert_validation'] ?? 0 }}"
        device.sec.TLS.prov.strictCertCommonNameValidation.set="1"
        device.sec.TLS.prov.strictCertCommonNameValidation="{{ $settings['polycom_provision_cert_validate'] ?? $settings['polycom_cert_validation'] ?? 0 }}"
        device.sec.TLS.syslog.strictCertCommonNameValidation.set="1"
        device.sec.TLS.syslog.strictCertCommonNameValidation="{{ $settings['polycom_cert_validation'] ?? 0 }}"
        sec.TLS.SIP.strictCertNameValidationToConfiguredAddresses="0"
        sec.tagSerialNo="1" 
        @if (isset($settings['polycom_platform_profile_cipher_suite']))
            device.sec.TLS.profile.cipherSuiteDefault1.set="1"
		    device.sec.TLS.profile.cipherSuiteDefault1="0"
            device.sec.TLS.profile.cipherSuite1.set="1"
    	    device.sec.TLS.profile.cipherSuite1="{{ $settings['polycom_platform_profile_cipher_suite'] }}"
        @endif
    />
    
    <device.prov
        device.prov.tagSerialNo.set="1"
        device.prov.tagSerialNo="1"
        @if (!empty($settings['provision_base_url']))
            device.prov.serverName.set="1"
    	    device.prov.serverName="{{ $settings['provision_base_url'] }}"
            device.prov.serverType.set="1"
            device.prov.serverType="{{ str_starts_with(strtolower($settings['provision_base_url']), 'https://') ? '3' : '2' }}"
        @endif
        device.prov.user.set="1"
        device.prov.user="{{ $settings['http_auth_username'] ?? '' }}"
        device.prov.password.set="1"
        device.prov.password="{{ $settings['http_auth_password'] ?? '' }}"
        device.prov.redunAttemptLimit.set="1"
        device.prov.redunAttemptLimit="10"
        device.prov.redunInterAttemptDelay.set="1"
        device.prov.redunInterAttemptDelay="150"
        device.prov.abortSWUpgradeAfterFailures.set="1"
        device.prov.abortSWUpgradeAfterFailures="3"
        @if (isset($settings['polycom_syslog_server']))
    	    device.syslog.serverName="{{ $settings['polycom_syslog_server'] }}"
    		device.syslog.transport="{{ $settings['polycom_syslog_transport'] }}"
    		device.syslog.facility="{{ $settings['polycom_syslog_facility'] }}"
    		device.syslog.renderLevel="{{ $settings['polycom_syslog_renderlevel'] }}"
    		device.syslog.prependMac="{{ $settings['polycom_syslog_prependmac'] }}"
        @endif
    />
    
    @if (isset($settings['polycom_page_enable']))
    <multicast_paging
		ptt.pageMode.enable="{{ $settings['polycom_page_enable'] }}"
		ptt.pageMode.group.1.label="Page All"
		ptt.volume = "0"
	/>
	@endif
	
    
    <voIpProt
        voIpProt.SIP.use486forReject="1"
        voIpProt.SIP.specialEvent.checkSync.alwaysReboot="1"
		voIpProt.SIP.requestValidation.1.method="{{ $settings['polycom_request_validation_method'] ?? 'Source' }}"
		voIpProt.SIP.requestValidation.1.request="{{ $settings['polycom_request_validation_request'] ?? 'INVITE' }}"
		@if (array_key_exists('polycom_diversion_header', $settings))
            voIpProt.SIP.header.diversion.enable = "{{ $settings['polycom_diversion_header'] }}"
        @endif
		voIpProt.server.1.failOver.reRegisterOn="1"
		voIpProt.server.1.failOver.failRegistrationOn="1"
		voIpProt.server.1.failOver.onlySignalWithRegistered="1" 
		voIpProt.server.1.failOver.failBack.mode="duration"
		voIpProt.server.1.failOver.failBack.timeout="120"
		voIpProt.server.2.failOver.reRegisterOn="1"
		voIpProt.server.2.failOver.failRegistrationOn="1"
		voIpProt.server.2.failOver.onlySignalWithRegistered="1" 
		voIpProt.server.2.failOver.failBack.mode="duration"
		voIpProt.server.2.failOver.failBack.timeout="120"
    />
    
    <call
      call.callWaiting.enable="{{ $settings['polycom_call_waiting'] ?? '1' }}"
      call.callWaiting.ring="beep"
      call.defaultTransferType="{{ $settings['polycom_default_transfer_method'] ?? 'Consultative' }}"
     />
     
    <!-- Dialplan -->
    <dialplan 
        dialplan.digitmap="{{ $settings['polycom_digitmap'] ?? '[2-8]11|R911R911.R|0T|011xxx.T|[0-1][2-9]xxxxxxxxx|[2-9]xxxxxxxxx|[1-9]xxT|0[1-9]xxT|[1-9]xxxT|0[1-9]xxxT|*x.T|**x.T' }}"
        dialplan.digitmap.timeOut="{{ $settings['polycom_digitmap_timeout'] ?? '1|1|3|3|3|3|3|3|3|3|3|3' }}">
    </dialplan>

    <feature
        feature.callRecording.enabled="0"
        feature.urlDialing.enabled="0"
        feature.enhancedFeatureKeys.enabled="1"
        feature.broadsoftUcOne.enabled="0"
        feature.doNotDisturb.enable="0"
		feature.forward.enable="0"
        @if (isset($settings['polycom_bluetooth_radio_on']))
    	    feature.bluetooth.radioOn="{{ $settings['polycom_bluetooth_radio_on'] }}"
        @endif
        @if (isset($settings['polycom_log_upload_enabled']))
    	    feature.logUpload.enabled="{{ $settings['polycom_log_upload_enabled'] }}"
        @endif
    
    />
    
    <softkeys
          @php
            $is1 = function (string $k) use ($settings) {
              return array_key_exists($k, $settings ?? []) && (string)$settings[$k] === '1';
            };
            $slot = 1;
          @endphp
        
          {{-- VM Transfer --}}
          @if ($is1('polycom_vm_transfer_enable'))
            softkey.{{ $slot }}.label="VMTransfer"
            softkey.{{ $slot }}.action="^*99$P{{ $slot }}N4$$Trefer$"
            softkey.{{ $slot }}.enable="1"
            softkey.{{ $slot }}.use.active="1"
            softkey.{{ $slot }}.precede="1"
            efk.efkprompt.{{ $slot }}.label="Voice Mail ID to transfer to:"
            efk.efkprompt.{{ $slot }}.status="1"
            efk.efkprompt.{{ $slot }}.type="numeric"
            @php $slot++; @endphp
          @endif
        
          {{-- Intercom --}}
          @if ($is1('polycom_intercom_enable'))
            softkey.{{ $slot }}.label="Intercom"
            softkey.{{ $slot }}.action="^*8$P{{ $slot }}N4$$Tinvite$"
            softkey.{{ $slot }}.enable="1"
            softkey.{{ $slot }}.use.idle="1"
            efk.efkprompt.{{ $slot }}.label="Enter destination"
            efk.efkprompt.{{ $slot }}.status="1"
            efk.efkprompt.{{ $slot }}.type="numeric"
            efk.efkprompt.{{ $slot }}.userfeedback="visible"
            efk.efkprompt.{{ $slot }}.digitmatching="none"
            @php $slot++; @endphp
          @endif
        
          {{-- Pick up --}}
          @if ($is1('polycom_pickup_enable'))
            softkey.{{ $slot }}.label="Pick up"
            softkey.{{ $slot }}.action="^**$P{{ $slot }}N4$$Tinvite$"
            softkey.{{ $slot }}.enable="1"
            softkey.{{ $slot }}.use.idle="1"
            efk.efkprompt.{{ $slot }}.label="Enter ext to intercept"
            efk.efkprompt.{{ $slot }}.status="1"
            efk.efkprompt.{{ $slot }}.type="numeric"
            efk.efkprompt.{{ $slot }}.userfeedback="visible"
            efk.efkprompt.{{ $slot }}.digitmatching="none"
            @php $slot++; @endphp
          @endif
        
          {{-- Speed Dial --}}
          @if ($is1('polycom_speeddial_enable'))
            softkey.{{ $slot }}.label="Speed Dial"
            softkey.{{ $slot }}.action="^*0$P{{ $slot }}N4$$Tinvite$"
            softkey.{{ $slot }}.enable="1"
            softkey.{{ $slot }}.use.idle="1"
            efk.efkprompt.{{ $slot }}.label="Enter Speed Dial Code"
            efk.efkprompt.{{ $slot }}.status="1"
            efk.efkprompt.{{ $slot }}.type="numeric"
            efk.efkprompt.{{ $slot }}.userfeedback="visible"
            efk.efkprompt.{{ $slot }}.digitmatching="none"
            @php $slot++; @endphp
          @endif
        
          {{-- Voicemail --}}
          @if ($is1('polycom_voicemail_softkey_enable'))
            softkey.{{ $slot }}.label="Voicemail"
            softkey.{{ $slot }}.action="$FMessages$"
            softkey.{{ $slot }}.enable="1"
            softkey.{{ $slot }}.use.idle="1"
            @php $slot++; @endphp
          @endif
		
		@if (isset($settings['polycom_softkey_do_not_disturb']))
    	    softkey.feature.doNotDisturb="{{ $settings['polycom_softkey_do_not_disturb'] }}"
        @endif
        @if (isset($settings['polycom_softkey_forward']))
    	    softkey.feature.forward="{{ $settings['polycom_softkey_forward'] }}"
        @endif
        @if (isset($settings['polycom_softkey_newcall']))
    	    softkey.feature.newcall="{{ $settings['polycom_softkey_newcall'] }}"
        @endif
        @if (isset($settings['polycom_softkey_directories']))
    	    softkey.feature.directories="{{ $settings['polycom_softkey_directories'] }}"
        @endif
        @if (isset($settings['polycom_basic_call_management_redundant']))
    	    softkey.feature.basicCallManagement.redundant="{{ $settings['polycom_basic_call_management_redundant'] }}"
    	    efk.softkey.alignleft="1"
        @endif
        @if (isset($settings['polycom_softkey_recent_calls']))
            softkey.feature.recentCalls="{{ $settings['polycom_softkey_recent_calls'] }}"
        @endif
    />
    
    <!-- NTP and DST Settings -->
     <tcpIpApp>
        <tcpIpApp.sntp 
            tcpIpApp.sntp.resyncPeriod="86400"
    		tcpIpApp.sntp.address="{{ $settings['ntp_server_primary'] ?? 'pool.ntp.org' }}"
    		tcpIpApp.sntp.gmtOffset.overrideDHCP="1"
    		tcpIpApp.sntp.gmtOffset="{{ $settings['polycom_gmt_offset'] ?? '0' }}"
            tcpIpApp.sntp.daylightSavings.enable="{{ filter_var($settings['daylight_savings_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN) ? '1' : '0' }}"
    		tcpIpApp.sntp.daylightSavings.fixedDayEnable="0"
    		tcpIpApp.sntp.daylightSavings.start.month="{{ $settings['daylight_savings_start_month'] ?? '3' }}"
    		tcpIpApp.sntp.daylightSavings.start.date="{{ $settings['daylight_savings_start_day'] ?? '8' }}"
    		tcpIpApp.sntp.daylightSavings.start.time="{{ $settings['daylight_savings_start_time'] ?? '2' }}"
    		tcpIpApp.sntp.daylightSavings.start.dayOfWeek="1"
    		tcpIpApp.sntp.daylightSavings.start.dayOfWeek.lastInMonth="0"
    		tcpIpApp.sntp.daylightSavings.stop.month="{{ $settings['daylight_savings_stop_month'] ?? '11' }}"
    		tcpIpApp.sntp.daylightSavings.stop.date="{{ $settings['daylight_savings_stop_day'] ?? '1' }}"
    		tcpIpApp.sntp.daylightSavings.stop.time="{{ $settings['daylight_savings_stop_time'] ?? '2' }}"
    		tcpIpApp.sntp.daylightSavings.stop.dayOfWeek="1"
    		tcpIpApp.sntp.daylightSavings.stop.dayOfWeek.lastInMonth="0"
          >
        </tcpIpApp.sntp>
    </tcpIpApp>
    
    <home_screen_settings
        homeScreen.intercom.enable="0"
        @if (isset($settings['polycom_homescreen_do_not_disturb']))
            homeScreen.doNotDisturb.enable="{{ $settings['polycom_homescreen_do_not_disturb'] }}"
        @endif
        @if (isset($settings['polycom_homescreen_forward']))
            homeScreen.forward.enable="{{ $settings['polycom_homescreen_forward'] }}"
        @endif
        @if (isset($settings['polycom_homescreen_directories']))
            homeScreen.directories.enable="{{ $settings['polycom_homescreen_directories'] }}"
        @endif
	/>
	
	@if (isset($settings['polycom_display_language']))
    	<language
    		lcl.ml.lang="{{ $settings['polycom_display_language'] ?? ''}}"
    	/>
	@endif
    
    <network_settings
        @if (isset($settings['polycom_dns_server']))
            tcpIpApp.dns.address.overrideDHCP="1"
            device.dns.serverAddress.set="1"
            device.dns.serverAddress="{{ $settings['polycom_dns_server'] }}"
        @endif
        @if (isset($settings['polycom_dns_alt_server']))
            device.dns.altSrvAddress.set="1"
            device.dns.altSrvAddress="{{ $settings['polycom_dns_alt_server'] }}"
        @endif
        @if (isset($settings['polycom_boot_server_option']))
            device.dhcp.bootSrvUseOpt.set="1"
            device.dhcp.bootSrvUseOpt="{{ $settings['polycom_boot_server_option'] }}"
        @endif
        @if (array_key_exists('polycom_lldp_enabled', $settings))
            device.net.lldpEnabled.set="1"
            device.net.lldpEnabled="{{ $settings['polycom_lldp_enabled'] }}"
        @endif
        @if (isset($settings['polycom_cdp_enabled']))
            device.net.cdpEnabled.set="1"
            device.net.cdpEnabled="{{ $settings['polycom_cdp_enabled'] }}"
        @endif
        @if (isset($settings['polycom_dhcp_vlan_discovery']))
            device.dhcp.dhcpVlanDiscUseOpt.set="1"
            device.dhcp.dhcpVlanDiscUseOpt="{{ $settings['polycom_dhcp_vlan_discovery'] }}"
        @endif
    />

</PHONE_CONFIG>
@break


{{-- ================= Poly model-mac.cfg ================= --}}
@case('model-mac.cfg')
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<polycomConfig>
    <certificate
        @if (array_key_exists('polycom_custom_ca_cert1', $settings))
            device.sec.TLS.customCaCert1.set="1"
            device.sec.TLS.customCaCert1="{!! polycom_inline_cert($settings['polycom_custom_ca_cert1']) !!}"
        @endif
        @if (array_key_exists('polycom_custom_ca_cert2', $settings))
            device.sec.TLS.customCaCert2.set="1"
            device.sec.TLS.customCaCert2="{!! polycom_inline_cert($settings['polycom_custom_ca_cert2']) !!}"
        @endif
    />
</polycomConfig>
@break

@endswitch
