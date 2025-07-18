@php
    use Illuminate\Support\Str;
@endphp

<extension name="{{ $fax->fax_name }}" continue="false" uuid="{{ $fax->dialplan_uuid }}">
    <condition field="destination_number" expression="^{{ $fax->fax_destination_number }}$">
        <action application="answer" data=""/>
        <action application="set" data="fax_uuid={{ $fax->fax_uuid }}"/>
        <action application="set" data="api_hangup_hook=lua app/fax/resources/scripts/hangup_rx.lua" inline="true" />

        @foreach ($settings as $data)
            @php
                $value = $data->default_setting_value;
            @endphp

            @if (Str::startsWith($value, 'inbound:'))
                <action application="set" data="{{ substr($value, 8) }}" />
            @elseif (Str::startsWith($value, 'outbound:'))
                {{-- Skip or handle outbound if needed --}}
            @else
                <action application="set" data="{{ $value }}" />
            @endif
        @endforeach

        <action application="set" data="{!! $last_fax !!}" />
        <action application="rxfax" data="{!! $rxfax_data !!}" />
        <action application="hangup" data="" />
    </condition>
</extension>
