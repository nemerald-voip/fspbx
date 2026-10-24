{{--
    Flag overrides, checked before the normal route. Each argument is a flag
    UUID, with :inactive when the row applies while the flag is inactive.
    flag_check.lua returns the position of the first row whose flag is in that
    state, or 0. Anything other than a listed position (no match, missing flag,
    database down) falls through to the normal route.
--}}
@if ($overrides->isNotEmpty())
	<condition field="destination_number" expression="^{{ $extension }}$">
		<action application="set" data="flag_match=${lua(lua/flag_check.lua {{ $domain_uuid }} {{ $overrides->map(fn ($override) => $override->flag_uuid . ($override->match_state === 'inactive' ? ':inactive' : ''))->implode(' ') }})}" inline="true"/>
	</condition>
@foreach ($overrides->values() as $index => $override)
	<condition field="${flag_match}" expression="^{{ $index + 1 }}$" break="on-true">
		<action application="set" data="flag_override_uuid={{ $override->flag_uuid }}"/>
		<action application="{{ $override->override_app }}" data="{{ $override->override_data }}"/>
@if ($override->override_app === 'lua' && str_starts_with((string) $override->override_data, 'streamfile.lua'))
		<action application="hangup" data="NORMAL_CLEARING"/>
@endif
	</condition>
@endforeach
@endif
