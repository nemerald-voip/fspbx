@php
    /**
     * @property string $type
     * @property string $id
     * @property string $value
     * @property array $extensions
     */
        $arrval = $id;
        if($id === '__NEWROWID__') {
            $arrval = 'newrow'.$arrval;
        }
@endphp
<div class="d-flex">
    <div class="mx-1">
        <select onchange="(function(){if($('#{{$type}}_type_{{$id}}').val() === 'external'){$('#{{$type}}_target_external_wrapper_{{$id}}').show();$('#{{$type}}_target_internal_wrapper_{{$id}}').hide()} else {$('#{{$type}}_target_internal_wrapper_{{$id}}').show();$('#{{$type}}_target_external_wrapper_{{$id}}').hide()}})()" id="{{$type}}_type_{{$id}}" name="{{$type}}[{{$arrval}}][type]">
            <option value="internal" @if (!detect_if_phone_number($value)) selected @endif>Internal</option>
            <option value="external" @if (detect_if_phone_number($value)) selected @endif>External</option>
        </select>
    </div>
    <div class="flex-fill">
        <div id="{{$type}}_target_external_wrapper_{{$id}}" class="destination_wrapper"
             @if (!detect_if_phone_number($value)) style="display: none;" @endif
        >
            <input type="text" id="{{$type}}_target_external_{{$id}}"
                   class="form-control dest-external" name="{{$type}}[{{$arrval}}][target_external]"
                   placeholder="Enter phone number"
                   @if (detect_if_phone_number($value))
                       value="{{$value}}"
                   @else
                       value=""
                   @endif
            />
        </div>
        <div id="{{$type}}_target_internal_wrapper_{{$id}}" class="destination_wrapper"
             @if (detect_if_phone_number($value)) style="display: none;" @endif
        >
            <select id="{{$type}}_target_internal_{{$id}}"
                    class="dest-internal"
                    name="{{$type}}[{{$arrval}}][target_internal]">
                @foreach($extensions as $ext)
                    <option value="{{ $ext->extension }}" @if($value == $ext->extension) selected @endif>
                        {{ $ext->extension }} - @if(!empty($ext->effective_caller_id_name)) {{ $ext->effective_caller_id_name }} @else {{ $ext->description }} @endif
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</div>
<div class="text-danger {{$type}}_{{$arrval}}_target_external_err error_message"></div>
<div class="text-danger {{$type}}_{{$arrval}}_target_internal_err error_message"></div>
