<extension name="{{ $ring_group->ring_group_name }}" continue="" uuid="{{ $ring_group->dialplan_uuid }}">
@include('layouts.xml.flag-override-conditions', [
	'extension' => $ring_group->ring_group_extension,
	'overrides' => $ring_group->relationLoaded('flagOverrides') ? $ring_group->flagOverrides : $ring_group->flagOverrides()->get(),
	'domain_uuid' => $ring_group->domain_uuid,
])
	<condition field="destination_number" expression="^{{ $ring_group->ring_group_extension }}$">
		<action application="ring_ready" data=""/>
	    <action application="set" data="ring_group_uuid={{ $ring_group->ring_group_uuid }}"/>
        <action application="lua" data="app.lua ring_groups"/>
    </condition>
</extension>