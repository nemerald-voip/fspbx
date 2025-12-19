{{-- version: 1.0.1 --}}

@switch($flavor)

{{-- ================= Poly E500 mac.cfg ================= --}}
@case('mac.cfg')

<?xml version="1.0" standalone="yes"?>
<APPLICATION
    APP_FILE_PATH="{{ $settings['poly_e500_firmware'] ?? }}"
    CONFIG_FILES="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" 
    MISC_FILES="" 
    LOG_FILE_DIRECTORY="" 
    OVERRIDES_DIRECTORY="" 
    CONTACTS_DIRECTORY="" 
    LICENSE_DIRECTORY="" 
    USER_PROFILES_DIRECTORY="" 
    CALL_LISTS_DIRECTORY="">


  <APPLICATION_SPIP300 APP_FILE_PATH_SPIP300="sip_213.ld" CONFIG_FILES_SPIP300="phone1_213.cfg, sip_213.cfg" />
  <APPLICATION_SPIP500 APP_FILE_PATH_SPIP500="sip_213.ld" CONFIG_FILES_SPIP500="phone1_213.cfg, sip_213.cfg" />
  <APPLICATION_SPIP301 APP_FILE_PATH_SPIP301="sip_318.ld" CONFIG_FILES_SPIP301="phone1_318.cfg, sip_318.cfg" />
  <APPLICATION_SPIP501 APP_FILE_PATH_SPIP501="sip_318.ld" CONFIG_FILES_SPIP501="phone1_318.cfg, sip_318.cfg" />
  <APPLICATION_SPIP600 APP_FILE_PATH_SPIP600="sip_318.ld" CONFIG_FILES_SPIP600="phone1_318.cfg, sip_318.cfg" />
  <APPLICATION_SPIP601 APP_FILE_PATH_SPIP601="sip_318.ld" CONFIG_FILES_SPIP601="phone1_318.cfg, sip_318.cfg" />
  <APPLICATION_SPIP430 APP_FILE_PATH_SPIP430="sip_327.ld" CONFIG_FILES_SPIP430="phone1_327.cfg, sip_327.cfg" />
  <APPLICATION_SPIP320 APP_FILE_PATH_SPIP320="sip_335.ld" CONFIG_FILES_SPIP320="" />
  <APPLICATION_SPIP330 APP_FILE_PATH_SPIP330="sip_335.ld" CONFIG_FILES_SPIP330="" />
  <APPLICATION_SPIP321 APP_FILE_PATH_SPIP321="sip_40x.ld" CONFIG_FILES_SPIP321="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SPIP331 APP_FILE_PATH_SPIP331="sip_40x.ld" CONFIG_FILES_SPIP331="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SPIP335 APP_FILE_PATH_SPIP335="sip_40x.ld" CONFIG_FILES_SPIP335="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SPIP450 APP_FILE_PATH_SPIP450="sip_40x.ld" CONFIG_FILES_SPIP450="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SPIP550 APP_FILE_PATH_SPIP550="sip_40x.ld" CONFIG_FILES_SPIP550="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SPIP560 APP_FILE_PATH_SPIP560="sip_40x.ld" CONFIG_FILES_SPIP560="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SPIP650 APP_FILE_PATH_SPIP650="sip_40x.ld" CONFIG_FILES_SPIP650="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SPIP670 APP_FILE_PATH_SPIP670="sip_40x.ld" CONFIG_FILES_SPIP670="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SSIP4000 APP_FILE_PATH_SSIP4000="sip_318.ld" CONFIG_FILES_SSIP4000="phone1_318.cfg, sip_318.cfg" />
  <APPLICATION_SSIP5000 APP_FILE_PATH_SSIP5000="sip_40x.ld" CONFIG_FILES_SSIP5000="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SSIP6000 APP_FILE_PATH_SSIP6000="sip_40x.ld" CONFIG_FILES_SSIP6000="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SSIP7000 APP_FILE_PATH_SSIP7000="sip_40x.ld" CONFIG_FILES_SSIP7000="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_SSDuo APP_FILE_PATH_SSDuo="sip_40x.ld" CONFIG_FILES_SSDuo="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_VVX300 APP_FILE_PATH_VVX300="sip_59x.ld" CONFIG_FILES_VVX300="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_VVX310 APP_FILE_PATH_VVX310="sip_59x.ld" CONFIG_FILES_VVX310="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_VVX400 APP_FILE_PATH_VVX400="sip_59x.ld" CONFIG_FILES_VVX400="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_VVX410 APP_FILE_PATH_VVX410="sip_59x.ld" CONFIG_FILES_VVX410="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_VVX500 APP_FILE_PATH_VVX500="sip_59x.ld" CONFIG_FILES_VVX500="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_VVX600 APP_FILE_PATH_VVX600="sip_59x.ld" CONFIG_FILES_VVX600="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  <APPLICATION_VVX1500 APP_FILE_PATH_VVX1500="sip_59x.ld" CONFIG_FILES_VVX1500="phone[PHONE_MAC_ADDRESS].cfg,  [PHONE_MODEL]-[PHONE_MAC_ADDRESS].cfg" />
  
</APPLICATION>
@break

{{-- ================= Poly phonemac.cfg ================= --}}
@case('phonemac.cfg')

<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<PHONE_CONFIG>
{{-- Build reg.x.lineKeys map --}}
@php
  $lineKeyCounts = [];
  foreach ($keys as $k) {
      if (strtolower($k['category'] ?? '') === 'line') {
          $ln  = (int)($k['line'] ?? 0);
          $cnt = is_numeric($k['value'] ?? null) ? (int)$k['value'] : null;
          if ($ln > 0 && $cnt !== null) {
              $lineKeyCounts[$ln] = $cnt; // take the value as set; last occurrence wins
          }
      }
  }
@endphp

<lineKey
  lineKey.reassignment.enabled="1"
  @php $slot = 1; @endphp

  @foreach ($keys as $k)
    @php
      $cat = strtolower($k['category'] ?? '');
    @endphp

    @if ($cat === 'line')
      @php
        $ln    = (int)($k['line'] ?? 0);
        // expand to the exact number set for this registration (default 1)
        $count = $lineKeyCounts[$ln] ?? 1;
      @endphp
      @for ($j = 1; $j <= $count; $j++)
        lineKey.{{ $slot }}.category="Line"
        lineKey.{{ $slot }}.index="{{ $ln }}"
        @php $slot++; @endphp
      @endfor
      
    @elseif ($cat === 'unassigned')
      lineKey.{{ $slot }}.category="Unassigned"
      lineKey.{{ $slot }}.index="null"
      @php $slot++; @endphp

    @elseif ($cat === 'blf')
      lineKey.{{ $slot }}.category="BLF"
      lineKey.{{ $slot }}.index="0"
      @php $slot++; @endphp
      
    @elseif ($cat === 'presence')
      lineKey.{{ $slot }}.category="BLF"
      lineKey.{{ $slot }}.index="0"
      @php $slot++; @endphp
    @endif
  @endforeach
/>

<REGISTRATION
  @foreach ($lines as $line)
    @php  $n = (int)($line['line_number'] ?? 0); @endphp
    @continue($n <= 0)

    reg.{{ $n }}.server.1.register="1"
    
    reg.{{ $n }}.lineKeys="{{ $lineKeyCounts[$n] ?? 1 }}"

    reg.{{ $n }}.auth.password="{{ $line['password'] ?? '' }}"
    reg.{{ $n }}.auth.userId="{{ $line['auth_id'] ?? '' }}"
    reg.{{ $n }}.label="{{ $line['display_name'] ?? $line['auth_id'] }}"
    reg.{{ $n }}.displayName="{{ $line['display_name'] ?? $line['auth_id'] }}"
    reg.{{ $n }}.address="{{ ($line['auth_id'] ?? '') . '@' . ($line['server_address'] ?? $domain_name) }}"

    reg.{{ $n }}.server.1.address="{{ $line['outbound_proxy_primary'] ?? $line['server_address_primary'] ?? $line['server_address'] ?? $domain_name }}"
    reg.{{ $n }}.server.1.port="{{ $line['sip_port'] ?? '' }}"
    reg.{{ $n }}.server.1.transport="{{ $line['sip_transport'] ?? 'UDPOnly' }}"
    reg.{{ $n }}.server.1.expires="{{ $line['register_expires'] ?? '3600' }}"
    reg.{{ $n }}.srtp.enable="{{ $settings['polycom_srtp_enable'] ?? '0' }}"
    reg.{{ $n }}.srtp.offer="{{ $settings['polycom_srtp_offer'] ?? '0' }}"
    reg.{{ $n }}.srtp.require="{{ $settings['polycom_srtp_require'] ?? '0' }}"


    @if (!empty($line['outbound_proxy_secondary']) || !empty($line['server_address_secondary']))
        reg.{{ $n }}.server.2.address="{{ $line['outbound_proxy_secondary'] ?? $line['server_address_secondary'] }}"
        reg.{{ $n }}.server.2.port="{{ $line['sip_port'] ?? '' }}"
        reg.{{ $n }}.server.2.transport="{{ $line['sip_transport'] ?? 'UDPOnly' }}"
        reg.{{ $n }}.server.2.expires="{{ $line['register_expires'] ?? '3600' }}"
        reg.{{ $n }}.srtp.enable="{{ $settings['polycom_srtp_enable'] ?? '0' }}"
        reg.{{ $n }}.srtp.offer="{{ $settings['polycom_srtp_offer'] ?? '0' }}"
        reg.{{ $n }}.srtp.require="{{ $settings['polycom_srtp_require'] ?? '0' }}"
    @endif
    
    {{-- handle shared lines --}}
    @if (!empty($line['shared_line']))
      reg.{{ $n }}.type="shared"
      reg.{{ $n }}.bargeInEnabled="1"
      reg.{{ $n }}.bridgeInEnabled="1"
      reg.{{ $n }}.enablePvtHoldSoftKey="1"
      reg.{{ $n }}.callsPerLineKey="1"
    @else
        reg.{{ $n }}.callsPerLineKey="{{ $settings['polycom_calls_per_line_key'] ?? '4'  }}"
    @endif
    
    msg.mwi.{{ $n }}.callBackMode="contact"
    msg.mwi.{{ $n }}.callBack="{{ $settings['voicemail_number'] ?? '' }}"
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
    
    @if (isset($settings['polycom_spontaneouscallappearances_normal']))
		attendant.behaviors.display.spontaneousCallAppearances.normal="{{ $settings['polycom_spontaneouscallappearances_normal'] }}"
	@else
		attendant.behaviors.display.spontaneousCallAppearances.normal="0"
    @endif
    
    @if (isset($settings['polycom_spontaneouscallappearances_automata']))
		attendant.behaviors.display.spontaneousCallAppearances.automata="{{ $settings['polycom_spontaneouscallappearances_automata'] }}"
    @endif
    
		
  @php $r = 1; @endphp
  @foreach ($keys as $k)
    @php
      $category = strtolower($k['category'] ?? '');
      $val      = trim((string)($k['value'] ?? ''));
      $label    = $k['label'] ?? $val;
      $type     = strtolower($k['type'] ?? 'normal');
    @endphp
    @continue(!in_array($category, ['blf','presence']) || $val === '')

    attendant.resourceList.{{ $r }}.address="{{ $val }}"
    attendant.resourceList.{{ $r }}.label="{{ $label }}"
    @if ($type === 'automata')
      attendant.resourceList.{{ $r }}.type="automata"
    @endif
    @php $r++; @endphp
  @endforeach
/>

    <!-- Admin Password -->  
    <device device.set="1" device.baseProfile.set="1" device.baseProfile="Generic"
        device.eulaAccepted.set="1"
        device.eulaAccepted="1"
        device.sntp.serverName="{{ $settings['ntp_server_primary'] ?? 'null' }}"
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
        voice.volume.persist.handset="1"
        voice.volume.persist.handsfree="1"
        voice.volume.persist.headset="1"
        voice.volume.persist.usbHeadset="1"
        voice.volume.persist.bluetooth.headset="1"
        voice.volume.persist.usb.handsfree="1"

    />
    
    <general 
        up.analogHeadsetOption="{{ $settings['polycom_analog_headset_option'] ?? 2 }}"
        up.oneTouchVoiceMail="{{ $settings['polycom_one_touch_voicemail'] ?? 1 }}"
        up.OffHookLineView.enabled="{{ $settings['polycom_offhook_line_view_enabled'] ?? 1 }}"
        up.warningLevel="2"
        up.headsetMode="{{ $settings['polycom_headset_mode'] ?? 1 }}"
        se.stutterOnVoiceMail="0"
        sec.srtp.simplifiedBestEffort="0"
    />
    
    <sec
        sec.TLS.SIP.strictCertCommonNameValidation="{{ $settings['polycom_cert_validation'] ?? 0 }}"
        device.sec.TLS.prov.strictCertCommonNameValidation="{{ $settings['polycom_cert_validation'] ?? 0 }}"
        device.sec.TLS.syslog.strictCertCommonNameValidation="{{ $settings['polycom_cert_validation'] ?? 0 }}"
        sec.TLS.SIP.strictCertNameValidationToConfiguredAddresses="0"
        sec.tagSerialNo="1" 
        @if (isset($settings['polycom_platform_profile_cipher_suite']))
		    device.sec.TLS.profile.cipherSuiteDefault1="0"
    	    device.sec.TLS.profile.cipherSuite1="{{ $settings['polycom_platform_profile_cipher_suite'] }}"
        @endif
    />
    
    <device.prov
        device.prov.tagSerialNo="1"
        @if (isset($settings['provision_base_url']))
    	    device.prov.serverName="{{ $settings['provision_base_url'] }}"
        @endif
        device.prov.user="{{ $settings['http_auth_username'] ?? '' }}"
        device.prov.password="{{ $settings['http_auth_password'] ?? '' }}"
        device.prov.redunAttemptLimit="10"
        device.prov.redunInterAttemptDelay="150"
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
      call.callWaiting.enable="{{ $settings['polycom_call_waiting'] ?? 1 }}"
      {{-- call.directedCallPickupMethod="legacy" --}}
      {{-- call.directedCallPickupString="*04" --}}
     />
     
    <!-- Dialplan -->
    <dialplan 
        dialplan.digitmap="{{ $settings['polycom_digitmap'] ?? '[2-8]11|R911R911.R|0T|011xxx.T|[0-1][2-9]xxxxxxxxx|[2-9]xxxxxxxxx|[1-9]xxT|0[1-9]xxT|[1-9]xxxT|0[1-9]xxxT|*x.T|**x.T' }}"
        dialplan.digitmap.timeOut="{{ $settings['polycom_digitmap_timeout'] ?? '1|1|3|3|3|3|3|3|3|3|3|3' }}">
    </dialplan>

    <feature
        feature.urlDialing.enabled="0"
        feature.enhancedFeatureKeys.enabled="1"
        feature.broadsoftUcOne.enabled="0"
        feature.doNotDisturb.enable="0"
		feature.forward.enable="0"
        @if (isset($settings['polycom_bluetooth_radio_on']))
    	    feature.bluetooth.enabled="{{ $settings['polycom_bluetooth_radio_on'] }}"
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
    		tcpIpApp.sntp.address="{{ $settings['ntp_server_primary'] ?? 'null' }}"
    		tcpIpApp.sntp.gmtOffset.overrideDHCP="1"
    		tcpIpApp.sntp.gmtOffset="{{ $settings['polycom_gmt_offset'] ?? '0' }}"
    		@if ($settings['daylight_savings_enabled'] == "false")
    		    tcpIpApp.sntp.daylightSavings.enable="0"
    		@else
    		    tcpIpApp.sntp.daylightSavings.enable="1"
    		@endif
    		tcpIpApp.sntp.daylightSavings.fixedDayEnable="0"
    		tcpIpApp.sntp.daylightSavings.start.month="{{ $settings['daylight_savings_start_month'] }}"
    		tcpIpApp.sntp.daylightSavings.start.date="{{ $settings['daylight_savings_start_day'] }}"
    		tcpIpApp.sntp.daylightSavings.start.time="{{ $settings['daylight_savings_start_time'] }}"
    		tcpIpApp.sntp.daylightSavings.start.dayOfWeek="1"
    		tcpIpApp.sntp.daylightSavings.start.dayOfWeek.lastInMonth="0"
    		tcpIpApp.sntp.daylightSavings.stop.month="{{ $settings['daylight_savings_stop_month'] }}"
    		tcpIpApp.sntp.daylightSavings.stop.date="{{ $settings['daylight_savings_stop_day'] }}"
    		tcpIpApp.sntp.daylightSavings.stop.time="{{ $settings['daylight_savings_stop_time'] }}"
    		tcpIpApp.sntp.daylightSavings.stop.dayOfWeek="1"
    		tcpIpApp.sntp.daylightSavings.stop.dayOfWeek.lastInMonth="0"
          >
        </tcpIpApp.sntp>
    </tcpIpApp>
    
    <home_screen_settings
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
            tcpIpApp.dns.server="{{ $settings['polycom_dns_server'] }}"
        @endif
        @if (isset($settings['polycom_dns_alt_server']))
            tcpIpApp.dns.server="{{ $settings['polycom_dns_alt_server'] }}"
        @endif
        @if (isset($settings['polycom_boot_server_option']))
            device.dhcp.bootSrvUseOpt="{{ $settings['polycom_boot_server_option'] }}"
        @endif
        @if (isset($settings['polycom_lldp_enabled']))
            device.net.lldpEnabled="{{ $settings['polycom_lldp_enabled'] }}"
        @endif
        @if (isset($settings['polycom_cdp_enabled']))
            device.net.cdpEnabled="{{ $settings['polycom_cdp_enabled'] }}"
        @endif
        @if (isset($settings['polycom_dhcp_vlan_discovery']))
            device.dhcp.dhcpVlanDiscUseOpt="{{ $settings['polycom_dhcp_vlan_discovery'] }}"
        @endif
    />

</PHONE_CONFIG>
@break


{{-- ================= Poly model-mac.cfg ================= --}}
@case('model-mac.cfg')
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<polycomConfig>
    <bg bg.color.bm.1.name="{{ $settings['poly_e500_wallpaper'] ?? ''}}" bg.color.selection="2,1" bg.logo="{{ $settings['poly_e500_logo'] ?? ''}}" />
</polycomConfig>
@break

@endswitch