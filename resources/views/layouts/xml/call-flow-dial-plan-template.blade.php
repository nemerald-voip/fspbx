<extension name="{{ $call_flow->call_flow_name }}" continue="" uuid="{{ $call_flow->dialplan_uuid }}">
@if (filled($call_flow->call_flow_feature_code))
	<condition field="destination_number" expression="^{{ $destination_feature }}$" break="on-true">
		<action application="answer" data=""/>
		<action application="sleep" data="200"/>
		<action application="set" data="feature_code=true"/>
		<action application="set" data="call_flow_uuid={{ $call_flow->call_flow_uuid }}"/>
		<action application="lua" data="lua/flow_toggle.lua"/>
	</condition>
@endif
	<condition field="destination_number" expression="^{{ $destination_extension }}$">
		<action application="set" data="call_flow_uuid={{ $call_flow->call_flow_uuid }}"/>
		<action application="lua" data="call_flow.lua"/>
	</condition>
</extension>
