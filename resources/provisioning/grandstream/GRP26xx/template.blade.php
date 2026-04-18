{{-- version: 1.0.4 --}}

@switch($flavor)

{{-- ================= Grandstream GRP26xx cfgmac.xml ================= --}}
@case('cfgmac.xml')

<gs_provision version="1">
	<config version="2">


@foreach ($lines as $line)
    @php  $n = (int)($line['line_number'] ?? 0); @endphp
    @continue($n <= 0)

		<!-- Account Active -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.enable">Yes</item>

		<!-- Account Name -->
		<item name="account.{{ $n }}.name">{{ $line['display_name'] ?? $line['auth_id'] }}</item>

		<!-- SIP Server -->
		<item name="account.{{ $n }}.sip.server.1.address">{{ $line['server_address'] ?? '' }}</item>

		<!-- Outbound Proxy -->
		<item name="account.{{ $n }}.sip.outboundProxy.1.address">{{ $line['outbound_proxy_primary'] ?? '' }}:{{ $line['sip_port'] ?? '' }}</item>

		<!-- BLF Server -->
		<item name="account.{{ $n }}.sip.blf.server"></item>

		<!-- SIP User ID -->
		<item name="account.{{ $n }}.sip.userid">{{ $line['auth_id'] ?? '' }}</item>

		<!-- SIP Authentication ID -->
		<item name="account.{{ $n }}.sip.subscriber.userid">{{ $line['auth_id'] ?? '' }}</item>

		<!-- SIP Authentication Password -->
		<item name="account.{{ $n }}.sip.subscriber.password">{{ $line['password'] ?? '' }}</item>

		<!-- Name -->
		<item name="account.{{ $n }}.sip.subscriber.name">{{ $line['display_name'] ?? $line['auth_id'] }}</item>

		<!-- Voicemail Access Number -->
		<item name="account.{{ $n }}.sip.voicemail.number">{{ $settings['voicemail_number'] ?? '' }}</item>

		<!-- Monitored Access Number -->
		<item name="account.{{ $n }}.sip.voicemail.monitoredNumber"/>

		<!-- Account Display -->
		<!-- User Name, User ID-->
		<item name="account.{{ $n }}.sip.accountDisplay">User Name</item>

		<!-- Account 1 Network Settings -->
		<!-- DNS Mode -->
		<!-- ARecord, SRV, NaptrOrSrv, UseConfiguredIP -->
		<item name="account.{{ $n }}.network.dnsMode">ARecord</item>

		<!-- DNS SRV Fail-over Mode -->
		<!-- Default, SavedOneUntilDNSTTL, SavedOneUntilNoResponse, SavedOneUntilFailbackTimeout -->
		<item name="account.{{ $n }}.network.dnsSRVFailoverMode">Default</item>

		<!-- Register Before DNS SRV Failover -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.network.dnsSRVRegisterBeforeFailover">No</item>

		<!-- Primary IP -->
		<item name="account.{{ $n }}.network.primaryIp"/>

		<!-- Backup IP 1 -->
		<item name="account.{{ $n }}.network.backupIp.1"/>

		<!-- Backup IP 2 -->
		<item name="account.{{ $n }}.network.backupIp.2"/>

		<!-- NAT Traversal -->
		<!-- No, STUN, KeepAlive, UPnP, Auto, VPN -->
		<item name="account.{{ $n }}.network.natTraversal">KeepAlive</item>

		<!-- Support Rport (RFC 3581) -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.network.supportRport">Yes</item>

		<!-- Proxy-Require -->
		<item name="account.{{ $n }}.network.proxyRequire"/>

		<!-- Use SBC -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.network.sbc.enable">No</item>

		<!-- Account 1 SIP Settings -->
		<!-- Account 1 Basic Settings -->
		<!-- TEL URI -->
		<!-- Disabled, UserIsPhone, Enabled -->
		<item name="account.{{ $n }}.sip.telUri">Disabled</item>

		<!-- SIP Registration -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.registration">Yes</item>

		<!-- Unregister on Reboot -->
		<!-- No, Yes, Instance -->
		<item name="account.{{ $n }}.sip.unregisterOnReboot">No</item>

		<!-- Register Expiration (m) -->
		<!-- Number: 0 - 64800 -->
		<item name="account.{{ $n }}.sip.registerExpiration">{{ $line['register_expires']/60 ?? '3600' }}</item>

		<!-- Subscribe Expiration (m) -->
		<!-- Number: 0 - 64800 -->
		<item name="account.{{ $n }}.sip.subscribe.expiration">60</item>

		<!-- Reregister before Expiration (s)  -->
		<!-- Number: 0 - 64800 -->
		<item name="account.{{ $n }}.sip.registerBeforeExpiration">0</item>

		<!-- Enable OPTIONS Keep Alive -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.keepAlive.enable">Yes</item>

		<!-- OPTIONS Keep Alive Interval (s)  -->
		<item name="account.{{ $n }}.sip.keepAlive.interval">30</item>

		<!-- OPTIONS Keep-Alive Max Tries -->
		<item name="account.{{ $n }}.sip.keepAlive.maxLost">3</item>

		<!-- Local SIP Port -->
		<item name="account.{{ $n }}.sip.localPort">5060</item>

		<!-- Registration Retry Wait Time (s) -->
		<item name="account.{{ $n }}.sip.registrationFailureRetryWaitTime">20</item>

		<!-- SIP T1 Timeout -->
		<!-- 0.5 sec - 0.5sec, 1 sec - 1sec, 2 sec - 2sec -->
		<item name="account.{{ $n }}.sip.timer.t1">0.5sec</item>

		<!-- SIP T2 Timeout -->
		<!-- 2 sec - 2sec, 4 sec - 4sec, 8 sec - 8sec -->
		<item name="account.{{ $n }}.sip.timer.t2">4sec</item>

		<!-- SIP Transport -->
		<!-- UDP, TCP, Tls Or Tcp -->
		<item name="account.{{ $n }}.sip.transport">{{ $line['sip_transport'] ?? 'TCP' }}</item>


		<!-- SIP Listening Mode -->
		<!-- Transport_Only, Dual, Dual_BLF_Enforced, Dual_Secured -->
		<item name="account.{{ $n }}.sip.listeningMode">Transport_Only</item>

		<!-- SIP URI Scheme When Using TLS -->
		<!-- sip, sips -->
		<item name="account.{{ $n }}.sip.uriSchemeWhenUsingTls">sips</item>

		<!-- Use Actual Ephemeral Port in Contact with TCP/TLS -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.sip.useActualEphemeralPortInContactWithTcpTls">No</item>

		<!-- Outbound Proxy Mode -->
		<!-- InRoute, NotInRoute, AlwaysSentTo -->
		<item name="account.{{ $n }}.sip.outboundProxy.mode">InRoute</item>

		<!-- Support SIP Instance ID -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.sip.supportInstanceId">Yes</item>

		<!-- Subscribe for MWI -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.subscribe.forMwi">No</item>

		<!-- SUBSCRIBE for Registration -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.subscribe.forRegistration">No</item>

		<!-- Enable 100rel -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.sip.header.100rel">No</item>

		<!-- Callee ID Display -->
		<!-- Auto, Disabled, To Header - Header -->
		<item name="account.{{ $n }}.sip.calleeIdDisplay">Auto</item>

		<!-- Caller ID Display -->
		<!-- Auto, Disabled, From Header - Header -->
		<item name="account.{{ $n }}.sip.callerIdDisplay">Header</item>

		<!-- Add Auth Header On Initial REGISTER -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.header.authOnInitialRegister">No</item>

		<!-- Allow SIP Reset -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.allowSipReset">No</item>

		<!-- Ignore Alert-Info header -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.ring.ignoreSipAlertInfo">No</item>

		<!-- Account 1 SIP Settings -->
		<!-- Account 1 Custom SIP Headers -->
		<!-- Use Privacy Header -->
		<!-- Default, Yes, No -->
		<item name="account.{{ $n }}.sip.header.privacy">Default</item>

		<!-- Use P-Preferred-Identity Header -->
		<!-- Default, Yes, No -->
		<item name="account.{{ $n }}.sip.header.ppi">Default</item>

		<!-- Use X-Grandstream-PBX Header -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.header.xGrandstream">Yes</item>

		<!-- Use P-Access-Network-Info Header -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.header.pani">Yes</item>

		<!-- Use P-Emergency-Info Header -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.header.pei">Yes</item>

		<!-- Use X-switch-info Header -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.header.xSwitchInfo">No</item>

		<!-- Use MAC Header -->
		<!-- No - No, Yes except REGISTER only - YesExceptRegister, Yes to all SIP - YesToAllSip -->
		<item name="account.{{ $n }}.sip.header.mac">No</item>

		<!-- Account 1 SIP Settings -->
		<!-- Account 1 Advanced Features -->
		<!-- Line-seize Timeout -->
		<!-- Number: 15 - 60 -->
		<item name="account.{{ $n }}.sip.lineSeizeTimeout">15</item>

		<!-- Eventlist BLF URI -->
		<item name="account.{{ $n }}.sip.blf.eventlist.uri"/>

		<!-- Auto Provision Eventlists -->
		<!-- Disabled, BLFEventList, PresenceEventlist -->
		<item name="account.{{ $n }}.sip.blf.eventlist.autoProvision">Disabled</item>

		<!-- Conference URI -->
		<item name="account.{{ $n }}.sip.conferenceUri"/>

		<!-- Music On Hold URI -->
		<item name="account.{{ $n }}.sip.musicOnHoldUri"/>

		<!-- BLF Call-pickup  -->
		<!-- Auto, Force, Disabled -->
		<item name="account.{{ $n }}.sip.blf.callPickup.forcePrefix">Auto</item>

		<!-- BLF Call-pickup Prefix -->
		<item name="account.{{ $n }}.sip.blf.callPickup.prefix">**</item>

		<!-- Call Pickup Barge-In Code -->
		<item name="account.{{ $n }}.sip.callPickupBargeinCode"/>

		<!-- PUBLISH for Presence -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.publishForPresence">No</item>

		<!-- Omit charset=UTF-8 in MESSAGE -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.omitCharsetUtf8InMessage">No</item>

		<!-- Allow Unsolicited REFER -->
		<!-- Disabled, Enabled, EnabledOrForceAuth -->
		<item name="account.{{ $n }}.sip.allowUnsolicitedRefer">Disabled</item>

		<!-- Special Feature -->
		<!-- Standard, NortelMCS, Broadsoft, CBCOM, RNK, Sylantro, HuawaiIMS, Phonepower, UCMCallCenter, Zoom -->
		<item name="account.{{ $n }}.sip.specialFeature">Standard</item>

		<!-- Broadsoft Call Center -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.broadsoft.callCenter">No</item>

		<!-- Hoteling Event -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.broadsoft.hoteling">No</item>

		<!-- Call Center Status -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.broadsoft.callCenterStatus">No</item>

		<!-- Broadsoft Executive Assistant -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.broadsoft.executiveAssistant">No</item>

		<!-- Feature Key Synchronization -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.syncFeatureKey">No</item>

		<!-- Broadsoft Call Park -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.broadsoft.callPark">No</item>

		<!-- Account 1 SIP Settings -->
		<!-- Account 1 Session Timer -->
		<!-- Enable Session Timer -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.sessionTimer.enable">No</item>

		<!-- Session Expiration (s) -->
		<!-- Session Timer is disabled if the value is 0 -->
		<item name="account.{{ $n }}.sip.sessionTimer.expiration">180</item>

		<!-- Min-SE(s) -->
		<item name="account.{{ $n }}.sip.minimumSE">90</item>

		<!-- Caller Request Timer -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.sip.sessionTimer.requestTimer.caller">No</item>

		<!-- Callee Request Timer -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.sip.sessionTimer.requestTimer.callee">No</item>

		<!-- Force Timer -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.sip.sessionTimer.force">No</item>

		<!-- UAC Specify Refresher -->
		<!-- Omit, UAC, UAS -->
		<item name="account.{{ $n }}.sip.sessionTimer.refresher.uacSpecify">UAC</item>

		<!-- UAS Specify Refresher -->
		<!--UAC - 1, UAS - 2 -->
		<item name="account.{{ $n }}.sip.sessionTimer.refresher.uasSpecify">1</item>

		<!-- Force INVITE -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.sip.sessionTimer.forceInvite">No</item>

		<!-- Account 1 SIP Settings -->
		<!-- Account 1 Security Settings -->
		<!-- Check Domain Certificates -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.validate.domainCertificates">No</item>

		<!-- Trusted Domain Name List -->
		<!-- String-->
		<item name="account.{{ $n }}.sip.validate.trustedDomains"/>

		<!-- Validate Certification Chain 	 -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.validate.certificationChain">No</item>

		<!-- Validate Incoming SIP Messages -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.validate.incomingMessage">No</item>

		<!-- Check SIP User ID for Incoming INVITE  -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.validate.userIdforInvite">No</item>

		<!-- Accept Incoming SIP from Proxy Only  -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.validate.incomingServer">Yes</item>

		<!-- Authenticate Incoming INVITE -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.sip.authenticateIncomingInvite">No</item>

		<!-- Account 1 Audio Settings -->
		<!-- Codec Settings -->
		<!-- Preferred Vocoder -->
		<!-- PCMU, PCMA, G.726-32, G.723.1, G.722, G.729A/B, iLBC, OPUS -->
		<item name="account.{{ $n }}.codec.choice.1">PCMU</item>
		<item name="account.{{ $n }}.codec.choice.2">PCMA</item>
		<item name="account.{{ $n }}.codec.choice.3">G.722</item>

		<!-- Use First Matching Vocoder in 200OK SDP -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.codec.useFirstMatch">No</item>

		<!-- Codec Negotiation Priority -->
		<!-- Caller, Callee -->
		<item name="account.{{ $n }}.codec.negotiatePriority">Callee</item>

		<!-- Hide Vocoder -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.codec.hideLabel">No</item>

		<!-- Configures to enable or disable multiple m lines in SDP -->
		<!-- Invert_Yes_No, Yes - No, No - Yes -->
		<item name="account.{{ $n }}.audio.multipleMLineInSDP">No</item>

		<!-- SRTP Mode -->
        <!-- Disabled, EnabledButNotForced, EnabledAndForced, Optional -->
        <item name="account.{{ $n }}.audio.srtpMode">{{ $lines[$n]['sip_transport'] === 'Tls Or Tcp' ? 'EnabledButNotForced' : 'Disabled' }}</item>

		<!-- SRTP Key Length -->
		<!-- AES128And256Bit, AES128Bit, AES256Bit -->
		<item name="account.{{ $n }}.audio.srtpKeyLength">AES128And256Bit</item>

		<!-- Crypto Life Time -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.audio.cryptoLifeTime">Yes</item>

		<!-- Symmetric RTP -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.audio.symmetricRTP">No</item>

		<!-- Silence Suppression -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.audio.silenceSuppression">No</item>

		<!-- Jitter Buffer Type -->
		<!-- Fixed, Adaptive -->
		<item name="account.{{ $n }}.audio.jitterBufferType">Adaptive</item>

		<!-- Jitter Buffer Length -->
		<!-- 100ms, 200ms, 300ms, 400ms, 500ms, 600ms, 700ms, 800ms -->
		<item name="account.{{ $n }}.audio.jitterBufferLength">300ms</item>

		<!-- Voice Frames per TX -->
		<!-- Number: 1 - 64 -->
		<item name="account.{{ $n }}.audio.voiceFramePerTX">2</item>

		<!-- G723 Rate -->
		<!-- 6.3kbpsEncodingRate, 5.3kbpsEncodingRate -->
		<item name="account.{{ $n }}.codec.g723rate">5.3kbpsEncodingRate</item>

		<!-- G.726-32 Packing Mode -->
		<!-- ITU, IETF -->
		<item name="account.{{ $n }}.codec.g723.32.packingMode">ITU</item>

		<!-- iLBC Frame Size -->
		<!-- 20ms, 30ms -->
		<item name="account.{{ $n }}.codec.iLBC.frameSize">30ms</item>

		<!-- iLBC Payload Type -->
		<item name="account.{{ $n }}.codec.payloadType.ilbc">97</item>

		<!-- OPUS Payload Type -->
		<item name="account.{{ $n }}.codec.payloadType.opus">123</item>

		<!-- DTMF Payload Type -->
		<item name="account.{{ $n }}.codec.payloadType.dtmf">101</item>

		<!-- Send DTMF -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.dtmf.sendInAudio">No</item>
		<item name="account.{{ $n }}.dtmf.sendInRtp">Yes</item>
		<item name="account.{{ $n }}.dtmf.sendInSip">No</item>

		<!-- DTMF Delay -->
		<!-- Number: 100 - 250 -->
		<item name="account.{{ $n }}.dtmf.delay">250</item>

		<!-- Account 1 Call Settings -->
		<!-- Early Dial -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.call.earlyDial">No</item>

		<!-- Dial Plan Prefix -->
		<item name="account.{{ $n }}.call.dialplanPrefix"/>

		<!-- Dial Plan -->
		@if (array_key_exists('grandstream_dial_plan', $settings))
            <item name="account.{{ $n }}.call.dialplan">{{ $settings['grandstream_dial_plan'] }}</item>
        @endif

		<!-- Bypass Dial Plan -->
		<!-- contact,incoming,outgoing,dialing,Mpk,api -->
		<item name="account.{{ $n }}.call.dialplanBypass">Mpk</item>

		<!-- Call Log -->
		<!-- All, IncomingAndOutgoing, Disable -->
		<item name="account.{{ $n }}.call.callLog">All</item>

		<!-- Send Anonymous -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.call.sendAnonymous">No</item>

		<!-- Anonymous Call Rejection -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.call.rejectAnonymousCall">No</item>

		<!--Auto Answer -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.call.autoAnswer">No</item>

		<!-- Auto Answer Numbers -->
		<item name="account.{{ $n }}.call.autoAnswerNumber"/>

		<!-- Refer-To Use Target Contact -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.call.referToUseTargetContact">No</item>

		<!-- Transfer on Conference Hangup -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.call.transferOnConferenceHangup">No</item>

		<!-- Disable Recovery on Blind Transfer -->
		<!-- Invert_Yes_No, Yes - No, No - Yes -->
		<item name="account.{{ $n }}.call.recoveryOnBlindTransfer"/>

		<!-- Blind Transfer Wait Timeout -->
		<!-- Number: 30 - 300 -->
		<item name="account.{{ $n }}.call.blindTransferTimeout">30</item>

		<!-- No Key Entry Timeout (s) -->
		<!-- Number: 1 - 15 -->
		<item name="account.{{ $n }}.call.noKeyEntryTimeout">4</item>

		<!-- Use as Dial Key -->
		<!-- Disabled, Pound, Star -->
		<item name="account.{{ $n }}.call.keyAsSend">Pound</item>

		<!-- On Hold Reminder Tone -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.call.onHoldReminderTone">Yes</item>

		<!-- RFC2543 Hold -->
		<!-- Yes, No -->
		<item name="account.{{ $n }}.call.rfc2543Hold.enable">No</item>

		<!-- Hide Dialing Password -->
		<!-- Prefix for dialing password -->
		<item name="account.{{ $n }}.call.hidePassword.prefix"/>

		<!-- Password length -->
		<item name="account.{{ $n }}.call.hidePassword.length">0</item>

		<!-- Disable Call Waiting -->
		<!-- Default, Yes, No -->
		@if (array_key_exists('grandstream_call_waiting', $settings))
            <item name="account.{{ $n }}.call.callWaiting">{{ $settings['grandstream_call_waiting'] }}</item>
        @endif

		<!-- Account Ringtone -->
		<item name="account.{{ $n }}.ring.ringtone">5</item>

		<!-- Matching Incoming Caller ID. Matching Rule 1 -->
		<item name="account.{{ $n }}.ring.match.1.callerId"/>

		<!-- Distinctive Ring Tone -->
		<item name="account.{{ $n }}.ring.match.1.ringtone">5</item>

		<!-- Match Incoming Caller ID. Matching Rule 2 -->
		<item name="account.{{ $n }}.ring.match.2.callerId"/>

		<!-- Distinctive Ring Tone -->
		<item name="account.{{ $n }}.ring.match.2.ringtone">5</item>

		<!-- Match Incoming Caller ID. Matching Rule 3 -->
		<item name="account.{{ $n }}.ring.match.3.callerId"/>

		<!-- Distinctive Ring Tone -->
		<item name="account.{{ $n }}.ring.match.3.ringtone">5</item>

		<!-- Match Incoming Caller ID. Matching Rule 4 -->
		<item name="account.{{ $n }}.ring.match.4.callerId"/>

		<!-- Distinctive Ring Tone -->
		<item name="account.{{ $n }}.ring.match.4.ringtone">5</item>

		<!-- Match Incoming Caller ID. Matching Rule 5 -->
		<item name="account.{{ $n }}.ring.match.5.callerId"/>

		<!-- Distinctive Ring Tone -->
		<item name="account.{{ $n }}.ring.match.5.ringtone">5</item>

		<!-- Match Incoming Caller ID. Matching Rule 6 -->
		<item name="account.{{ $n }}.ring.match.6.callerId"/>

		<!-- Distinctive Ring Tone -->
		<item name="account.{{ $n }}.ring.match.6.ringtone">5</item>

		<!-- Match Incoming Caller ID. Matching Rule 7 -->
		<item name="account.{{ $n }}.ring.match.7.callerId"/>

		<!-- Distinctive Ring Tone -->
		<item name="account.{{ $n }}.ring.match.7.ringtone">5</item>

		<!-- Match Incoming Caller ID. Matching Rule 8 -->
		<item name="account.{{ $n }}.ring.match.8.callerId"/>

		<!-- Distinctive Ring Tone -->
		<item name="account.{{ $n }}.ring.match.8.ringtone">5</item>

		<!-- Match Incoming Caller ID. Matching Rule 9 -->
		<item name="account.{{ $n }}.ring.match.9.callerId"/>

		<!-- Distinctive Ring Tone -->
		<item name="account.{{ $n }}.ring.match.9.ringtone">5</item>

		<!-- Match Incoming Caller ID. Matching Rule 10 -->
		<item name="account.{{ $n }}.ring.match.10.callerId"/>

		<!-- Distinctive Ring Tone -->
		<item name="account.{{ $n }}.ring.match.10.ringtone">5</item>

		<!-- Ring Timeout -->
		<!-- Number: 10 - 300 -->
		<item name="account.{{ $n }}.ring.timeout">60</item>

		<!-- ############################################################### -->
		<!-- # Account 1/Intercom Settings -->
		<!-- ############################################################### -->
		<!-- # Allow Auto Answer by Call-Info/Alert-Info. 0 - No, 1 - Yes. Default is Yes -->
		<!-- # Number: 0, 1 -->
		<!-- # Mandatory -->
		<item name="account.{{ $n }}.intercom.allowAutoAnswer">Yes</item>

		<!-- Allow Barging by Call-Info/Alert-Info -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.intercom.allowBargingByCallInfo">No</item>

		<!-- Mute on Answer Intercom Call -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.intercom.muteOnAnswerIntercom">No</item>

		<!-- Play Warning Tone for Auto Answer Intercom  -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.intercom.playWarningToneForAutoAnswer">Yes</item>

		<!-- # Custom Alert-Info for Auto Answer. -->
		<!-- # String -->
		<item name="account.{{ $n }}.intercom.customCallInfoForAutoAnswer"/>

		<!-- ############################################################### -->
		<!-- # Account 1/Feature Codes -->
		<!-- ############################################################### -->
		<!-- #Enable Local Call Features. Default is Yes -->
		<!-- #Mandatory -->
		<!-- No, Yes -->
		<item name="account.{{ $n }}.featureCodes.callFeatures">No</item>

		<!-- DND Call Feature On -->
		<item name="account.{{ $n }}.featureCodes.dndOn"/>

		<!-- DND Call Feature Off  -->
		<item name="account.{{ $n }}.featureCodes.dndOff"/>

		<!-- #Call Forward Always - On -->
		<!-- # String -->
		<item name="account.{{ $n }}.featureCodes.callForwardUnconditionallyOn"/>

		<!-- #Call Forward Always - Off -->
		<!-- # String -->
		<item name="account.{{ $n }}.featureCodes.callForwardUnconditionallyOff"/>

		<!-- #Call Forward Always- Target -->
		<!-- # String -->
		<item name="account.{{ $n }}.featureCodes.callForwardUnconditionallyTarget"/>

		<!-- #Call Forward Busy - On -->
		<!-- # String -->
		<item name="account.{{ $n }}.featureCodes.callForwardBusyOn"/>

		<!-- #Call Forward Busy - Off -->
		<!-- # String -->
		<item name="account.{{ $n }}.featureCodes.callForwardBusyOff"/>

		<!-- #Call Forward Busy - Target -->
		<!-- # String -->
		<item name="account.{{ $n }}.featureCodes.callForwardBusyTarget"/>

		<!-- #Call Forward No Answer - On -->
		<!-- #String -->
		<item name="account.{{ $n }}.featureCodes.callForwardDelayedOn"/>

		<!-- #Call Forward No Answer - Off -->
		<!-- #String -->
		<item name="account.{{ $n }}.featureCodes.callForwardDelayedOff"/>

		<!-- #Call Forward No Answer - Target -->
		<!-- #String -->
		<item name="account.{{ $n }}.featureCodes.callForwardDelayedTarget"/>

		<!-- #Delayed Call Forward Wait Time (in seconds). Default is 12 -->
		<!-- #Number: 1 - 120 -->
		<!-- #Mandatory -->
		<item name="account.{{ $n }}.featureCodes.delayedCallForwardWaitTime">20</item>

@endforeach

@php
    $grandstreamKeyModes = [
        'none' => 'None',
        'speed dial' => 'SpeedDial',
        'blf' => 'BLF',
        'speed dial via active account' => 'SpeedDialViaActiveAccount',
        'voicemail' => 'VoiceMail',
        'transfer' => 'Transfer',
        'call park' => 'CallPark',
        'intercom' => 'Intercom',
        'monitored call park' => 'MonitoredCallPark',
        'line' => 'Line',
        'sharedline' => 'SharedLine',
        'dial dtmf' => 'DialDTMF',
    ];

    $maxVpkId = collect($main_keys)
        ->pluck('id')
        ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
        ->map(fn ($id) => (int) $id)
        ->max() ?? 0;

    $maxMpkId = collect($multi_purpose_keys)
        ->pluck('id')
        ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
        ->map(fn ($id) => (int) $id)
        ->max() ?? 0;
@endphp

{{-- ====================================================================== --}}
{{-- Virtual Multi-Purpose Keys / VPKs                                      --}}
{{-- pks.vpk.{i}.account is NUMERIC: 0 = Account1, 1 = Account2, ...        --}}
{{-- ====================================================================== --}}
@foreach ($main_keys as $k)
    @php
        $type = strtolower(trim((string) ($k['type'] ?? 'none')));
    @endphp

    <!-- ################################################################## -->
    <!-- # VPK {{ $k['id'] }} -->
    <!-- ################################################################## -->
    <item name="pks.vpk.{{ $k['id'] }}.keyMode">{{ $grandstreamKeyModes[$type] ?? 'None' }}</item>
    <item name="pks.vpk.{{ $k['id'] }}.account">{{ (int) ($k['line'] ?? 0) }}</item>
    <item name="pks.vpk.{{ $k['id'] }}.description">{{ $k['label'] ?? '' }}</item>
    <item name="pks.vpk.{{ $k['id'] }}.value">{{ $k['value'] ?? '' }}</item>
@endforeach

{{-- Clear unused VPKs --}}
@for ($id = $maxVpkId + 1; $id <= 30; $id++)
    <item name="pks.vpk.{{ $id }}.keyMode">None</item>
    <item name="pks.vpk.{{ $id }}.account">0</item>
    <item name="pks.vpk.{{ $id }}.description"></item>
    <item name="pks.vpk.{{ $id }}.value"></item>
@endfor


{{-- ====================================================================== --}}
{{-- Physical Multi-Purpose Keys / MPKs                                     --}}
{{-- pks.mpk.{i}.account uses STRING: Account1, Account2, ...               --}}
{{-- MPKs do NOT support line/sharedline                                    --}}
{{-- ====================================================================== --}}
@foreach ($multi_purpose_keys as $k)
    @php
        $type = strtolower(trim((string) ($k['type'] ?? 'none')));
        $mpkMode = in_array($type, ['line', 'sharedline'], true)
            ? 'None'
            : ($grandstreamKeyModes[$type] ?? 'None');
    @endphp

    <!-- ################################################################## -->
    <!-- # MPK {{ $k['id'] }} -->
    <!-- ################################################################## -->
    <item name="pks.mpk.{{ $k['id'] }}.keyMode">{{ $mpkMode }}</item>
    <item name="pks.mpk.{{ $k['id'] }}.account">Account{{ ((int) ($k['line'] ?? 0)) + 1 }}</item>
    <item name="pks.mpk.{{ $k['id'] }}.description">{{ $k['label'] ?? '' }}</item>
    <item name="pks.mpk.{{ $k['id'] }}.value">{{ $k['value'] ?? '' }}</item>
@endforeach

{{-- Clear unused MPKs --}}
@for ($id = $maxMpkId + 1; $id <= 30; $id++)
    <item name="pks.mpk.{{ $id }}.keyMode">None</item>
    <item name="pks.mpk.{{ $id }}.account">Account1</item>
    <item name="pks.mpk.{{ $id }}.description"></item>
    <item name="pks.mpk.{{ $id }}.value"></item>
@endfor

		<!-- ################################################################### -->
		<!-- ## Network/Basic Settings                                        ## -->
		<!-- ################################################################### -->
		<!-- Ethernet Settings -->
		<!-- Preferred Internet Protocol -->
		<!-- BothAndPreferIPv4, BothAndPreferIPv6, IPv4Only, IPv6Only -->
        @if (array_key_exists('grandstream_internet_protocol', $settings))
            <item name="network.internetProtocol">{{ $settings['grandstream_internet_protocol'] }}</item>
        @else
            <item name="network.internetProtocol">IPv4Only</item>
        @endif

		<!-- IPv4 Address Type -->
		<!-- DHCP, StaticIP, PPPoE -->
        @if (array_key_exists('grandstream_ip_address_type', $settings))
            <item name="network.port.eth.1.type">{{ $settings['grandstream_ip_address_type'] }}</item>
        @else
            <item name="network.port.eth.1.type">DHCP</item>
        @endif

		<!-- ########################################## -->
		<!-- # DHCP -->
		<!-- ########################################## -->
		<!-- Host Name(Option 12) -->
		<item name="network.dhcp.hostName"/>

		<!-- Vendor Class ID(Option 60) -->
		<!-- <item name="network.dhcp.vendorID"></item> -->

		<!-- ########################################## -->
		<!-- # PPPoE -->
		<!-- ########################################## -->
		<!-- # PPPoE Account ID -->
		<!-- # String -->
		<!-- PPPoE Account ID -->
		<item name="network.port.eth.1.pppoe.account"/>

		<!-- PPPoE Password -->
		<item name="network.port.eth.1.pppoe.password"/>

		<!-- # PPPoE Service Name -->
		<!-- # String  -->
		<item name="network.pppoe.serviceName"/>


		<!-- Network/Advanced Settings  -->
		<!-- # 802.1X Mode. 0 - Disable, 1 - EAP-MD5. 2 - EAP-TLS, 3 - EAP-PEAPv0/MSCHAPv2. Default is 0 -->
		<!-- # Number: 0, 1, 2, 3 -->
		<!-- # Mandatory -->
		<!-- 802.1x Mode -->
		<!-- Disable, EAP_MD5, EAP_TLS, EAP_PEAPv0OrMSCHAPv2 -->
		<item name="network.802.1x.mode">Disable</item>
		<!-- 802.1x Identity -->
		<item name="network.802.1x.identity"/>
		<!-- MD5 Password -->
		<item name="network.md5Password"/>

		<!-- # 802.1X CA Certificate -->
		<!-- # String -->
		<item name="network.802.1x.cert.root"/>

		<!-- # 802.1X Client Certificate -->
		<!-- # String -->
		<item name="network.802.1x.cert.client"/>

		<!-- # HTTP Proxy -->
		<!-- # String -->
		<!-- Must include port number -->
		<item name="network.proxy.http"/>

		<!-- # HTTPS Proxy -->
		<!-- # String -->
		<item name="network.proxy.https"/>

		<!-- # Bypass Proxy For -->
		<!-- # String -->
		<item name="network.proxy.bypassAddress"/>

		<!-- # Layer 3 QoS for SIP -->
		<!-- # Number:0 - 63 -->
		<!-- # Mandatory -->
		<item name="network.qos.forSip">26</item>

		<!-- # Layer 3 QoS for RTP -->
		<!-- # Number:0 - 63 -->
		<!-- # Mandatory -->
		<item name="network.qos.forRtp">46</item>

		<!-- # Release DHCP On Reboot. Yes or No -->
		<!-- # Mandatory -->
		<item name="network.dhcp.enableRelease">No</item>

		<!-- # Enable DHCP VLAN. Yes or No -->
		<!-- # Mandatory -->
		<item name="network.dhcp.enableVlan">No</item>

		<!-- # Enable Manual VLAN Configuration. Yes or No. -->
		<!-- # Mandatory -->
		<item name="network.port.eth.1.vlan.enable">No</item>

		<!-- # Layer 2 QoS. 802.1Q/VLAN Tag (VLAN classification for RTP). Default is 0 -->
		<!-- Layer 2 QoS 802.1Q/VLAN Tag (Ethernet) -->
		<!-- Number: 0 - 4095 -->
		<item name="network.port.eth.1.vlan.tag">0</item>

		<!-- 802.1p Priority Value (Ethernet) -->
		<!-- Number: 0 - 7 -->
		<item name="network.port.eth.1.vlan.priority">0</item>

		<!-- PC Port Mode -->
		<!-- Enabled, Disabled, Mirrored -->
		<item name="network.port.pc.mode">Enabled</item>

		<!-- PC Port VLAN Tag  -->
		<item name="network.port.pc.vlan.tag">0</item>

		<!-- PC Port Priority Value -->
		<item name="network.port.pc.vlan.priority">0</item>

		<!-- Enable CDP -->
		<!-- Yes, No -->
		<item name="network.cdp">No</item>

		<!-- Enable LLDP -->
		<!-- Yes, No -->
		<item name="network.lldp.enable">No</item>

		<!-- LLDP TX Interval (s)  -->
		<item name="network.lldp.txInterval">60</item>

		<!-- # Maximum Transmission Unit(MTU). Default is 1500 -->
		<!-- # Number: 576 - 1500 -->
		<item name="network.mtu">1500</item>
		
	    <!-- #################################################################################### -->
		<!-- ##  Network /Bluetooth Settings    GRP2614/GRP2615/GRP2616/GRP2624/GRP2634/GRP2670/GRP2650   -->
		<!-- #################################################################################### -->
		<!-- # Bluetooth Power.  -->
		<!-- # Off, On, OffAndHideMenuFromLCD -->
		<item name="bluetooth.power">On</item>

		<!-- # Handsfree Mode. -->
		<!-- # Off or On -->
		<item name="bluetooth.handsfreeMode"/>

		<!-- # Connection In Public Mode. -->
		<!-- # Default (0), Always On (1) -->
		<item name="bluetooth.connectionInPulicMode"/>

		<!-- # Bluetooth Name. -->
		<!-- # String -->
		<item name="bluetooth.name"/>


		<!-- ############################################################################## -->
		<!-- ##  Maintenance/ Web Access     ## -->
		<!-- ############################################################################## -->

		<!-- # End User Password -->
		<!-- # String: a-z, A-Z, 0-9 -->
        @if (array_key_exists('user_password', $settings))
            <item name="users.user.password">{{ $settings['user_password'] }}</item>
        @endif

		<!-- # Admin Password for web interface -->
		<!-- # String: a-z, A-Z, 0-9 -->
        @if (array_key_exists('admin_password', $settings))
            <item name="users.admin.password">{{ $settings['admin_password'] }}</item>
        @endif

		<!-- ############################################################################## -->
		<!-- ##  Maintenance/Upgrade and Provisioning -->
		<!-- ############################################################################## -->

		<!-- # Firmware Upgrade and Provisioning -->
		<!-- AlwaysCheck (default), CheckWhenChange, SkipCheck -->
		<item name="provisioning.firmware.checkCondition">AlwaysCheck</item>

		<!-- # Always Authenticate Before Challenge. Yes or No -->
		<item name="provisioning.alwaysAuthenticateBeforeChallenge">No</item>

		<!-- # Validate Hostname in Certificate. No, Yes. -->
		<item name="provisioning.validateHostnameInCertificate">No</item>


		<!-- # Firmware Upgrade Confirmation. No or Yes -->
		<item name="provisioning.firmware.confirm.enable">Yes</item>

		<!-- Config Upgrade Via -->
		<!-- TFTP, HTTP, HTTPS -->
		<item name="provisioning.config.protocol">HTTPS</item>

		<!-- Config Server Path -->
        <item name="provisioning.config.serverPath">{{ trim(str_replace(['http://', 'https://'], '', $settings['provision_base_url'] ?? ''), " /") }}</item>
        <!--<item name="provisioning.config.serverPath">portal.us.nemerald.net/prov</item>-->

		<!-- Config Server User Name -->
		<item name="provisioning.config.username">{{ $settings['http_auth_username'] ?? '' }}</item>
		<!-- Config Server Password -->
		<item name="provisioning.config.password">{{ $settings['http_auth_password'] ?? '' }}</item>
		
		<!-- Firmware Upgrade via -->
		<!-- TFTP, HTTP, HTTPS, FTP,FTPS -->
		<item name="provisioning.firmware.protocol">HTTPS</item>
		<!-- Firmware Server Path -->
        @if (array_key_exists('grandstream_firmware_path', $settings))
            <item name="provisioning.firmware.serverPath">{{ trim(str_replace(['http://', 'https://'], '', $settings['grandstream_firmware_path'] ?? ''), " /") }}</item>
        @endif

		<!-- Firmware Server User Name -->
		<item name="provisioning.firmware.username"/>
		<!-- Firmware Server Password -->
		<item name="provisioning.firmware.password"/>
		<!-- Firmware File Prefix -->
		
		<!-- # 3CX Auto Provision. No or Yes. -->
		<item name="provisioning.3cxAutoProvision">Yes</item>
		
		<!-- Automatic Upgrade -->
		<!-- No - No (default), Check Every Day - YesUpgradeHourOfDay, Check Every Week - YesUpgradeDayOfWeek, Check at a Period Time - YesUpgradeMin -->
		<item name="provisioning.auto.mode">No</item>

		<!-- Automatic Upgrade Check Interval (m) -->
		<!-- Number: 60 - 5256000. Default value is 10080 -->
		<item name="provisioning.auto.minute">10080</item>

		<!-- # Start Upgrade at Random Time. No or Yes -->
		<item name="provisioning.auto.randomTime.enable">No</item>

		<!-- Starting - Ending Hour of the Day (0-23) -->
		<item name="provisioning.auto.hour">1</item>
		<item name="provisioning.auto.endHour"/>

		<!-- Day of the Week -->
		<!-- 0, 1 (default), 2, 3, 4, 5, 6 -->
		<!-- Multiple days example: 0/1/2/3/4 -->
		<item name="provisioning.auto.day">1</item>

		<!-- #	Disable SIP NOTIFY Authentication. Yes or No -->
		<item name="sip.notify.challenge">No</item>
		
		<!-- ############################################################################## -->
		<!-- ##  Maintenance/Syslog -->
		<!-- ############################################################################## -->
		<!-- Maintenance - System Diagnosis -->
		<!-- Syslog Protocol -->
		<!-- UDP, SSL_TLS -->
		<item name="maintain.syslog.protocol">UDP</item>

		<!-- Syslog Server -->
		@if (array_key_exists('grandstream_syslog_server', $settings))
            <item name="maintain.syslog.server">{{ $settings['grandstream_syslog_server'] }}</item>
        @endif

		<!-- Syslog Level -->
		<!-- None, Debug, Info, Warning, Error -->
		@if (array_key_exists('grandstream_syslog_level', $settings))
            <item name="maintain.syslog.level">{{ $settings['grandstream_syslog_level'] }}</item>
        @endif

		<!-- Syslog Keyword Filter -->
		<item name="maintain.syslog.keywordFiltering"/>

		<!-- # Send SIP Log.Yes or No -->
		@if (array_key_exists('grandstream_send_sip_log', $settings))
            <item name="maintain.syslog.sendSipLog">{{ $settings['grandstream_send_sip_log'] }}</item>
        @endif

		<!-- # Show Network Warning Message. Yes or No-->
		<item name="network.showInternetDownWarning">No</item>

		<!-- # Auto Recover from Abnormal. Yes or No -->
		<item name="maintain.autoRecover">Yes</item>

		<!-- # USB Console Log. Yes or No -->
		<item name="maintain.usbConsoleLog">No</item>
		
		<!-- ############################################################################## -->
		<!-- ##  Maintenance/Security Settings/Security   ## -->
		<!-- ############################################################################## -->
		<!-- Configuration via Keypad Menu -->
		<!-- Unrestricted, BasicSettingsOnly, Constraint Mode, LockedMode -->
		<item name="security.configurationViaKeypadMenu">Unrestricted</item>

		<!-- Factory Reset Security Level -->
		<!-- Default, AlwaysRequirePassword, NoPasswordRequired -->
		<item name="security.factoryResetSecurityLevel">Default</item>

		<!-- # Validate Server Certificates. Yes or No -->
		<!--<item name="security.validate.serverCertificate">No</item>-->

		<!-- SIP TLS Certificate -->
		<item name="security.certificate"/>

		<!-- SIP TLS Private Key  -->
		<item name="security.key"/>

		<!-- SIP TLS Private Key Password -->
		<item name="security.password"/>

		<!-- Web Access Method -->
		<!-- HTTP, HTTPS, Both, Disabled -->
		<item name="security.webaccessmode">HTTPS</item>

		<!-- # Enable User Web Access.  Yes or No -->
		<item name="security.webAccess.user.enable">Yes</item>

		<!-- # HTTP Web Port. Default is 80 -->
		<item name="network.web.port.http">80</item>

		<!-- # HTTPS Web Port. Default is 443 -->
		<item name="network.web.port.https">443</item>

		<!-- Disable SSH -->
		<!-- Invert_Yes_No, Yes - No, No - Yes -->
		<item name="security.ssh">Yes</item>

		<!-- # SSH Port. Default is 22 -->
		<!-- # Number: 22 - 65535 -->
		<item name="security.ssh.port">22</item>

		<!-- # Web/Keypad/Restrict mode Lockout Duration (0-60 minutes). Default is 5 -->
		<!-- # Number: 0-60 -->
		<item name="security.webKeypadRestrictModeLockoutDuration">5</item>

		<!-- # Web Session Timeout(in minutes) -->
		<!-- # Number: 2 - 60. Default is 10 -->
		<item name="security.webAccess.session.timeout">10</item>

		<!-- # Web Access Attempt Limit -->
		<!-- # Number: 1 - 10. Default is 5 -->
		<item name="security.webAccess.attemptLimit">5</item>

		<!-- Minimum TLS Version -->
		<!-- UNLIMITED, TLS_1_0, TLS_1_1, TLS_1_2 -->
		<item name="security.minimum.TLS.version">TLS_1_1</item>

		<!-- Maximum TLS Version -->
		<!-- UNLIMITED, TLS_1_0, TLS_1_1, TLS_1_2 -->
		<item name="security.maximum.TLS.version">UNLIMITED</item>

		<!-- Enable/Disable Weak Ciphers -->
		<!-- 0 - Enable Weak TLS Ciphers Suites, 1 - Disable Symmetric Encryption RC4/DES/3DES, 2 - Disable Symmetric Encryption SEED, 3 - Disable All Weak Symmetric Encryption, 4 - Disable Symmetric Authentication MD5, 5 - Disable All Weak TLS Cipher Suites -->
		<item name="security.maximum.TLS.version">0</item>
		
		<!-- ############################################################################## -->
		<!-- ##  Phonebook/Phonebook Management -->
		<!-- ############################################################################## -->
		<!-- Enable Phonebook XML Download -->
		<!-- Disabled, EnabledUseHTTP, EnabledUseTFTP, EnabledUseHTTPS -->
		<item name="phonebook.download.mode">EnabledUseHTTPS</item>

		<!-- HTTP/HTTPS User Name  -->
		@if (array_key_exists('grandstream_phonebook_username', $settings))
            <item name="phonebook.download.username">{{ $settings['grandstream_phonebook_username'] }}</item>
        @endif

		<!-- HTTP/HTTPS Password  -->
		@if (array_key_exists('grandstream_phonebook_password', $settings))
            <item name="phonebook.download.password">{{ $settings['grandstream_phonebook_password'] }}</item>
        @endif

        <!-- Phonebook XML Server Path -->
        @if (array_key_exists('grandstream_phonebook_server', $settings))
            <item name="phonebook.download.server">{{ $settings['grandstream_phonebook_server'] }}</item>
        @endif
        
        <!-- Phonebook Download Interval  -->
        <!-- 0, 120, 240, 360, 480, 720 -->
        @if (array_key_exists('grandstream_phonebook_download_interval', $settings))
            <item name="phonebook.download.interval">{{ $settings['grandstream_phonebook_download_interval'] }}</item>
        @endif

		<!-- # Remove Manually-edited entries on Download. Yes or No -->
		<item name="phonebook.download.removeEditedEntries">Yes</item>

		<!-- # Import Group Method. Replace, Append. -->
		<item name="phonebook.import.group.method">Replace</item>

		<!-- Sort Phonebook by -->
		<!-- LastName, FirstName -->
		<item name="phonebook.sortBy">LastName</item>

		<!-- Phonebook Key Function -->
		<!-- Default, LDAPSearch, LocalPhonebook, LocalGroup, BroadsoftPhonebook, Blacklist, Whitelist -->
		<item name="phonebook.keyFunction">Default</item>

		<!-- Default search mode -->
		<!-- QuickMatch, ExactMatch -->
		<item name="phonebook.defaultSearchMode">QuickMatch</item>


		<!-- ############################################################################## -->
		<!-- ##  Maintenance/TR-069 -->
		<!-- ############################################################################## -->
		<!-- Enable TR-069 -->
		<!-- Yes, No -->
		<item name="tr069.enable">Yes</item>

		<!-- ACS URL -->
		<item name="tr069.url">https://acs.gdms.cloud</item>

		<!-- TR-069 Username -->
		<item name="tr069.username"/>

		<!-- TR-069 Password -->
		<item name="tr069.password"/>

		<!-- Periodic Inform Enable -->
		<!-- Yes, No -->
		<item name="tr069.periodicInform">Yes</item>

		<!-- Periodic Inform Interval (s) -->
		<item name="tr069.periodicInformInterval">86400</item>

		<!-- Connection Request Username -->
		<item name="tr069.connectionRequestUsername">{{ strtoupper(preg_replace('/-/', '', $mac)) }}</item>

		<!-- Connection Request Password -->
		<item name="tr069.connectionRequestPassword">{{ strtoupper(preg_replace('/-/', '', $mac)) }}</item>

		<!-- Connection Request Port -->
		<item name="tr069.connectionRequestPort">7547</item>

		<!-- CPE SSL Certificate -->
		<item name="tr069.ssl.certificate"/>

		<!-- CPE SSL Private Key -->
		<item name="tr069.ssl.privateKey"/>

		<!-- # 	Start TR-069 at Random Time. Yes or No -->
		<item name="tr069.randomStart.enable">No</item>



		<!-- ############################################################################## -->
		<!-- ##  Settings/General Settings -->
		<!-- ############################################################################## -->
		<!-- # Local RTP Port. Default is 5004 -->
		<!-- # Number: 1024 - 65400. Must be even number -->
		<!-- Number: 5004 - 65535 -->
		<item name="network.rtp.local.port">5004</item>

		<!-- # Local RTP Port Range. Default is 200 -->
		<!-- # Number: 48 - 10000 -->
		<item name="network.rtp.local.portRange">200</item>

		<!-- Use Random Port -->
		<!-- Yes, No -->
		<item name="network.rtp.useRandomPort">Yes</item>

		<!-- Keep-Alive Interval (s) -->
		<!-- Number: 10 - 160 -->
		<item name="sip.keepAliveInterval">20</item>

		<!-- # Use NAT IP. This will enable our SIP client to use this IP in the SIP/SDP message. Example 64.3.153.50 -->
		<!-- # String: a-z, A-Z, 0-9, ".", ":" -->
		<item name="sip.userNatIp"/>

		<!-- STUN Server -->
		<item name="network.stunServer"/>

		<!-- # Delay Registration. Default is 0. -->
		<!-- # Number: 0 - 90 -->
		<item name="sip.delayRegistration">0</item>

		<!-- # Test Password Strength. Default is 0. -->
		<!-- # Yes or No.  -->
		<item name="users.testPasswordStrength.enable">No</item>



		<!-- ############################################################################## -->
		<!-- ##  Settings/Call Features -->
		<!-- ############################################################################## -->
		<!-- # Preferred Default Account.  -->
		<!-- # Account1 - Account6  -->
		<item name="call.dial.preferredAccount">Account1</item>

		<!-- # Predictive Dialing Feature. Yes or No -->
		<item name="call.dial.predictive.enable">Yes</item>

		<!-- # Predictive Dialing Source -->
		<!-- # String: CallHistory,LocalPhonebook,RemotePhonebook,FeatureCode -->
		<item name="call.dial.predictive.source">CallHistory,LocalPhonebook,RemotePhonebook,FeatureCode</item>

		<!-- # Onhook Dial Barging. Yes or No -->
		<item name="call.dial.offhook.allowBarging">Yes</item>

		<!-- # Off-hook Auto Dial -->
		<!-- # String -->
		<item name="call.dial.offhook.autoDial.number"/>

		<!-- # Off-hook Auto Dial Delay -->
		<!-- # Number: 0 - 10 -->
		<item name="call.dial.offhook.autoDial.delay">0</item>

		<!-- # Off-hook Timeout (in seconds). Default is 30 -->
		<item name="call.dial.offhook.timeout">30</item>

		<!-- # Enable Live DialPad. Yes or No -->
		<item name="call.dial.liveDialpad.enable">No</item>

		<!-- # Live DialPad Expire Time. Default is 5 -->
		<!-- # Number: 2 - 15.  -->
		<item name="call.dial.liveDialpad.expire">5</item>

		<!-- # Enable Auto Redial. Yes or No -->
		<item name="call.dial.autoRedial.enable">No</item>

		<!-- # Automatic Redial Times. Default is 10 -->
		<item name="call.dial.autoRedial.retry">10</item>

		<!-- # Automatic Redial Interval. Default is 20 -->
		<item name="call.dial.autoRedial.interval">20</item>

		<!-- # Bypass Dial Plan Through Call History and Directories. Yes or No -->
		<item name="call.dialPlan.allowBypassFromDirectories"/>

		<!-- # Disable Call Waiting. -->
		<!-- Invert_Yes_No, Yes - No, No - Yes -->
		@if (array_key_exists('grandstream_call_waiting', $settings))
            <item name="call.callWaiting.enable">{{ $settings['grandstream_call_waiting'] }}</item>
        @endif

        <!-- # Disable Call Waiting Tone -->
		<!-- Invert_Yes_No, Yes - No, No - Yes -->
		<item name="call.callWaiting.enableTone">Yes</item>

		<!-- # Ring for Call Waiting. Yes or No -->
		<item name="call.callWaiting.alwaysRing"/>

		<!-- # Disable Busy Tone on Remote Disconnect. -->
		<!-- Invert_Yes_No, Yes - No, No - Yes -->
		<item name="call.disconnect.remote.enableTone">Yes</item>

		<!-- # Disable Direct IP Call. -->
		<!-- # Invert_Yes_No, Yes - No, No - Yes -->
		<item name="call.ipcall.enable">Yes</item>

		<!-- # Use Quick IP call mode. Yes or No-->
		<item name="call.ipcall.allowQuickDialing">No</item>

		<!-- # Disable Conference. -->
		<!-- # Invert_Yes_No, Yes - No, No - Yes -->
		<item name="call.conference.enable">Yes</item>

		<!-- Disable In-call DTMF Display -->
		<!-- Invert_Yes_No, Yes - No, No - Yes -->
		<item name="call.display.dtmfInCall">Yes</item>

		<!-- # Enable Sending DTMF via specific MPKs. Yes or No -->
		<item name="pks.behavior.incall.sendDtmfInstead">No</item>

		<!-- # Disable Active MPK Page. Yes or No. GRP2614/15/16/24/34 only -->
		<item name="callFeatures.activeMPKPage">No</item>

		<!-- # Enable Active VPK Page. Yes or No -->
		<item name="callFeatures.displayVpkPage.enable">No</item>

		<!-- # Enable DND Feature. Yes or No -->
		<item name="call.dnd.enable">Yes</item>

		<!-- # 	Preserve DND Status. Yes or No -->
		<item name="preserve.DND.status.after.reboot">No</item>

		<!-- # Mute Key Functions While Idle. DND, IdleMute, Disabled. -->
		<item name="keys.custom.mute.idle"/>

		<!-- # Disable Mute Key In Call. Yes or No -->
		<item name="call.disable.mute.key">No</item>

		<!-- # DND Override. -->
		<!-- Off,  AllowAll, AllowOnlyContacts, AllowOnlyFavourites. -->
		<item name="call.dnd.allowCallList"/>

		<!-- # Disable Transfer. -->
		<!-- # Invert_Yes_No, Yes - No, No - Yes -->
		<item name="call.transfer.enable">Yes</item>

		<!-- # In-call Dial Number on Pressing Transfer Key -->
		<!-- # String -->
		<item name="call.transfer.initDialNumber"/>

		<!-- # Attended Transfer Mode. Static or Dynamic. -->
		<item name="call.transfer.attended.mode">Dynamic</item>
		
		<!-- # Transfer Mode via VPK/MPK. -->
		<!-- BlindTransfer, AttendedTransfer,  NewCall -->
		<item name="call.transfer.modeViaVpk">BlindTransfer</item>

		<!-- # Show On Hold Duration. Yes or No -->
		<item name="call.hold.showHoldDuration.enable">Yes</item>

		<!-- # Filter Characters -->
		<item name="call.filter.character">[()- ]+</item>

		<!-- # Do not Escape '#' as 23% in SIP URL. -->
		<!-- # Invert_Yes_No, Yes - No, No - Yes -->
		<item name="sip.escapeUrl">Yes</item>

		<!-- # Click-To-Dial Feature. Yes or No -->
		<item name="call.dial.clickToDial.enable">No</item>

		<!-- # Default call log type. Default, BroadsoftCallLog, LocalCallLog. -->
		<item name="features.history.defaultSource">Default</item>

		<!-- # Return Code When Refusing Incoming Call.  -->
		<!-- # Busy, TemporarilyUnavailable, NotFound, Decline. -->
		<item name="sip.returnCode.callReject">Busy</item>

		<!-- # Return Code Upon DND. Default is 0 -->
		<!-- # Busy, TemporarilyUnavailable, NotFound, Decline. -->
		<item name="sip.returnCode.dnd">TemporarilyUnavailable</item>

		<!-- # Enable BLF Pickup Screen.  Yes or No -->
		<item name="sip.blf.pickupScreen.enable">No</item>

		<!-- # Enable BLF Pickup Sound. Yes or No -->
		<item name="sip.blf.pickupSound.enable">No</item>

		<!-- # Enable BLF Pickup Sound List. ExceptList, OnlyList.  -->
		<item name="sip.blf.pickupSound.mode"/>

		<!-- # BLF Pickup Sound Except List.  -->
		<!-- # String -->
		<item name="sip.blf.pickupSound.list.except"/>

		<!-- # Local Call Recording Feature. Yes or No -->
		<item name="call.recording.local.enable">No</item>

		<!-- # Saved Call Recording Location. InternalStorage or USB -->
		<item name="callFeatures.savedLocalCallRecordingLocation">InternalStorage</item>

		<!-- # Enable IM Popup. Yes or No -->
		<item name="features.im.popup.enable">Yes</item>

		<!-- # Instant Message Popup Timeout -->
		<!-- # Number: 10 - 900 -->
		<item name="features.im.popupTimeout">10</item>

		<!-- # Play Tone On Receiving IM. Yes or No -->
		<item name="features.im.playToneOnNew"/>

		<!-- # Allow Incoming Call Before Ringing.  Yes(1) or No(0) -->
		<item name="callFeatures.allowIncomingCallBeforeRinging">0</item>

		<!-- # User-Agent Prefix -->
		<!-- # String -->
		<item name="call.dialPlan.allowBypassFromDirectories"/>

		<!-- # Auto Provision List Starting Point. ExtensionBoards, MPK, VPK -->
		<!-- <item name="sip.blf.eventlist.provisionStartingFrom">VPK</item> -->

		<!-- # Hide BLF Remote Status. Yes or No -->
		<item name="sip.blf.hideRemoteStatus"/>

		<!-- # Show SIP Error Response. Yes or No -->
		<item name="sip.showError">Yes</item>

		<!-- # Enable Missed Call Notification. Yes or No -->
		<item name="features.history.missedCallNotification">Yes</item>

		<!-- # Enable Call Completion Service. Yes or No -->
		<item name="call.dial.callComplete.enable"/>

		<!-- # Enable Incoming Call Popup. Yes or No -->
		<item name="callFeatures.incomingPopup.enable">Yes</item>

		<!-- # Enable Enhanced Acoustic Echo Canceller. Yes or No -->
		<item name="callFeatures.eac.enable">Yes</item>

		<!-- # Enable Diversion Information Display. Yes or No -->
		<item name="sip.diversionInformationDisplay">Yes</item>

	    <!-- # Disable Hook Switch. Yes, No, ForAnsweringCall -->
		<item name="callFeatures.hookSwitch">No</item>

	    <!-- # Disable Speaker Key Yes, No, ForOngoingCall -->
		<item name="callFeatures.speakerKey">No</item>

		<!-- ############################################################################## -->
		<!-- ##  Settings/Call History -->
		<!-- ############################################################################## -->

	

	

		<!-- ###################################################################################### -->
		<!-- # Destination -->
		<!-- ###################################################################################### -->

		<!-- ###################################################################################### -->
		<!-- # Notification -->
		<!-- ###################################################################################### -->

		<!-- ############################################################################## -->
		<!-- ##  Settings/Preferences -->
		<!-- ############################################################################## -->
		<!-- ############################################################################## -->
		<!-- ##  Settings/Preferences / Audio Control -->
		<!-- ############################################################################## -->
		<!-- # HEADSET Key Mode. DefaultMode, ToggleHeadsetOrSpeaker. -->
		<item name="audio.headset.keyMode">DefaultMode</item>

		<!-- # Headset Type. Normal, PlantronicsEHS. -->
		<item name="audio.headset.ehs.ringtone">Normal</item>

		<!-- # EHS Headset Ringtone. Normal, PlantronicsEHS. -->
		<item name="audio.headset.type">Normal</item>

		<!-- # Always Ring Speaker. -->
		<!-- No - 0, Yes,both - 1, Yes,speaker only - 2 -->
		<item name="audio.alwaysRingSpeaker">0</item>

		<!-- # Group Listen with Speaker. -->
		<!-- No - 0, Yes - 1 -->
		<item name="audio.groupListenwithSpeaker">0</item>

		<!-- # Headset TX gain(dB). 1 - -6, 0 - 0, 2 - +6. Default is 0 -->
		<item name="audio.headset.txGain">0</item>

		<!-- # Headset RX gain(dB). 1 - -6, 0 - 0, 2 - +6. Default is 0 -->
		<item name="audio.headset.rxGain">0</item>

		<!-- # Handset TX gain(dB). 1 - -6, 0 - 0, 2 - +6. Default is 0 -->
		<item name="audio.handset.txGain">0</item>

		<!-- ############################################################################## -->
		<!-- ##  Settings/Preferences / Date and Time -->
		<!-- ############################################################################## -->
		<!-- # NTP Server -->
		<item name="dateTime.ntp.server.1">pool.ntp.org</item>

		<!-- # Secondary NTP Server -->
		<!-- # String -->
		<item name="dateTime.ntp.server.2"/>

		<!-- # NTP Update Interval -->
		<!-- # String -->
		<!-- # Number: 5 - 1440, Default is 1440  -->
		<item name="dateTime.ntp.updateInterval">1440</item>

		<!-- # Allow DHCP Option 42 to override NTP server. Yes or No -->
		<!-- # When set to Yes, it will override the configured NTP server -->
		<item name="dateTime.override.dhcp.allowOption42">No</item>

		<!-- # Time Zone -->

		<!-- # String -->
		<!-- # Mandatory -->
        @if (array_key_exists('grandstream_time_zone', $settings))
            <item name="dateTime.timezone">{{ $settings['grandstream_time_zone'] }}</item>
        @else
            <item name="dateTime.timezone">auto</item>
        @endif


		<!-- # Allow DHCP Option 2 to Override Time Zone Setting. Yes or No -->
		<item name="dateTime.override.dhcp.allowOption2">Yes</item>

		<!-- # Self Defined Time Zone. Max length allowed is 64 characters -->
		<!-- # String -->
		<!-- # Mandatory -->
		<item name="dateTime.timezone.custom">MTZ+6MDT+5,M4.1.0,M11.1.0</item>

        <!-- # Date Display Format -->
        <!-- # 0: yyyy-mm-dd      eg. 2011-10-31 -->
        <!-- # 1: mm-dd-yyyy      eg. 10-31-2011 -->
        <!-- # 2: dd-mm-yyyy      eg. 31-10-2011 -->
        <!-- # 3: dddd, MMMM dd   eg. Monday, October 31 -->
        <!-- # 4: MMMM dd, dddd   eg. October 31, Monday -->
        @if (array_key_exists('grandstream_format_date', $settings))
            <item name="dateTime.format.date">{{ $settings['grandstream_format_date'] }}</item>
        @else
            <item name="dateTime.format.date">yyyy-mm-dd</item>
        @endif
        
        <!-- # Time Display Format. 12 Hour, 24 Hour -->
        <!-- 12Hour, 24Hour -->
        @if (array_key_exists('grandstream_format_time', $settings))
            <item name="dateTime.format.time">{{ $settings['grandstream_format_time'] }}</item>
        @else
            <item name="dateTime.format.time">12Hour</item>
        @endif
        
        <!-- # Show Clock. Yes or No -->
        @if (array_key_exists('grandstream_show_clock', $settings))
            <item name="dateTime.showClock">{{ $settings['grandstream_show_clock'] }}</item>
        @else
            <item name="dateTime.showClock">Yes</item>
        @endif


		<!-- # Show Date and Time on Status Bar. Time Only, Date and Time, None -->
		<!-- <item name="dateTime.showOnStatusBar">Time Only</item>-->

		<!-- ############################################################################## -->
		<!-- ##  Settings/Preferences / LCD Display -->
		<!-- ############################################################################## -->
		<!-- # Backlight Brightness. -->
		<!-- # Active. Default is 100. -->
		<!-- # Number: 10 - 100 -->
		<item name="lcd.backlight.brightness.active">100</item>

		<!-- # Idle. Default is 60. -->
		<!-- # Number: 0 - 100 -->
		<item name="lcd.backlight.brightness.idle">80</item>

		<!-- # Active Backlight Timeout. Default is 1 -->
		<!-- # Number: 1 - 90 -->
		<item name="lcd.backlight.activeTimeout">1</item>

		<!-- # Disable Missed Call Backlight. No, Yes, Yes, but flash MWI LED.  -->
		<!-- No - 0, Yes - 1, Yes,but flash MWI LED - 2 -->
		<item name="lcd.backlight.missedCall">1</item>

		<!-- # Wallpaper Settings -->
		<!-- # Wallpaper Source. Default, Download, USB, Uploaded, ColorBackground -->
        @if (array_key_exists('grandstream_wallpaper_url', $settings))
            <item name="lcd.wallpaper.source">Download</item>
        @else
            <item name="lcd.wallpaper.source">Default</item>
        @endif

		<!-- # Wallpaper Server Path -->
		<!-- # String  -->
        @if (array_key_exists('grandstream_wallpaper_url', $settings))
            <item name="lcd.wallpaper.serverPath">{{ $settings['grandstream_wallpaper_url'] }}</item>
        @endif
        
        <!-- ############################################################################## -->
		<!-- ##  Settings/Preferences / LED Control -->
		<!-- ############################################################################## -->
		<!-- # BLF LED Pattern. Default-0,  Analog-1, Directional-2, Reserved(Red)-3, Reserved(Green)-4, Inverse-5.-->
		<item name="sip.blf.lightPattern">0</item>

		<!-- # Disable VM/MSG power light flash. -->
		<!-- Invert_Yes_No, Yes - No, No - Yes -->
		<item name="ledControl.mwi">Yes</item>

		<!-- ############################################################################## -->
		<!-- ##  Settings/Preferences / Ringtone -->
		<!-- ############################################################################## -->

		<!-- # System Ringtone -->
		<!-- # String -->
		<!-- # Mandatory -->
		<item name="audio.tone.systemRing">f1=440,f2=480,c=200/400;</item>

		<!-- # Dial Tone -->
		<!-- # String -->
		<!-- # Mandatory -->
		<item name="audio.tone.dial">f1=350,f2=440;</item>

		<!-- # Second Dial Tone -->
		<!-- # String -->
		<!-- # Mandatory -->
		<item name="audio.tone.secondDial">f1=350,f2=440;</item>

		<!-- # Message Waiting -->
		<!-- # String -->
		<!-- # Mandatory -->
		<item name="audio.tone.message">f1=350,f2=440,c=10/10;</item>

		<!-- # Ring Back Tone -->
		<!-- # String -->
		<!-- # Mandatory -->
		<item name="audio.tone.ringBack">f1=440,f2=480,c=200/400;</item>

		<!-- # Call-Waiting Tone -->
		<!-- # String -->
		<!-- # Mandatory -->
		<item name="audio.tone.callWaiting">f1=440,f2=440,c=25/525;</item>

		<!-- # Call Waiting Tone Gain -->
		<!-- # Option  Low, Medium, High-->
		<item name="audio.tone.callWaiting.gain">Low</item>

		<!-- # Busy Tone -->
		<!-- # String -->
		<!-- # Mandatory -->
		<item name="audio.tone.busy">f1=480,f2=620,c=50/50;</item>

		<!-- # Reorder Tone -->
		<!-- # String -->
		<!-- # Mandatory -->
		<item name="audio.tone.reorder">f1=480,f2=620,c=25/25;</item>

		<!-- # Speaker Ring Volume -->
		<!-- # Number: 0-7. Default is 5. -->
		<!-- # Mandatory -->
		<item name="audio.volume.ring">5</item>

		<!-- # Notification Tone Volume -->
		<!-- # Number: 0-7. Default is 5. -->
		<!-- # Mandatory -->
		<item name="audio.volume.notification">5</item>

		<!-- # Call Tone Volume -->
		<!-- # Number: (-15)-15. Default is 0. -->
		<!-- # Mandatory -->
		<item name="audio.volume.tone">0</item>

		<!-- # Lock Speaker Volume. No, Ring, Talk, Both -->
		<item name="audio.volume.lock">No</item>

		<!-- # Default Ringtone. -->
		<item name="audio.ring.defaultRingtone">0</item>

		<!-- # Total Number of Custom Ringtone Update -->
		<!-- # Number: 0-10. Default is 3. -->
		<!-- # Mandatory -->
		<item name="audio.ring.numberOfRingtone">3</item>

	
		<!-- ############################################################################## -->
		<!-- ##  Settings/Web Service -->
		<!-- ############################################################################## -->
		<!-- # Weather Update -->
		<!-- # Use Auto Location Service. Yes or No -->
		<item name="services.weather.enable">Yes</item>

	

	
		<!-- ############################################################################## -->
		<!-- ##  Settings/Programmable Keys -->
		<!-- ############################################################################## -->
		<!-- ############################################################################## -->
		<!-- ##  Settings/Programmable Keys / Virtual Multi-Purpose Keys Settings -->
		<!-- ############################################################################## -->
		<!-- # Idle Screen Settings -->
		<!-- # Use Long Label. Yes or No -->
		<item name="pks.vpk.settings.idle.longLabel">Yes</item>

		<!-- # Call Screen Settings -->
		<!-- # Key Mode. LineMode, AccountMode. -->
		<item name="pks.vpk.settings.mode">AccountMode</item>

		<!-- # Transfer Mode via VPK. BlindTransfer, AttendedTransfer, NewCall. -->
		<item name="call.transfer.modeViaVpk">BlindTransfer</item>

		<!-- # Enable transfer via non-Transfer MPK. Yes or No -->
		<item name="transfer.nontransfer.mpk.enable">No</item>

		<!-- # Show Keys Label. Toggle, Show, Hide -->
		<item name="pks.vpk.settings.call.showLabel">Show</item>

	    <!-- ############################################################################## -->
		<!-- ##  Settings/Programmable Keys / Softkeys Settings -->
		<!-- ############################################################################## -->
		<!-- # More Softkey Display Mode -->
		<!-- # Menu, Toggle -->
		<!-- # Mandatory -->
		<item name="softkeys.moreKeyDisplayMode">Menu</item>

		<!-- # Show Target Softkey -->
		<!-- # Yes or No -->
		<item name="softkeys.state.call.showTargetKey">Yes</item>

		<!-- # Custom Softkey Layout. Yes or No -->
		<item name="softkeys.layout.enable">No</item>

		<!-- # Enforce Softkey Layout Position. Yes or No -->
		<item name="softkeys.layout.enforcePosition">No</item>

		<!-- # Hide System Softkey on Idle Page.  -->
		<!-- # String: Next, History, ForwardAll, Redial -->
		<item name="softkeys.state.idle.hideSystemKeys"/>

		<!-- ## Custom Call Screen Softkey Layout ## -->
		<!-- # Softkey layout in dialing state -->
		<!-- # String: BTPhonebook,BTOnOff,EndCall,ReConf,ConfRoom,Redial,Dial,Backspace,PickUp,Target  -->
		<item name="softkeys.layout.state.inDialing">BTPhonebook,BTOnOff,EndCall,ReConf,ConfRoom,Redial,Dial,Backspace,PickUp,Target</item>

		<!-- # Softkey Layout in Onhook Dialing State -->
		<!-- # String: BTPhonebook,DirectIP,Onhook,Cancel,Dial,Backspace,Target, -->
		<item name="softkeys.layout.state.inOnhookDialing">BTPhonebook,DirectIP,Onhook,Cancel,Dial,Backspace,Target</item>

		<!-- # Softkey Layout in Ringing State -->
		<!-- # String: Answer, Reject, Forward, ReConf,Custom-Silence  -->
		<item name="softkeys.layout.state.InRinging">Answer,Reject,Forward,ReConf</item>

		<!-- # Softkey Layout in Calling State -->
		<!-- # String: BTOnOff,EndCall,ReConf,ConfRoom,ConfCall  -->
		<item name="softkeys.layout.state.inCalling">BTOnOff,Cancel,EndCall,ReConf,ConfRoom,ConfCall</item>

		<!-- # Softkey Layout in Call Connected State  -->
		<!-- # String: BTPhonebook,BTOnOff,EndCall,ReConf,ConfRoom,Custom-Confcall,Cancel,NewCall,Swap,Transfer,Trnf>VM,DialDTMF,BSCCenter,URecord,Record,UCallPark,PrivateHold,CallPark, -->
		<!-- # Custom-Hold,Custom-Conference,Custom-Mute -->
		<item name="softkeys.layout.state.inCallConnected">BTPhonebook,BTOnOff,EndCall,ReConf,ConfRoom,Custom-Confcall,Cancel,NewCall,Swap,Transfer,Trnf&gt;VM,DialDTMF,BSCCenter,URecord,Record,UCallPark,PrivateHold,CallPark</item>

		<!-- # Softkey Layout in Conference Connected State -->
		<!-- # String: BTOnOff,EndCall,Kick,NewCall,Trnf>VM,DialDTMF,BSCCenter,URecord,Record,ConfRoom,Add,Custom-Hold,Custom-Split,Custom-Mute -->
		<item name="softkeys.layout.state.inConferenceConnected">BTOnOff,EndCall,Kick,NewCall,Trnf&gt;VM,DialDTMF,BSCCenter,URecord,Record,ConfRoom,Add</item>

		<!-- # Softkey Layout in Onhold State -->
		<!-- # String: ReConf,Resume,HoldTrnf,ConfCall,Add,Custom-NewCall,Custom-EndCall -->
		<item name="softkeys.layout.state.inOnhold">ReConf,Resume,HoldTrnf,ConfCall,Add</item>

		<!-- # Softkey Layout in Call Failed State -->
		<!-- # String: EndCall,ReCOnf,ConfRoom,Custom-NewCall -->
		<item name="softkeys.layout.state.inCallFailed">EndCall,ReConf,ConfRoom</item>

		<!-- # Softkey Layout in Transfer State -->
		<!-- # String: BTOnOff,Cancel,BlindTrnf,AttTrnf,Backspace,Target -->
		<item name="softkeys.layout.state.inTransfer">BTOnOff,Cancel,BlindTrnf,AttTrnf,Backspace,Target</item>

		<!-- # Softkey Layout in Conference State -->
		<!-- # String:BTOnOff,Cancel,Dial,Backspace,Target -->
		<item name="softkeys.layout.state.inConference">BTOnOff,Cancel,Dial,Backspace,Target</item>

		<!-- ###################################################################################### -->
		<!-- ## Programmable Keys/Idle Screen Settings -->
		<!-- ###################################################################################### -->
		<!-- # Softkey 1 -->
		<!-- ###################################################################################### -->
		<!-- # Key Mode. -->
		<!-- # Default, SpeedDial, SpeedDialViaActiveAccount, VoiceMail,  -->
		<!-- # CallReturn, Intercom, LDAPSearch, CallLog, Menu, Information, Message -->
		<item name="pks.softkey.1.keyMode">Default</item>

		<!-- # Account. 0 - Account1, 1 - Account2, 2 - Account3, 3 - Account4, 4 - Account5, 5 - Account6 -->
		<item name="pks.softkey.1.account"/>

		<!-- # Description.  -->
		<!-- # String.  -->
		<item name="pks.softkey.1.description"/>

		<!-- # Value. -->
		<!-- # String. -->
		<item name="pks.softkey.1.value"/>

		<!-- ###################################################################################### -->
		<!-- # Softkey 2 -->
		<!-- ###################################################################################### -->
		<!-- # Key Mode. -->
		<!-- # Default, SpeedDial, SpeedDialViaActiveAccount, VoiceMail,  -->
		<!-- # CallReturn, Intercom, LDAPSearch, CallLog, Menu, Information, Message -->
		<item name="pks.softkey.2.keyMode">Default</item>

		<!-- # Account. 0 - Account1, 1 - Account2, 2 - Account3, 3 - Account4, 4 - Account5, 5 - Account6 -->
		<item name="pks.softkey.2.account"/>

		<!-- # Description.  -->
		<!-- # String.  -->
		<item name="pks.softkey.2.description"/>

		<!-- # Value. -->
		<!-- # String. -->
		<item name="pks.softkey.2.value"/>

		<!-- ###################################################################################### -->
		<!-- # Softkey 3 -->
		<!-- ###################################################################################### -->
		<!-- # Key Mode. -->
		<!-- # Default, SpeedDial, SpeedDialViaActiveAccount, VoiceMail,  -->
		<!-- # CallReturn, Intercom, LDAPSearch, CallLog, Menu, Information, Message -->
		<item name="pks.softkey.3.keyMode">Default</item>

		<!-- # Account. 0 - Account1, 1 - Account2, 2 - Account3, 3 - Account4, 4 - Account5, 5 - Account6 -->
		<item name="pks.softkey.3.account"/>

		<!-- # Description.  -->
		<!-- # String.  -->
		<item name="pks.softkey.3.description"/>

		<!-- # Value. -->
		<!-- # String. -->
		<item name="pks.softkey.3.value"/>

		<!-- ###################################################################################### -->
		<!-- ## Programmable Keys/Call Screen Settings -->
		<!-- ###################################################################################### -->
		<!-- ###################################################################################### -->
		<!-- # Softkey 1 -->
		<!-- ###################################################################################### -->
		<!-- # Key Mode. -->
		<!-- # Default, SpeedDial, SpeedDialViaActiveAccount, VoiceMail,  -->
		<!-- # CallReturn, Intercom, LDAPSearch, CallLog, Information, Message -->
		<item name="pks.scSoftkey.1.mode">Default</item>

		<!-- # Description.  -->
		<!-- # String.  -->
		<item name="pks.scSoftkey.1.description"/>

		<!-- # Value. -->
		<!-- # String. -->
		<item name="pks.scSoftkey.1.value"/>

		<!-- ###################################################################################### -->
		<!-- # Softkey 2 -->
		<!-- ###################################################################################### -->
		<!-- # Key Mode. -->
		<!-- # Default, SpeedDial, SpeedDialViaActiveAccount, VoiceMail,  -->
		<!-- # CallReturn, Intercom, LDAPSearch, CallLog, Information, Message -->
		<!-- # Mandatory -->
		<item name="pks.scSoftkey.2.mode">Default</item>

		<!-- # Description.  -->
		<!-- # String.  -->
		<item name="pks.scSoftkey.2.description"/>

		<!-- # Value. -->
		<!-- # String. -->
		<item name="pks.scSoftkey.2.value"/>

		<!-- ###################################################################################### -->
		<!-- # Softkey 3 -->
		<!-- ###################################################################################### -->
		<!-- # Key Mode. -->
		<!-- # Default, SpeedDial, SpeedDialViaActiveAccount, VoiceMail,  -->
		<!-- # CallReturn, Intercom, LDAPSearch, CallLog, Information, Message -->
		<!-- # Mandatory -->
		<item name="pks.scSoftkey.3.mode">Default</item>

		<!-- # Description.  -->
		<!-- # String.  -->
		<item name="pks.scSoftkey.3.description"/>

		<!-- # Value. -->
		<!-- # String. -->
		<item name="pks.scSoftkey.3.value"/>

		<!-- ###################################################################################### -->
		<!-- ## Programmable Keys/EXT Setting -->
		<!-- ###################################################################################### -->
		<!-- # One Page Display Mode. Yes or No -->
		<!-- # Mandatory -->
		<item name="pks.ext.onePageDisplayMode">No</item>

		<!-- # Sync Backlight with LCD. Yes or No -->
		<!-- # Mandatory -->
		<item name="pks.ext.syncBacklightWithLCD">No</item>


	</config>
</gs_provision>

@break

@endswitch