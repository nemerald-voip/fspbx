{{-- version: 1.0.4 --}}

@switch($flavor)

{{-- ================= Dinstar {serial}.xml ================= --}}
@case('serial.xml')

<?xml version="1.0" encoding="UTF-8"?>
<provision version="2.0">
  <product id="{{ $product_id ?? '83' }}"
           snfilter="" macfilter="" model="" language="" dmsno="" oemid=""
           lowver="" highver="" url="{{ $settings['provision_base_url'] ?? '' }}" force="false">
    <ConfigFile name="$(MA).cfg" action="merge" reboot="never"></ConfigFile>
  </product>
</provision>

@break
  
{{-- ================= Dinstar mac.cfg ================= --}}
@case('mac.cfg')

<?xml version="1.0" encoding="UTF-8"?>
<config version="5.8" md5=" ">
<sipserver>
        <server0>
            <param name="domain" value="{{ $lines[1]['server_address'] ?? '' }}" />
            <param name="port" value="{{ $lines[1]['sip_port'] ?? '' }}" />
            <param name="reg_interval" value="{{ $lines[1]['register_expires'] ?? '' }}" />
            <param name="heartbeat" value="enable" />
        </server0>
        <server1>
            <param name="domain" value="" />
            <param name="port" value="5060" />
            <param name="reg_interval" value="300" />
            <param name="heartbeat" value="enable" />
        </server1>
        <outbound>
            <param name="domain" value="{{ $lines[1]['outbound_proxy_primary'] ?? '' }}" />
            <param name="port" value="{{ $lines[1]['sip_port'] ?? '' }}" />
            <param name="sec_domain" value="{{ $lines[1]['outbound_proxy_secondary'] ?? '' }}" />
            <param name="sec_port" value="{{ $lines[1]['sip_port'] ?? '' }}" />
        </outbound>
        <param name="transport" value="{{ $lines[1]['sip_transport'] ?? '' }}" />
        <local>
            <param name="random_port" value="disable" />
            <param name="udp_port" value="5060" />
            <param name="tcp_port" value="5060" />
            <param name="tls_port" value="5061" />
        </local>
        <param name="sip_proxy_enable" value="disable" />
        <param name="sipipprot" value="0" />
        <param name="unreg_allreg_enable" value="0" />
        <param name="reg_times_interval" value="0" />
        <param name="rereg_expires_ratio" value="0" />
        <param name="moh_enable" value="disable" />
        <param name="moh_dial" value="~~mh~u" />
        <param name="sips_url" value="1" />
        <param name="tls_bidirectional_auth" value="disable" />
    </sipserver>
        <sipacc>
    @foreach ($lines as $line)
        @php
            // line_number may be a string — coerce to int
            $ln  = (int) ($line['line_number'] ?? 0);
            $idx = $ln;
        @endphp
        <sipacc{{ $idx }}>
            <param name="serverid"   value="0" />
            <param name="display"    value="{{ $line['display_name'] ?? $line['auth_id'] }}" />
            <param name="user_id"    value="{{ $line['auth_id'] ?? ''}}" />
            <param name="auth_id"    value="{{ $line['auth_id'] ?? ''}}" />
            <param name="auth_pwd" value="{{ $line['password'] ?? ''}}" />
            <param name="isregister" value="enable" />
        </sipacc{{ $idx }}>
    @endforeach
    </sipacc>
    <ports>
    @foreach ($lines as $line)
        @php
            // line_number may be a string — coerce to int
            $ln  = (int) ($line['line_number'] ?? 0);
            $idx = $ln;
        @endphp
        <port{{ $idx }}>
            <param name="enable"                 value="enable" />
            <param name="primary_sipacc_id"      value="{{ $idx }}" />
            <param name="second_sipacc_id"       value="65535" />
            <param name="auto_dial"              value="" />
            <param name="auto_dial_timeout"      value="0" />
            <param name="dnd"                    value="disable" />
            <param name="callerid"               value="enable" />
            <param name="cfu"                    value="" />
            <param name="cfb"                    value="" />
            <param name="cfnry"                  value="" />
            <param name="call_waiting"           value="disable" />
            <param name="call_waiting_tone"      value="disable" />
            <param name="call_waiting_send_cid"  value="disable" />
            <param name="function"               value="" />
            <param name="ipprofileindex"         value="0" />
            <param name="telprofileindex"        value="0" />
            <param name="call_limit_count"       value="0" />
            <param name="call_limit_period"      value="0" />
        </port{{ $idx }}>
    @endforeach
    </ports>
    <portgroup />
    <fxs_fxo>
        <param name="dial_timeout" value="4" />
        <param name="ringback_timeout" value="55" />
        <param name="ringing_timeout" value="55" />
        <param name="no_rtp_detect" value="disable" />
        <param name="no_rtp_period" value="60" />
        <param name="call_process_tone" value="0" />
        <param name="ring_tone" value="0,0,0,0,0,0,0,0" />
        <param name="busy_tone" value="0,0,0,0,0,0,0,0" />
        <param name="dial_tone" value="0,0,0,0,0,0,0,0" />
        <param name="auto_gain_ctrl" value="disable" />
        <fxs>
            <param name="send_rolarity_reversal" value="disable" />
            <param name="flashhook_detect" value="enable" />
            <param name="flashhook_min_time" value="100" />
            <param name="flashhook_max_time" value="400" />
            <param name="cid_type" value="0" />
            <param name="cid_msg_type" value="0" />
            <param name="cid_msg_format" value="0" />
            <param name="send_cid_brfore_ring" value="disable" />
            <param name="send_cid_after_ring_delay" value="500" />
            <param name="cfnry_timeout" value="33" />
            <param name="slic" value="0" />
            <param name="longlinesupport" value="disable" />
            <longline>
                <param name="slot0" value="disable" />
                <param name="slot1" value="disable" />
                <param name="slot2" value="disable" />
                <param name="slot3" value="disable" />
                <param name="slot4" value="disable" />
                <param name="slot5" value="disable" />
                <param name="slot6" value="disable" />
                <param name="slot7" value="disable" />
                <param name="slot8" value="disable" />
                <param name="slot9" value="disable" />
                <param name="slot10" value="disable" />
                <param name="slot11" value="disable" />
                <param name="slot12" value="disable" />
                <param name="slot13" value="disable" />
            </longline>
            <pos_modem>
                <param name="port0" value="disable" />
                <param name="port1" value="disable" />
                <param name="port2" value="disable" />
                <param name="port3" value="disable" />
                <param name="port4" value="disable" />
                <param name="port5" value="disable" />
                <param name="port6" value="disable" />
                <param name="port7" value="disable" />
                <param name="port8" value="disable" />
                <param name="port9" value="disable" />
                <param name="port10" value="disable" />
                <param name="port11" value="disable" />
                <param name="port12" value="disable" />
                <param name="port13" value="disable" />
                <param name="port14" value="disable" />
                <param name="port15" value="disable" />
                <param name="port16" value="disable" />
                <param name="port17" value="disable" />
                <param name="port18" value="disable" />
                <param name="port19" value="disable" />
                <param name="port20" value="disable" />
                <param name="port21" value="disable" />
                <param name="port22" value="disable" />
                <param name="port23" value="disable" />
            </pos_modem>
            <fxs_cid_enable_mask>
                <param name="cid_port0" value="disable" />
                <param name="cid_port1" value="disable" />
                <param name="cid_port2" value="disable" />
                <param name="cid_port3" value="disable" />
                <param name="cid_port4" value="disable" />
                <param name="cid_port5" value="disable" />
                <param name="cid_port6" value="disable" />
                <param name="cid_port7" value="disable" />
                <param name="cid_port8" value="disable" />
                <param name="cid_port9" value="disable" />
                <param name="cid_port10" value="disable" />
                <param name="cid_port11" value="disable" />
                <param name="cid_port12" value="disable" />
                <param name="cid_port13" value="disable" />
                <param name="cid_port14" value="disable" />
                <param name="cid_port15" value="disable" />
                <param name="cid_port16" value="disable" />
                <param name="cid_port17" value="disable" />
                <param name="cid_port18" value="disable" />
                <param name="cid_port19" value="disable" />
                <param name="cid_port20" value="disable" />
                <param name="cid_port21" value="disable" />
                <param name="cid_port22" value="disable" />
                <param name="cid_port23" value="disable" />
            </fxs_cid_enable_mask>
            <param name="enable_fxs_fault" value="enable" />
            <param name="connect_phone_count" value="4" />
            <param name="caller_preferred" value="0" />
            <param name="ring_cadence" value="0,0,0,0,0,0" />
            <param name="current_offHook" value="20" />
            <param name="fxs_offhook_debounce_interval" value="5" />
            <param name="fxs_lcr_onhook" value="10" />
            <param name="fxs_lcr_offhook" value="12" />
            <param name="subscriber_category" value="0" />
            <param name="cid_modulation_type" value="0" />
            <param name="dtmf_code" value="" />
        </fxs>
        <dsp>
            <param name="fax_vaolume" value="0" />
            <param name="busy_tone_detect_druation" value="50" />
            <param name="mix_tone_time" value="10" />
            <param name="callerid_type" value="0" />
            <param name="dtmf_detect_druation_mix" value="40" />
            <param name="fxo_init_period" value="0" />
            <param name="fxo_init_timeout" value="180" />
            <param name="fxs_init_period" value="0" />
            <param name="fxs_init_timeout" value="180" />
            <param name="fxs_init_level" value="0" />
            <param name="min_onhook_voltoge" value="16" />
            <param name="min_offhook_voltoge" value="4" />
            <param name="min_offhook_current" value="8" />
            <param name="max_offhook_current" value="45" />
            <param name="fxo_pluse_digit" value="300" />
            <param name="fxo_limit_enable" value="0" />
            <param name="fxo_max_tone_canadence" value="4" />
            <param name="callerid_data_parity" value="0" />
            <param name="callerid_crc_checksum" value="0" />
            <param name="echo_cancel_mode" value="0" />
            <param name="echo_cancel_tail" value="15" />
            <param name="agctx_on" value="disable" />
            <param name="agcrx_on" value="disable" />
            <param name="dsp_jb_mode" value="0" />
            <param name="dsp_jb_adpt_start" value="20" />
            <param name="dtmfdet_freqdev" value="20" />
            <param name="dtmfdet_negative_twist" value="100" />
            <param name="dtmfdet_positive_twist" value="100" />
            <param name="dtmfdet_minlevel" value="-35" />
            <param name="dtmfdet_snrthreshold" value="20" />
            <param name="dtmfdet_minduration" value="40" />
            <param name="echo_cancel_gain" value="0" />
            <param name="echo_cancel_nlp" value="0" />
            <param name="echo_cancel_epcd" value="0" />
            <param name="silence_det_enable" value="0" />
            <param name="silence_det_timing" value="120" />
            <param name="silence_det_threshold" value="-38" />
            <param name="fxo_busytone_time" value="0,0,0,0,0,0,0,0" />
            <param name="cptone_on_off_threshold" value="-34" />
            <param name="cptone_off_on_threshold" value="-30" />
            <param name="pcm_capt_with_spu" value="0" />
            <param name="dsp_jb_static_size" value="200" />
        </dsp>
        <lines>
            <line0>
                <param name="workmode" value="2" />
                <gain>
                    <param name="tx_gain" value="0" />
                    <param name="rx_gain" value="0" />
                    <param name="codec_tx_gain" value="4" />
                    <param name="codec_rx_gain" value="0" />
                </gain>
                <param name="scene_mode" value="0" />
                <param name="set_mode" value="0" />
                <param name="gain_offset" value="1" />
            </line0>
        </lines>
        <param name="spi_protect_time" value="15" />
        <param name="first_dial_timeout" value="10" />
        <param name="offline_escape" value="disable" />
        <param name="ac_impedance_hybrid" value="0" />
        <param name="cw_tone_duration" value="800" />
        <param name="cw_tone_gap" value="2000" />
        <param name="cw_tone_cnt" value="5" />
    </fxs_fxo>
    <media>
        <param name="rtp_port_random" value="disable" />
        <param name="start_rtp_port" value="8000" />
        <param name="dtmf_method" value="2" />
        <param name="dtmf_gain" value="0" />
        <param name="dtmf_interval" value="100" />
        <param name="rfc2833_pt_prefered" value="remote" />
        <param name="rfc2833_pt" value="101" />
        <param name="send_flash_event" value="disable" />
        <param name="flashhook_rtp_event" value="16" />
        <param name="content_type" value="application/dtmf-relay" />
        <param name="content_length" value="11" />
        <param name="body" value="Signal=16" />
        <codec0>
            <param name="name" value="0" />
            <param name="payload_type" value="0" />
            <param name="packetization" value="20" />
            <param name="rate" value="64" />
            <param name="silence_suppression" value="disable" />
        </codec0>
        <codec1>
            <param name="name" value="8" />
            <param name="payload_type" value="8" />
            <param name="packetization" value="20" />
            <param name="rate" value="64" />
            <param name="silence_suppression" value="disable" />
        </codec1>
        <codec2>
            <param name="name" value="18" />
            <param name="payload_type" value="18" />
            <param name="packetization" value="20" />
            <param name="rate" value="8" />
            <param name="silence_suppression" value="disable" />
        </codec2>
        <codec3>
            <param name="name" value="4" />
            <param name="payload_type" value="4" />
            <param name="packetization" value="30" />
            <param name="rate" value="63" />
            <param name="silence_suppression" value="disable" />
        </codec3>
        <codec4>
            <param name="name" value="255" />
            <param name="payload_type" value="255" />
            <param name="packetization" value="255" />
            <param name="rate" value="255" />
            <param name="silence_suppression" value="enable" />
        </codec4>
        <codec5>
            <param name="name" value="255" />
            <param name="payload_type" value="255" />
            <param name="packetization" value="255" />
            <param name="rate" value="255" />
            <param name="silence_suppression" value="enable" />
        </codec5>
        <codec6>
            <param name="name" value="255" />
            <param name="payload_type" value="255" />
            <param name="packetization" value="255" />
            <param name="rate" value="255" />
            <param name="silence_suppression" value="enable" />
        </codec6>
        <codec7>
            <param name="name" value="255" />
            <param name="payload_type" value="255" />
            <param name="packetization" value="255" />
            <param name="rate" value="255" />
            <param name="silence_suppression" value="enable" />
        </codec7>
        <codec8>
            <param name="name" value="255" />
            <param name="payload_type" value="255" />
            <param name="packetization" value="255" />
            <param name="rate" value="255" />
            <param name="silence_suppression" value="enable" />
        </codec8>
        <codec9>
            <param name="name" value="255" />
            <param name="payload_type" value="255" />
            <param name="packetization" value="255" />
            <param name="rate" value="255" />
            <param name="silence_suppression" value="enable" />
        </codec9>
        <codec10>
            <param name="name" value="255" />
            <param name="payload_type" value="255" />
            <param name="packetization" value="255" />
            <param name="rate" value="255" />
            <param name="silence_suppression" value="enable" />
        </codec10>
        <codec11>
            <param name="name" value="255" />
            <param name="payload_type" value="255" />
            <param name="packetization" value="255" />
            <param name="rate" value="255" />
            <param name="silence_suppression" value="enable" />
        </codec11>
        <codec12>
            <param name="name" value="255" />
            <param name="payload_type" value="255" />
            <param name="packetization" value="255" />
            <param name="rate" value="255" />
            <param name="silence_suppression" value="enable" />
        </codec12>
        <codec13>
            <param name="name" value="255" />
            <param name="payload_type" value="255" />
            <param name="packetization" value="255" />
            <param name="rate" value="255" />
            <param name="silence_suppression" value="enable" />
        </codec13>
        <codec14>
            <param name="name" value="255" />
            <param name="payload_type" value="255" />
            <param name="packetization" value="255" />
            <param name="rate" value="255" />
            <param name="silence_suppression" value="enable" />
        </codec14>
        <codec15>
            <param name="name" value="255" />
            <param name="payload_type" value="255" />
            <param name="packetization" value="255" />
            <param name="rate" value="255" />
            <param name="silence_suppression" value="enable" />
        </codec15>
        <param name="dtmf_duration" value="100" />
        <param name="codecs_prefered" value="1" />
    </media>
    <sip>
        <param name="mwi" value="disable" />
        <param name="voicemail" value="" />
        <param name="rfc3407" value="disable" />
        <param name="ip_to_ip_call" value="enable" />
        <param name="include_user_is_phone" value="disable" />
        <param name="rfc3325_ppi" value="disable" />
        <param name="only_accept_calls_from_ACL" value="disable" />
        <param name="anonymous_call" value="disable" />
        <param name="reject_anonymous_call" value="disable" />
        <param name="sharp_as_end_dial" value="enable" />
        <param name="sharp_key_escape" value="disable" />
        <param name="refer_to_refers_contact" value="disable" />
        <param name="refer_delay" value="disable" />
        <param name="send_bye_after_refer" value="disable" />
        <param name="send_new_reg_when_recv_423" value="enable" />
        <param name="cseq_start_with_1" value="disable" />
        <param name="rtp_mode_when_holding" value="0" />
        <param name="support_huawei_cw" value="disable" />
        <param name="domain_query_type" value="0" />
        <param name="dns_resolve_interval" value="0" />
        <param name="early_media" value="enable" />
        <param name="rfc3262_prack" value="disable" />
        <param name="preack_only_183" value="disable" />
        <param name="early_answer" value="disable" />
        <param name="session_timer" value="disable" />
        <param name="session_expires" value="1800" />
        <param name="min_se" value="1800" />
        <param name="t1" value="500" />
        <param name="t2" value="4000" />
        <param name="t4" value="5000" />
        <param name="max_timeout" value="32000" />
        <param name="heartbeat_interval" value="10" />
        <param name="heartbeat_timeout" value="16" />
        <param name="username_in_option_for_server" value="heartbeat" />
        <param name="username_in_option_for_trunk" value="heartbeato" />
        <param name="retry_reg_interval" value="30" />
        <param name="reg_times_pers" value="1" />
        <code_switch0>
            <param name="response_code" value="0" />
            <param name="result_code" value="0" />
        </code_switch0>
        <code_switch1>
            <param name="response_code" value="0" />
            <param name="result_code" value="0" />
        </code_switch1>
        <code_switch2>
            <param name="response_code" value="0" />
            <param name="result_code" value="0" />
        </code_switch2>
        <code_switch3>
            <param name="response_code" value="0" />
            <param name="result_code" value="0" />
        </code_switch3>
        <cfg_wild_proxy_reg />
        <param name="3rd_not_send_18x" value="disable" />
        <param name="accept_orphan_200ok" value="disable" />
        <param name="dns_cache_enable" value="enable" />
        <param name="forbid_invalid_media" value="disable" />
        <param name="mwi_sig_type" value="0" />
        <param name="sharp_send_enable" value="enable" />
        <param name="SessionRefreshMethod" value="0" />
        <param name="sip_multipart_payload" value="disable" />
        <param name="report_hook_via_info" value="disable" />
        <param name="3way_mode" value="0" />
        <param name="mwi_neon_voltage" value="90" />
        <param name="user_agent_value" value="" />
        <param name="include_id" value="0" />
        <param name="id_hdr_separator" value="0" />
        <param name="url_display_policy" value="2" />
        <param name="mwi_expires" value="3600" />
    </sip>
    <fax>
        <param name="mode" value="3" />
        <param name="ced_cng_as_fax_tone" value="disable" />
        <param name="ecm" value="disable" />
        <param name="rate" value="14400" />
        <param name="tone_detect_by" value="0" />
        <param name="gpmd" value="disable" />
        <param name="x_fax" value="disable" />
        <param name="fax" value="disable" />
        <param name="x_modem" value="disable" />
        <param name="modem" value="disable" />
        <param name="fax_enable" value="enable" />
        <param name="vbd" value="enable" />
        <param name="silencesupp" value="enable" />
    </fax>
    <digitmap>
        <param name="digitmap" value="[*#]T|[*#][*#]|*x.T|**x.#|[*#]xx#|*#xx#|[*#][0-9*#]x[0-9*].x#|x.#|x.T" />
        <param name="match_fail_proc" value="1" />
    </digitmap>
    <feature_code>
        <feature0>
            <param name="default" value="*47*" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature0>
        <feature1>
            <param name="default" value="*50#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature1>
        <feature2>
            <param name="default" value="*51#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature2>
        <feature3>
            <param name="default" value="*72*" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature3>
        <feature4>
            <param name="default" value="*73#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature4>
        <feature5>
            <param name="default" value="*78" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature5>
        <feature6>
            <param name="default" value="*79" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature6>
        <feature7>
            <param name="default" value="*87*" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature7>
        <feature8>
            <param name="default" value="*90*" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature8>
        <feature9>
            <param name="default" value="*91#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature9>
        <feature10>
            <param name="default" value="*92*" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature10>
        <feature11>
            <param name="default" value="*93#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature11>
        <feature12>
            <param name="default" value="*111#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature12>
        <feature13>
            <param name="default" value="*114#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature13>
        <feature14>
            <param name="default" value="*149*" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature14>
        <feature15>
            <param name="default" value="*150*" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature15>
        <feature16>
            <param name="default" value="*152*" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature16>
        <feature17>
            <param name="default" value="*153*" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature17>
        <feature18>
            <param name="default" value="*156*" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature18>
        <feature19>
            <param name="default" value="*157*" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature19>
        <feature20>
            <param name="default" value="*158#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature20>
        <feature21>
            <param name="default" value="*159#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature21>
        <feature22>
            <param name="default" value="*160*" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature22>
        <feature23>
            <param name="default" value="*166*" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature23>
        <feature24>
            <param name="default" value="*193#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature24>
        <feature25>
            <param name="default" value="*194#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature25>
        <feature26>
            <param name="default" value="*195#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature26>
        <feature27>
            <param name="default" value="*196#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature27>
        <feature28>
            <param name="default" value="*197#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature28>
        <feature29>
            <param name="default" value="*198#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature29>
        <feature30>
            <param name="default" value="*199#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature30>
        <feature31>
            <param name="default" value="*97" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature31>
        <feature32>
            <param name="default" value="*353#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature32>
        <feature33>
            <param name="default" value="*354*" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature33>
        <feature34>
            <param name="default" value="*355#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature34>
        <feature35>
            <param name="default" value="*356#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature35>
        <feature36>
            <param name="default" value="*357#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature36>
        <feature37>
            <param name="default" value="*358#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature37>
        <feature38>
            <param name="default" value="*#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature38>
        <feature39>
            <param name="default" value="*165*" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature39>
        <feature40>
            <param name="default" value="*115#" />
            <param name="current" value="" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature40>
        <feature41>
            <param name="default" value="*170#" />
            <param name="current" value="0" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature41>
        <feature42>
            <param name="default" value="*171#" />
            <param name="current" value="0" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature42>
        <feature44>
            <param name="default" value="*00*" />
            <param name="current" value="0" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature44>
        <feature43>
            <param name="default" value="*168#" />
            <param name="current" value="0" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature43>
        <feature45>
            <param name="default" value="##" />
            <param name="current" value="0" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature45>
        <feature46>
            <param name="default" value="*154#" />
            <param name="current" value="0" />
            <param name="usedefault" value="enable" />
            <param name="enabled" value="enable" />
        </feature46>
    </feature_code>
    <system>
        <nat>
            <param name="nat_traversal" value="3" />
            <param name="stun_refresh_interval" value="60" />
            <param name="stun_server_addr" value="" />
            <param name="stun_server_port" value="3478" />
            <param name="nat_ip" value="" />
        </nat>
        <ntp>
            <param name="flag" value="on" />
            <param name="interval" value="3600" />
            <param name="zone hour" value="-6" />
            <param name="zone minute" value="0" />
            <server1>
                <param name="domain" value="0.pool.ntp.org" />
                <param name="port" value="123" />
            </server1>
            <server2>
                <param name="domain" value="1.pool.ntp.org" />
                <param name="port" value="123" />
            </server2>
            <DaylightSavingTime>
                <param name="flag" value="0" />
                <param name="start_month" value="3" />
                <param name="start_day" value="2" />
                <param name="start_weekday" value="7" />
                <param name="start_hour" value="2" />
                <param name="start_minute" value="0" />
                <param name="end_month" value="11" />
                <param name="end_day" value="1" />
                <param name="end_weekday" value="7" />
                <param name="end_hour" value="2" />
                <param name="end_minute" value="0" />
                <param name="save_time" value="60" />
            </DaylightSavingTime>
        </ntp>
        <daily_reboot>
            <param name="daily_reboot" value="disable" />
            <param name="daily_reboot_time" value="150" />
        </daily_reboot>
        <access_ctrl>
            <web>
                <param name="web_port" value="80" />
                <param name="ssl_port" value="443" />
                <param name="web_language" value="2" />
                <param name="rootdir" value="/tmp/web" />
                <users>
                    <user1>
                        <param name="username" value="admin" />
                        <param name="password" value="admin" />
                    </user1>
                </users>
                <param name="logout_time" value="5" />
                <param name="login_fail_locked_time" value="3" />
                <param name="login_fail_times" value="5" />
            </web>
            <cli>
                <param name="telnet_port" value="23" />
                <param name="idle_time" value="5" />
                <param name="max_count" value="5" />
                <users>
                    <user1>
                        <param name="username" value="admin" />
                        <param name="password" value="admin" />
                    </user1>
                </users>
                <param name="username_aes" value="admin" />
                <param name="password_aes" value="euLUpj0cPhoYeh/Yn0ce9Q==" />
                <param name="hex_pwd_aes" value="Gbxoqge58FJqH2FSaAp9VA==" />
            </cli>
            <ctrl>
                <param name="access_web_by_wan" value="disable" />
                <param name="access_web_by_lan" value="enable" />
                <param name="access_tel_by_wan" value="disable" />
                <param name="access_tel_by_lan" value="enable" />
            </ctrl>
        </access_ctrl>
        <param name="voice_hint_type" value="1" />
        <white_list_for_web>
            <param name="enable" value="disable" />
        </white_list_for_web>
        <white_list_for_telnet>
            <param name="enable" value="enable" />
            <param name="Telitem0" value="127.0.0.1" />
        </white_list_for_telnet>
        <param name="http_host_checking" value="disable" />
        <param name="summary_config" value="disable" />
        <cpufreq>
            <param name="cpumode" value="0" />
        </cpufreq>
        <param name="cdr_record" value="enable" />
        <param name="network_discnct_enable" value="disable" />
        <param name="system_log" value="disable" />
        <param name="network_fault_enable" value="disable" />
        <param name="only_accept_trusted" value="disable" />
        <param name="device_name" value="DAG2000-24S " />
        <entrypt_param>
            <param name="sip_encrypt" value="disable" />
            <param name="rtp_encrypt" value="0" />
            <param name="encrypt_mode" value="0" />
        </entrypt_param>
    </system>
    <provision>
        <param name="url" value="{{ $settings['provision_base_url'] ?? '' }}" />
        <param name="interval" value="86400" />
        <param name="provison_account" value="{{ $settings['http_auth_username'] ?? '' }}" />
        <param name="provison_password" value="{{ $settings['http_auth_password'] ?? '' }}" />
        <param name="proxy_domain" value="" />
        <param name="proxy_port" value="" />
        <param name="proxy_account" value="" />
        <param name="proxy_password" value="" />
        <param name="configfilekey" value="" />
        <param name="default_configuration_enable" value="disable" />
        <param name="check_method" value="0" />
        <param name="check_time" value="0" />
        <param name="check_prov_ca" value="disable" />
    </provision>
    <iptrunk />
    <route>
        <route_config>
            <param name="calls_from_ip" value="0" />
            <param name="calls_from_analog" value="0" />
            <param name="ip2ip_enable" value="disable" />
        </route_config>
        <ip_to_tel />
        <tel_to_ip_or_tel />
        <ip_to_ip />
    </route>
    <manipulation>
        <ip_to_tel_callee />
        <tel_to_iptel_caller />
        <tel_to_iptel_callee />
    </manipulation>
    <simserver>
        <param name="ip" value="" />
        <param name="port" value="" />
        <param name="password" value="" />
        <param name="localport" value="2020" />
        <param name="iceidletime" value="10" />
        <param name="srvchecktime" value="3600" />
        <param name="domain" value="" />
        <param name="default_configuration_enable" value="disable" />
        <param name="compress_enable" value="disable" />
    </simserver>
    <syslog>
        <param name="flag" value="off" />
        <param name="level" value="-1" />
        <param name="cdr" value="on" />
        <param name="ip" value="" />
        <param name="port" value="514" />
        <signal>
            <param name="53" value="off" />
            <param name="71" value="off" />
            <param name="83" value="off" />
            <param name="84" value="off" />
            <param name="111" value="off" />
            <param name="112" value="off" />
        </signal>
        <media>
            <param name="44" value="off" />
            <param name="113" value="off" />
        </media>
        <system>
            <param name="0" value="off" />
            <param name="1" value="off" />
            <param name="3" value="off" />
            <param name="4" value="off" />
            <param name="5" value="off" />
            <param name="17" value="off" />
            <param name="42" value="off" />
            <param name="50" value="off" />
            <param name="60" value="off" />
            <param name="93" value="off" />
        </system>
        <management>
            <param name="24" value="off" />
            <param name="25" value="off" />
            <param name="26" value="off" />
            <param name="40" value="off" />
            <param name="43" value="off" />
            <param name="78" value="off" />
            <param name="92" value="off" />
        </management>
        <param name="signal" value="off" />
        <param name="media" value="off" />
        <param name="system" value="off" />
        <param name="management" value="off" />
    </syslog>
    <filelog>
        <param name="flag" value="off" />
        <param name="level" value="-1" />
        <param name="file" value="/backup/apps/run.log" />
        <param name="size" value="256" />
        <signal>
            <param name="53" value="off" />
            <param name="71" value="off" />
            <param name="83" value="off" />
            <param name="84" value="off" />
            <param name="111" value="off" />
            <param name="112" value="off" />
        </signal>
        <media>
            <param name="44" value="off" />
            <param name="113" value="off" />
        </media>
        <system>
            <param name="0" value="off" />
            <param name="1" value="off" />
            <param name="3" value="off" />
            <param name="4" value="off" />
            <param name="5" value="off" />
            <param name="17" value="off" />
            <param name="42" value="off" />
            <param name="50" value="off" />
            <param name="60" value="off" />
            <param name="93" value="off" />
        </system>
        <management>
            <param name="24" value="off" />
            <param name="25" value="off" />
            <param name="26" value="off" />
            <param name="40" value="off" />
            <param name="43" value="off" />
            <param name="78" value="off" />
            <param name="92" value="off" />
        </management>
        <param name="signal" value="off" />
        <param name="media" value="off" />
        <param name="system" value="off" />
        <param name="management" value="off" />
    </filelog>
    <serverlog>
        <param name="flag" value="off" />
        <param name="level" value="-1" />
        <param name="cdr" value="off" />
        <param name="ip" value="" />
        <param name="port" value="514" />
        <param name="signal" value="off" />
        <param name="media" value="off" />
        <param name="system" value="off" />
        <param name="management" value="off" />
        <signal>
            <param name="53" value="off" />
            <param name="71" value="off" />
            <param name="83" value="off" />
            <param name="84" value="off" />
            <param name="111" value="off" />
            <param name="112" value="off" />
        </signal>
        <media>
            <param name="44" value="off" />
            <param name="113" value="off" />
        </media>
        <system>
            <param name="0" value="off" />
            <param name="1" value="off" />
            <param name="3" value="off" />
            <param name="4" value="off" />
            <param name="5" value="off" />
            <param name="17" value="off" />
            <param name="42" value="off" />
            <param name="50" value="off" />
            <param name="60" value="off" />
            <param name="93" value="off" />
        </system>
        <management>
            <param name="24" value="off" />
            <param name="25" value="off" />
            <param name="26" value="off" />
            <param name="40" value="off" />
            <param name="43" value="off" />
            <param name="78" value="off" />
            <param name="92" value="off" />
        </management>
    </serverlog>
    <snmp>
        <general>
            <param name="enable" value="disable" />
        </general>
        <user>
            <user0>
                <param name="user" value="" />
                <param name="auth_type" value="" />
                <param name="auth_pwd" value="" />
                <param name="privacy_type" value="" />
                <param name="privacy_password" value="" />
            </user0>
        </user>
        <community>
            <community0>
                <param name="community" value="" />
                <param name="source" value="" />
                <param name="sec_name" value="" />
                <param name="context_name" value="" />
            </community0>
            <community1>
                <param name="community" value="" />
                <param name="source" value="" />
                <param name="sec_name" value="" />
                <param name="context_name" value="" />
            </community1>
            <community2>
                <param name="community" value="" />
                <param name="source" value="" />
                <param name="sec_name" value="" />
                <param name="context_name" value="" />
            </community2>
            <community3>
                <param name="community" value="" />
                <param name="source" value="" />
                <param name="sec_name" value="" />
                <param name="context_name" value="" />
            </community3>
        </community>
        <group>
            <group0>
                <param name="security_model" value="0" />
                <param name="security_name" value="" />
                <param name="group_name" value="" />
            </group0>
            <group1>
                <param name="security_model" value="0" />
                <param name="security_name" value="" />
                <param name="group_name" value="" />
            </group1>
            <group2>
                <param name="security_model" value="0" />
                <param name="security_name" value="" />
                <param name="group_name" value="" />
            </group2>
            <group3>
                <param name="security_model" value="0" />
                <param name="security_name" value="" />
                <param name="group_name" value="" />
            </group3>
        </group>
        <view>
            <view0>
                <param name="name" value="" />
                <param name="type" value="0" />
                <param name="subtree" value="" />
                <param name="mask" value="" />
            </view0>
            <view1>
                <param name="name" value="" />
                <param name="type" value="0" />
                <param name="subtree" value="" />
                <param name="mask" value="" />
            </view1>
            <view2>
                <param name="name" value="" />
                <param name="type" value="0" />
                <param name="subtree" value="" />
                <param name="mask" value="" />
            </view2>
            <view3>
                <param name="name" value="" />
                <param name="subtree" value="" />
                <param name="mask" value="" />
                <param name="type" value="" />
            </view3>
        </view>
        <access>
            <access0>
                <param name="group" value="" />
                <param name="read" value="" />
                <param name="write" value="" />
                <param name="notify" value="" />
                <param name="context" value="" />
                <param name="security_model" value="0" />
                <param name="security_level" value="0" />
                <param name="context_match" value="0" />
            </access0>
            <access1>
                <param name="group" value="" />
                <param name="read" value="" />
                <param name="write" value="" />
                <param name="notify" value="" />
                <param name="context" value="" />
                <param name="security_model" value="0" />
                <param name="security_level" value="0" />
                <param name="context_match" value="0" />
            </access1>
            <access2>
                <param name="group" value="" />
                <param name="read" value="" />
                <param name="write" value="" />
                <param name="notify" value="" />
                <param name="context" value="" />
                <param name="security_model" value="0" />
                <param name="security_level" value="0" />
                <param name="context_match" value="0" />
            </access2>
            <access3>
                <param name="group" value="" />
                <param name="read" value="" />
                <param name="write" value="" />
                <param name="notify" value="" />
                <param name="context" value="" />
                <param name="security_model" value="0" />
                <param name="security_level" value="0" />
                <param name="context_match" value="0" />
            </access3>
        </access>
        <trap>
            <trap0>
                <param name="flag" value="0" />
                <param name="ip" value="" />
                <param name="port" value="0" />
                <param name="community" value="" />
            </trap0>
        </trap>
        <number>
            <param name="com2c" value="3" />
            <param name="group" value="3" />
            <param name="view" value="3" />
            <param name="access" value="3" />
            <param name="trap" value="3" />
            <param name="user" value="3" />
        </number>
        <param name="hook_status_trap_switch" value="0" />
    </snmp>
    <tr069>
        <param name="NotFirstInstall" value="305419896" />
        <param name="Enable" value="0" />
        <param name="RebootReason" value="5" />
        <agent>
            <acs>
                <param name="url" value="" />
                <param name="username" value="" />
                <param name="password" value="" />
                <param name="password_aes" value="" />
            </acs>
            <periodic>
                <param name="enable" value="1" />
                <param name="interval" value="30" />
                <param name="time" value="" />
            </periodic>
            <connreq>
                <param name="port" value="7547" />
                <param name="username" value="" />
                <param name="password" value="" />
                <param name="password_aes" value="" />
            </connreq>
            <param name="parameterkey" value="" />
            <param name="retry_times" value="3" />
            <param name="command_key" value="" />
            <param name="flag_reboot" value="0" />
        </agent>
        <stun>
            <param name="conn_req_addr" value="" />
            <param name="conn_req_notify_limit" value="10" />
            <param name="enable" value="0" />
            <param name="server_addr" value="" />
            <param name="server_port" value="3478" />
            <param name="username" value="" />
            <param name="password" value="" />
            <param name="max_keep_alive_period" value="10" />
            <param name="min_keep_alive_period" value="10" />
            <param name="nat_detected" value="0" />
            <param name="retry_times" value="3" />
            <param name="timeout" value="2" />
            <param name="time_wait" value="30" />
            <param name="stun_client_port" value="50001" />
            <param name="password_aes" value="" />
        </stun>
        <tasklist>
            <param name="count" value="0" />
            <FuncNames />
        </tasklist>
        <flag>
            <param name="FactoryResetFlag" value="0" />
            <param name="DiagnosticFlag" value="0" />
            <param name="DiagnosticStateFlag" value="0" />
        </flag>
        <Obj>
            <param name="UsedNum" value="5" />
            <MultiObjs>
                <MultiObj0>
                    <param name="param_path" value="1W" />
                    <param name="CurMaxInstanceIndex" value="0" />
                    <param name="used_num" value="1" />
                </MultiObj0>
                <MultiObj1>
                    <param name="param_path" value="1W1I" />
                    <param name="CurMaxInstanceIndex" value="0" />
                    <param name="used_num" value="1" />
                </MultiObj1>
                <MultiObj2>
                    <param name="param_path" value="1W1N" />
                    <param name="CurMaxInstanceIndex" value="0" />
                    <param name="used_num" value="1" />
                </MultiObj2>
                <MultiObj3>
                    <param name="param_path" value="1P" />
                    <param name="CurMaxInstanceIndex" value="0" />
                    <param name="used_num" value="1" />
                </MultiObj3>
                <MultiObj4>
                    <param name="param_path" value="1P1O" />
                    <param name="CurMaxInstanceIndex" value="0" />
                    <param name="used_num" value="16777215" />
                </MultiObj4>
            </MultiObjs>
        </Obj>
        <downinfo>
            <param name="flag" value="0" />
            <down>
                <param name="command_key" value="" />
                <param name="file_type" value="" />
                <param name="url" value="" />
                <param name="username" value="" />
                <param name="password" value="" />
                <param name="file_size" value="0" />
                <param name="target_file_name" value="" />
                <param name="delay_seconds" value="1" />
                <param name="success_url" value="" />
                <param name="failure_url" value="" />
            </down>
            <transcomplete>
                <param name="command_key" value="" />
                <param name="start_time" value="0" />
                <param name="complete_time" value="0" />
                <fault_struct>
                    <param name="fault_code" value="0" />
                    <param name="fault_string" value="" />
                </fault_struct>
            </transcomplete>
        </downinfo>
        <attr>
            <param name="ElementNum" value="0" />
            <param name="Index" value="0" />
            <ParamAttrs />
        </attr>
        <param name="http_upload_method" value="POST" />
        <param name="FirstUseDate" value="2023-2-14-3:4:22" />
    </tr069>
    <actionurl>
        <param name="HeartbeatInterval" value="10" />
    </actionurl>
    <relayserver>
        <param name="flag" value="off" />
        <param name="domain" value="" />
        <param name="port" value="3479" />
        <param name="port1" value="6479" />
        <param name="port2" value="12479" />
        <param name="port3" value="24479" />
        <param name="userid" value="" />
        <param name="password" value="123456" />
        <param name="secdomain" value="" />
        <param name="secport" value="unused" />
        <param name="secport1" value="unused" />
        <param name="secport2" value="unused" />
        <param name="secport3" value="unused" />
        <param name="secuserid" value="" />
        <param name="secpassword" value="" />
        <param name="sip" value="on" />
        <param name="rtp" value="on" />
        <param name="rtphead" value="off" />
        <param name="rtpcheck" value="on" />
        <param name="encrypt" value="on" />
        <param name="rtpcompress" value="off" />
        <param name="voicequality" value="off" />
        <param name="local_port" value="0" />
    </relayserver>
    <remote_server>
        <param name="default_server" value="disable" />
        <param name="domain" value="" />
        <param name="port" value="3100" />
        <param name="password" value="" />
    </remote_server>
    <record>
        <param name="enable" value="disable" />
        <param name="domain" value="" />
        <param name="port" value="2999" />
        <param name="max_num" value="2000" />
        <param name="period_mode" value="0" />
        <param name="start_time" value="00:00;" />
        <param name="end_time" value="23:59;" />
    </record>
    <nat_config>
        <param name="mode" value="3" />
        <param name="stun_refresh" value="60" />
        <param name="stun_port" value="3478" />
        <param name="stun_addr" value="" />
        <param name="nat_ip" value="" />
        <param name="dtr_addr" value="" />
        <param name="dtr_port" value="6579" />
        <param name="dtr_pwd" value="" />
        <param name="via_addr_type" value="0" />
        <param name="contact_addr_type" value="1" />
        <param name="sdp_addr_type" value="0" />
    </nat_config>
    <radius>
        <param name="enable" value="disable" />
        <param name="local_port" value="1645" />
        <param name="domain" value="" />
        <param name="auth_port" value="1645" />
        <param name="key" value="" />
    </radius>
    <inet_check_cfg>
        <param name="ip_conflict" value="enable" />
    </inet_check_cfg>
    <pbx>
        <param name="pbx_enable" value="disable" />
        <param name="direct_ext_enable" value="enable" />
        <param name="ivr_mode" value="0" />
        <param name="account_index" value="255" />
        <param name="dial_timeout" value="4" />
        <param name="ivr_play_count" value="3" />
        <param name="start_day" value="1" />
        <param name="end_day" value="7" />
        <param name="start_time0" value="00:00" />
        <param name="end_time0" value="12:00" />
        <param name="start_time1" value="12:00" />
        <param name="end_time1" value="23:59" />
        <param name="dtmf_num0" value="0" />
        <param name="dst_account_index0" value="254" />
        <param name="dtmf_num1" value="" />
        <param name="dst_account_index1" value="255" />
        <param name="dtmf_num2" value="" />
        <param name="dst_account_index2" value="255" />
        <param name="dtmf_num3" value="" />
        <param name="dst_account_index3" value="255" />
        <param name="dtmf_num4" value="" />
        <param name="dst_account_index4" value="255" />
        <param name="dtmf_num5" value="" />
        <param name="dst_account_index5" value="255" />
        <param name="dtmf_num6" value="" />
        <param name="dst_account_index6" value="255" />
        <param name="dtmf_num7" value="" />
        <param name="dst_account_index7" value="255" />
        <param name="dtmf_num8" value="" />
        <param name="dst_account_index8" value="255" />
        <param name="dtmf_num9" value="" />
        <param name="dst_account_index9" value="255" />
        <param name="dtmf_num10" value="" />
        <param name="dst_account_index10" value="255" />
        <param name="dtmf_num11" value="" />
        <param name="dst_account_index11" value="255" />
    </pbx>
    <speed_dial>
        <speed_dial0>
            <param name="src" value="" />
            <param name="dst" value="" />
        </speed_dial0>
        <speed_dial1>
            <param name="src" value="" />
            <param name="dst" value="" />
        </speed_dial1>
        <speed_dial2>
            <param name="src" value="" />
            <param name="dst" value="" />
        </speed_dial2>
        <speed_dial3>
            <param name="src" value="" />
            <param name="dst" value="" />
        </speed_dial3>
        <speed_dial4>
            <param name="src" value="" />
            <param name="dst" value="" />
        </speed_dial4>
        <speed_dial5>
            <param name="src" value="" />
            <param name="dst" value="" />
        </speed_dial5>
        <speed_dial6>
            <param name="src" value="" />
            <param name="dst" value="" />
        </speed_dial6>
        <speed_dial7>
            <param name="src" value="" />
            <param name="dst" value="" />
        </speed_dial7>
        <speed_dial8>
            <param name="src" value="" />
            <param name="dst" value="" />
        </speed_dial8>
        <speed_dial9>
            <param name="src" value="" />
            <param name="dst" value="" />
        </speed_dial9>
    </speed_dial>
    <call_limit />
    <sippnp>
        <param name="pnp_enable" value="enable" />
        <param name="pnp_srv_addr" value="224.0.1.75" />
        <param name="pnp_srv_port" value="5060" />
        <param name="pnp_interval" value="3600" />
    </sippnp>
    <dma>
        <param name="enable" value="disable" />
        <param name="port" value="0" />
        <param name="domain" value="" />
    </dma>
    <lanswitch_protect>
        <param name="enable" value="disable" />
        <param name="check_time" value="15" />
    </lanswitch_protect>
    <openvpn>
        <param name="enable" value="disable" />
        <param name="vpntype" value="0" />
        <param name="default_route" value="disable" />
        <param name="push_route" value="disable" />
        <param name="protocol" value="0" />
        <param name="device" value="0" />
        <param name="address" value="" />
        <param name="port" value="0" />
        <param name="sec_address" value="" />
        <param name="sec_port" value="0" />
        <param name="username" value="" />
        <param name="pwd" value="" />
        <param name="mode" value="0" />
    </openvpn>
    <vlan>
        <param name="trunkport" value="" />
        <portBind>
            <portBind0>
                <param name="vlanId" value="" />
                <param name="portmap" value="" />
            </portBind0>
            <portBind1>
                <param name="vlanId" value="" />
                <param name="portmap" value="" />
            </portBind1>
            <portBind2>
                <param name="vlanId" value="" />
                <param name="portmap" value="" />
            </portBind2>
            <portBind3>
                <param name="vlanId" value="" />
                <param name="portmap" value="" />
            </portBind3>
            <portBind4>
                <param name="vlanId" value="" />
                <param name="portmap" value="" />
            </portBind4>
            <portBind5>
                <param name="vlanId" value="" />
                <param name="portmap" value="" />
            </portBind5>
            <portBind6>
                <param name="vlanId" value="" />
                <param name="portmap" value="" />
            </portBind6>
            <portBind7>
                <param name="vlanId" value="" />
                <param name="portmap" value="" />
            </portBind7>
        </portBind>
    </vlan>
    <network>
        <local_network>
            <param name="ip_protocol" value="1" />
            <param name="network_mode" value="1" />
            <param name="wan_eth_mode" value="1" />
            <param name="net_mode" value="2" />
            <param name="wan_ip" value="192.168.11.1" />
            <param name="wan_mask" value="255.255.255.0" />
            <param name="wan_mtu" value="1500" />
            <param name="wan_gateway" value="" />
            <param name="ppp_username" value="" />
            <param name="ppp_password" value="" />
            <param name="ppp_servicename" value="" />
            <param name="manage_ip" value="" />
            <param name="manage_mask" value="" />
            <param name="lan_ethmode" value="1" />
            <param name="lan_ip" value="" />
            <param name="lan_mask" value="" />
            <param name="lan_mtu" value="1500" />
            <param name="use_peer_dns" value="disable" />
            <param name="wan_dns1" value="8.8.8.8" />
            <param name="wan_dns2" value="4.4.4.4" />
            <param name="ipv6_net_mode" value="2" />
            <param name="ipv6_ip" value="" />
            <param name="ipv6_gateway" value="" />
            <param name="ipv6_use_peer_dns" value="enable" />
            <param name="ipv6_dns1" value="" />
            <param name="ipv6_dns2" value="" />
        </local_network>
        <remote_manage>
            <param name="lan_http_enabled" value="enable" />
            <param name="wan_http_enabled" value="enable" />
            <param name="lan_telnet_enabled" value="enable" />
            <param name="wan_telnet_enabled" value="enable" />
        </remote_manage>
        <qos>
            <param name="lan_qos_flag" value="disable" />
            <param name="lan0_priority" value="0" />
            <param name="lan0_flowcontrol" value="0" />
            <param name="lan0_incominglimit" value="9" />
            <param name="lan0_outgoinglimit" value="9" />
            <param name="lan1_priority" value="0" />
            <param name="lan1_flowcontrol" value="0" />
            <param name="lan1_incominglimit" value="9" />
            <param name="lan1_outgoinglimit" value="9" />
            <param name="lan2_priority" value="0" />
            <param name="lan2_flowcontrol" value="0" />
            <param name="lan2_incominglimit" value="9" />
            <param name="lan2_outgoinglimit" value="9" />
            <param name="lan3_priority" value="0" />
            <param name="lan3_flowcontrol" value="0" />
            <param name="lan3_incominglimit" value="9" />
            <param name="lan3_outgoinglimit" value="9" />
            <param name="lan_aos_enable" value="disable" />
            <param name="lan_qos0" value="0" />
            <param name="lan_qos1" value="0" />
            <param name="lan_qos2" value="0" />
        </qos>
        <vlan>
            <param name="wan_vlanid_router" value="0" />
            <param name="lan_vlanid_router" value="0" />
            <param name="vlan1_enabled" value="disable" />
            <param name="vlan1_index_num" value="0" />
            <param name="vlan1_group" value="0" />
            <param name="vlan1_id" value="1" />
            <param name="vlan1_priid" value="0" />
            <param name="vlan1_ip_mode" value="2" />
            <param name="vlan1_ip" value="" />
            <param name="vlan1_mask" value="" />
            <param name="vlan1_gateway" value="" />
            <param name="vlan1_use_peerdns" value="1" />
            <param name="vlan1_dns1" value="" />
            <param name="vlan1_dns2" value="" />
            <param name="vlan1_pppoe_name" value="" />
            <param name="vlan1_pppoe_password" value="" />
            <param name="vlan1_mtu" value="1400" />
            <param name="vlan1_lan0" value="0" />
            <param name="vlan1_lan1" value="0" />
            <param name="vlan1_lan2" value="0" />
            <param name="vlan2_enabled" value="disable" />
            <param name="vlan2_index_num" value="0" />
            <param name="vlan2_group" value="0" />
            <param name="vlan2_id" value="2" />
            <param name="vlan2_priid" value="0" />
            <param name="vlan2_ip_mode" value="2" />
            <param name="vlan2_ip" value="" />
            <param name="vlan2_mask" value="" />
            <param name="vlan2_gateway" value="" />
            <param name="vlan2_use_peerdns" value="1" />
            <param name="vlan2_dns1" value="" />
            <param name="vlan2_dns2" value="" />
            <param name="vlan2_pppoe_name" value="" />
            <param name="vlan2_pppoe_password" value="" />
            <param name="vlan2_mtu" value="1400" />
            <param name="vlan2_lan0" value="0" />
            <param name="vlan2_lan1" value="0" />
            <param name="vlan2_lan2" value="0" />
            <param name="vlan3_enabled" value="disable" />
            <param name="vlan3_index_num" value="0" />
            <param name="vlan3_group" value="0" />
            <param name="vlan3_id" value="3" />
            <param name="vlan3_priid" value="0" />
            <param name="vlan3_ip_mode" value="2" />
            <param name="vlan3_ip" value="" />
            <param name="vlan3_mask" value="" />
            <param name="vlan3_gateway" value="" />
            <param name="vlan3_use_peerdns" value="1" />
            <param name="vlan3_dns1" value="" />
            <param name="vlan3_dns2" value="" />
            <param name="vlan3_pppoe_name" value="" />
            <param name="vlan3_pppoe_password" value="" />
            <param name="vlan3_mtu" value="1400" />
            <param name="vlan3_lan0" value="0" />
            <param name="vlan3_lan1" value="0" />
            <param name="vlan3_lan2" value="0" />
            <param name="vlan4_enabled" value="disable" />
            <param name="vlan4_index_num" value="0" />
            <param name="vlan4_group" value="0" />
            <param name="vlan4_id" value="0" />
            <param name="vlan4_priid" value="0" />
            <param name="vlan4_ip_mode" value="0" />
            <param name="vlan4_ip" value="" />
            <param name="vlan4_mask" value="" />
            <param name="vlan4_gateway" value="" />
            <param name="vlan4_use_peerdns" value="0" />
            <param name="vlan4_dns1" value="" />
            <param name="vlan4_dns2" value="" />
            <param name="vlan4_pppoe_name" value="" />
            <param name="vlan4_pppoe_password" value="" />
            <param name="vlan4_mtu" value="0" />
            <param name="vlan4_lan0" value="0" />
            <param name="vlan4_lan1" value="0" />
            <param name="vlan4_lan2" value="0" />
        </vlan>
        <nat_fwds>
            <nat_fwd0>
                <param name="run_flag" value="disable" />
                <param name="protocol" value="0" />
                <param name="src_ip" value="" />
                <param name="src_port" value="0" />
            </nat_fwd0>
            <nat_fwd1>
                <param name="run_flag" value="disable" />
                <param name="protocol" value="0" />
                <param name="src_ip" value="" />
                <param name="src_port" value="0" />
            </nat_fwd1>
            <nat_fwd2>
                <param name="run_flag" value="disable" />
                <param name="protocol" value="0" />
                <param name="src_ip" value="" />
                <param name="src_port" value="0" />
            </nat_fwd2>
            <nat_fwd3>
                <param name="run_flag" value="disable" />
                <param name="protocol" value="0" />
                <param name="src_ip" value="" />
                <param name="src_port" value="0" />
            </nat_fwd3>
            <nat_fwd4>
                <param name="run_flag" value="disable" />
                <param name="protocol" value="0" />
                <param name="src_ip" value="" />
                <param name="src_port" value="0" />
            </nat_fwd4>
            <nat_fwd5>
                <param name="run_flag" value="disable" />
                <param name="protocol" value="0" />
                <param name="src_ip" value="" />
                <param name="src_port" value="0" />
            </nat_fwd5>
            <nat_fwd6>
                <param name="run_flag" value="disable" />
                <param name="protocol" value="0" />
                <param name="src_ip" value="" />
                <param name="src_port" value="0" />
            </nat_fwd6>
            <nat_fwd7>
                <param name="run_flag" value="disable" />
                <param name="protocol" value="0" />
                <param name="src_ip" value="" />
                <param name="src_port" value="0" />
            </nat_fwd7>
        </nat_fwds>
        <static_routes>
            <static_route0>
                <param name="run_flag" value="disable" />
                <param name="dst_ip" value="" />
                <param name="sub_mask" value="" />
                <param name="next_hop" value="" />
            </static_route0>
            <static_route1>
                <param name="run_flag" value="disable" />
                <param name="dst_ip" value="" />
                <param name="sub_mask" value="" />
                <param name="next_hop" value="" />
            </static_route1>
            <static_route2>
                <param name="run_flag" value="disable" />
                <param name="dst_ip" value="" />
                <param name="sub_mask" value="" />
                <param name="next_hop" value="" />
            </static_route2>
            <static_route3>
                <param name="run_flag" value="disable" />
                <param name="dst_ip" value="" />
                <param name="sub_mask" value="" />
                <param name="next_hop" value="" />
            </static_route3>
            <static_route4>
                <param name="run_flag" value="disable" />
                <param name="dst_ip" value="" />
                <param name="sub_mask" value="" />
                <param name="next_hop" value="" />
            </static_route4>
            <static_route5>
                <param name="run_flag" value="disable" />
                <param name="dst_ip" value="" />
                <param name="sub_mask" value="" />
                <param name="next_hop" value="" />
            </static_route5>
            <static_route6>
                <param name="run_flag" value="disable" />
                <param name="dst_ip" value="" />
                <param name="sub_mask" value="" />
                <param name="next_hop" value="" />
            </static_route6>
            <static_route7>
                <param name="run_flag" value="disable" />
                <param name="dst_ip" value="" />
                <param name="sub_mask" value="" />
                <param name="next_hop" value="" />
            </static_route7>
        </static_routes>
        <dmz>
            <param name="dmz_enabled" value="disable" />
            <param name="dmz_ip" value="" />
        </dmz>
        <dhcp_server>
            <param name="run_flag" value="disable" />
            <param name="dhcp_lease_time" value="259200" />
            <param name="dhcp_ip_start" value="192.168.11.100" />
            <param name="dhcp_ip_end" value="192.168.11.199" />
            <param name="dhcp_mask" value="255.255.255.0" />
            <param name="dhcp_gateway" value="192.168.11.1" />
            <param name="dhcp_dns1" value="192.168.11.1" />
            <param name="dhcp_dns2" value="" />
        </dhcp_server>
        <dhcp_option>
            <wan>
                <param name="run_flag" value="disable" />
                <param name="log_server" value="disable" />
                <param name="static_route" value="disable" />
                <param name="tftp_server" value="disable" />
                <param name="sip_server" value="disable" />
                <param name="class_static_route" value="disable" />
                <param name="ntp_server" value="disable" />
                <param name="tr069_acs_url" value="disable" />
                <param name="vendor_id" value="disable" />
            </wan>
        </dhcp_option>
        <arp_static_route>
            <param name="ARP_IP_0" value="" />
            <param name="ARP_MAC_0" value="" />
            <param name="ARP_IP_1" value="" />
            <param name="ARP_MAC_1" value="" />
            <param name="ARP_IP_2" value="" />
            <param name="ARP_MAC_2" value="" />
            <param name="ARP_IP_3" value="" />
            <param name="ARP_MAC_3" value="" />
            <param name="ARP_IP_4" value="" />
            <param name="ARP_MAC_4" value="" />
            <param name="ARP_IP_5" value="" />
            <param name="ARP_MAC_5" value="" />
            <param name="ARP_IP_6" value="" />
            <param name="ARP_MAC_6" value="" />
            <param name="ARP_IP_7" value="" />
            <param name="ARP_MAC_7" value="" />
            <param name="ARP_IP_8" value="" />
            <param name="ARP_MAC_8" value="" />
            <param name="ARP_IP_9" value="" />
            <param name="ARP_MAC_9" value="" />
            <param name="ARP_IP_10" value="" />
            <param name="ARP_MAC_10" value="" />
            <param name="ARP_IP_11" value="" />
            <param name="ARP_MAC_11" value="" />
            <param name="ARP_IP_12" value="" />
            <param name="ARP_MAC_12" value="" />
            <param name="ARP_IP_13" value="" />
            <param name="ARP_MAC_13" value="" />
            <param name="ARP_IP_14" value="" />
            <param name="ARP_MAC_14" value="" />
            <param name="ARP_IP_15" value="" />
            <param name="ARP_MAC_15" value="" />
        </arp_static_route>
        <security>
            <param name="ip_filters_disabled" value="disable" />
            <param name="mac_filters_disabled" value="disable" />
            <param name="domain_filters_disabled" value="disable" />
            <ip_filters>
                <ip_filters0>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters0>
                <ip_filters1>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters1>
                <ip_filters2>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters2>
                <ip_filters3>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters3>
                <ip_filters4>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters4>
                <ip_filters5>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters5>
                <ip_filters6>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters6>
                <ip_filters7>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters7>
                <ip_filters8>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters8>
                <ip_filters9>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters9>
                <ip_filters10>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters10>
                <ip_filters11>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters11>
                <ip_filters12>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters12>
                <ip_filters13>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters13>
                <ip_filters14>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters14>
                <ip_filters15>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters15>
                <ip_filters16>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters16>
                <ip_filters17>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters17>
                <ip_filters18>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters18>
                <ip_filters19>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters19>
                <ip_filters20>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters20>
                <ip_filters21>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters21>
                <ip_filters22>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters22>
                <ip_filters23>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters23>
                <ip_filters24>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters24>
                <ip_filters25>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters25>
                <ip_filters26>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters26>
                <ip_filters27>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters27>
                <ip_filters28>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters28>
                <ip_filters29>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters29>
                <ip_filters30>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters30>
                <ip_filters31>
                    <param name="enabled" value="disable" />
                    <param name="protocol" value="0" />
                    <param name="local_ip_start" value="" />
                    <param name="local_ip_end" value="" />
                    <param name="local_port_start" value="0" />
                    <param name="local_port_end" value="0" />
                    <param name="remote_ip_start" value="" />
                    <param name="remote_ip_end" value="" />
                    <param name="remote_port_start" value="0" />
                    <param name="remote_port_end" value="0" />
                </ip_filters31>
            </ip_filters>
            <mac_filters>
                <mac_filters0>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters0>
                <mac_filters1>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters1>
                <mac_filters2>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters2>
                <mac_filters3>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters3>
                <mac_filters4>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters4>
                <mac_filters5>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters5>
                <mac_filters6>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters6>
                <mac_filters7>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters7>
                <mac_filters8>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters8>
                <mac_filters9>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters9>
                <mac_filters10>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters10>
                <mac_filters11>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters11>
                <mac_filters12>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters12>
                <mac_filters13>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters13>
                <mac_filters14>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters14>
                <mac_filters15>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters15>
                <mac_filters16>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters16>
                <mac_filters17>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters17>
                <mac_filters18>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters18>
                <mac_filters19>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters19>
                <mac_filters20>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters20>
                <mac_filters21>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters21>
                <mac_filters22>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters22>
                <mac_filters23>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters23>
                <mac_filters24>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters24>
                <mac_filters25>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters25>
                <mac_filters26>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters26>
                <mac_filters27>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters27>
                <mac_filters28>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters28>
                <mac_filters29>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters29>
                <mac_filters30>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters30>
                <mac_filters31>
                    <param name="enabled" value="disable" />
                    <param name="mac_address" value="" />
                    <param name="describe" value="" />
                </mac_filters31>
            </mac_filters>
            <dm_filters>
                <dm_filters0>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters0>
                <dm_filters1>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters1>
                <dm_filters2>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters2>
                <dm_filters3>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters3>
                <dm_filters4>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters4>
                <dm_filters5>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters5>
                <dm_filters6>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters6>
                <dm_filters7>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters7>
                <dm_filters8>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters8>
                <dm_filters9>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters9>
                <dm_filters10>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters10>
                <dm_filters11>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters11>
                <dm_filters12>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters12>
                <dm_filters13>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters13>
                <dm_filters14>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters14>
                <dm_filters15>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters15>
                <dm_filters16>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters16>
                <dm_filters17>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters17>
                <dm_filters18>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters18>
                <dm_filters19>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters19>
                <dm_filters20>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters20>
                <dm_filters21>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters21>
                <dm_filters22>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters22>
                <dm_filters23>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters23>
                <dm_filters24>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters24>
                <dm_filters25>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters25>
                <dm_filters26>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters26>
                <dm_filters27>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters27>
                <dm_filters28>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters28>
                <dm_filters29>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters29>
                <dm_filters30>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters30>
                <dm_filters31>
                    <param name="enabled" value="disable" />
                    <param name="domain" value="" />
                </dm_filters31>
            </dm_filters>
        </security>
    </network>
</config>
@break

@endswitch