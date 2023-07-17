<div class="row">
    <div class="col-md-8">
        <div class="mb-3">
            <label for="timeout_action" class="form-label">If not answered, calls will be sent</label>
            <div class="row">
                <div class="col-md-4 col-sm-4">
                    <select class="select2 form-control" data-toggle="select2" data-placeholder="Choose ..."
                            id="timeout_category" name="timeout_category">
                        <option value="disabled" @if($destinationsByCategory == 'disabled') selected="selected" @endif>
                            Disabled
                        </option>
                        <option value="ringgroup" @if($destinationsByCategory == 'ringgroup') selected="selected" @endif>
                            Ring Groups
                        </option>
                        <option value="dialplans" @if($destinationsByCategory == 'dialplans') selected="selected" @endif>
                            Dial Plans
                        </option>
                        <option value="extensions" @if($destinationsByCategory == 'extensions') selected="selected" @endif>
                            Extensions
                        </option>
                        <option value="timeconditions" @if($destinationsByCategory == 'timeconditions') selected="selected" @endif>
                            Time Conditions
                        </option>
                        <option value="voicemails" @if($destinationsByCategory == 'voicemails') selected="selected" @endif>
                            Voicemails
                        </option>
                        <option value="others" @if($destinationsByCategory == 'others') selected="selected" @endif>
                            Others
                        </option>
                    </select>
                </div>
                <div id="timeout_action_wrapper" class="col-md-8 col-sm-8"
                     @if($destinationsByCategory == 'disabled') style="display: none" @endif>
                    @foreach($timeoutDestinationsByCategory as $category => $items)
                        <div id="timeout_action_wrapper_{{$category}}" @if($destinationsByCategory != $category) style="display: none" @endif>
                            <select class="select2 form-control" data-toggle="select2" data-placeholder="Choose ..."
                                    id="timeout_action_{{$category}}" name="timeout_action_{{$category}}">
                                @foreach($items as $item)
                                    <option value="{{$item['id']}}"
                                            @if($ringGroup->ring_group_timeout_data == $item['id']) selected="selected" @endif>
                                        {{$item['label']}}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>
            <div id="timeout_data_err" class="text-danger error_message"></div>
        </div>
    </div>
</div>
