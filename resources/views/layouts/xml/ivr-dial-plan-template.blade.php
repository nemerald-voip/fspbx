<extension name="{{ $ivr->ivr_menu_name }}" continue="{{ $dialplan_continue }}" uuid="{{ $ivr->dialplan_uuid }}">
    <condition field="destination_number" expression="^{{ $ivr->ivr_menu_extension }}$">
		<action application="ring_ready" data=""/>
		<action application="answer" data=""/>
		<action application="sleep" data="1000"/>
		<action application="set" data="hangup_after_bridge=true"/>
        <action application="set" data="ringback={{ $ivr->ivr_menu_ringback }}" />
        <action application="set" data="transfer_ringback={{ $ivr->ivr_menu_ringback }}" />
        <action application="set" data="ivr_menu_uuid={{ $ivr->ivr_menu_uuid  }}"/>
        <action application="ivr" data="{{ $ivr->ivr_menu_uuid  }}"/>
        <action application="hangup" data=""/>
    </condition>
</extension>
