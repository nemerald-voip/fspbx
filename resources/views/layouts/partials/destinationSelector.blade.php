@php
    $arrval = $id;
    if($id === '__NEWROWID__') {
        $arrval = 'newrow'.$arrval;
    }
@endphp
<div class="d-flex">
    <div class="mx-1">
        <select onchange="(function(){if($('#destination_type_{{$id}}').val() === 'external'){$('#destination_target_external_wrapper_{{$id}}').show();$('#destination_target_internal_wrapper_{{$id}}').hide()} else {$('#destination_target_internal_wrapper_{{$id}}').show();$('#destination_target_external_wrapper_{{$id}}').hide()}})()" id="destination_type_{{$id}}" name="follow_me_destinations[{{$arrval}}][type]">
            <option value="internal" @if (!detect_if_phone_number($value)) selected @endif>Internal</option>
            <option value="external" @if (detect_if_phone_number($value)) selected @endif>External</option>
        </select>
    </div>
    <div class="flex-fill">
        <div id="destination_target_external_wrapper_{{$id}}" class="destination_wrapper"
             @if (!detect_if_phone_number($value)) style="display: none;" @endif
        >
            <input type="text" id="destination_target_external_{{$id}}"
                   class="form-control dest-external" name="follow_me_destinations[{{$arrval}}][target_external]"
                   placeholder="Enter phone number" value="{{$value}}" />
        </div>
        <div id="destination_target_internal_wrapper_{{$id}}" class="destination_wrapper"
             @if (detect_if_phone_number($value)) style="display: none;" @endif
        >
            <select id="destination_target_internal_{{$id}}"
                    class="dest-internal"
                    name="follow_me_destinations[{{$arrval}}][target_internal]">
                @foreach($extensions as $ext)
                    <option value="{{ $ext->extension }}" @if($value == $ext->extension) selected @endif>
                        {{ $ext->extension }} - @if(!empty($ext->effective_caller_id_name)) {{ $ext->effective_caller_id_name }} @else {{ $ext->description }} @endif
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</div>
<div class="text-danger follow_me_destinations_{{$arrval}}_target_external_err error_message"></div>
<div class="text-danger follow_me_destinations_{{$arrval}}_target_internal_err error_message"></div>
