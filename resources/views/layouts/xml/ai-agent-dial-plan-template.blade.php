<extension name="{{ $agent->agent_name }}" continue="{{ $dialplan_continue }}" uuid="{{ $agent->dialplan_uuid }}">
    <condition field="destination_number" expression="^{{ $agent->agent_extension }}$">
        <action application="ring_ready" data="" />
        <action application="answer" data="" />
        <action application="sleep" data="1000" />
        <action application="set" data="hangup_after_bridge=true" />
        <action application="set" data="absolute_codec_string=PCMU,PCMA" />
        <action application="set" data="ringback=$${us-ring}" />
        <action application="set" data="transfer_ringback=$${us-ring}" />
        <action application="set" data="ignore_early_media=true" />
        <action application="set" data="ai_agent_uuid={{ $agent->ai_agent_uuid }}" />
        <action application="bridge" data="sofia/external/sip:{{ $agent->agent_extension }}@sip.rtc.elevenlabs.io:5060;transport=tcp" />
    </condition>
</extension>
