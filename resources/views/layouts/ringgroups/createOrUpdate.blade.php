@extends('layouts.horizontal', ["page_title"=> "Edit Ring Group"])

@section('content')
<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('ring-groups.index') }}">Ring Groups</a></li>
                        @if($ringGroup->exists)
                            <li class="breadcrumb-item active">Edit Ring Group</li>
                        @else
                            <li class="breadcrumb-item active">Create Ring Group</li>
                        @endif
                    </ol>
                </div>
                @if($ringGroup->exists)
                    <h4 class="page-title">Edit Ring Group ({{ $ringGroup->ring_group_name }})</h4>
                @else
                    <h4 class="page-title">Create Ring Group</h4>
                @endif
            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @php
                        if ($ringGroup->exists) {
                            $actionUrl = route('ring-groups.update', $ringGroup);
                        } else {
                            $actionUrl = route('ring-groups.store');
                        }
                    @endphp
                    <form method="POST" id="ringGroupForm" action="{{$actionUrl}}" class="form">
                        @if ($ringGroup->exists)
                            @method('put')
                        @endif
                        @csrf
                    <div class="row">
                        <div class="col-sm-2 mb-2 mb-sm-0">
                            <div class="nav flex-column nav-pills" id="extensionNavPills" role="tablist" aria-orientation="vertical">
                                <a class="nav-link active show" id="v-pills-home-tab" data-bs-toggle="pill" href="#v-pills-home" role="tab" aria-controls="v-pills-home"
                                    aria-selected="true">
                                    <i class="mdi mdi-home-variant d-md-none d-block"></i>
                                    <span class="d-none d-md-block">Basic Information
                                        <span class="float-end text-end
                                            ring_group_extension_badge
                                            " hidden><span class="badge badge-danger-lighten">error</span></span>
                                    </span>
                                </a>
                                <a class="nav-link" id="v-pills-callforward-tab" data-bs-toggle="pill" href="#v-pills-callforward" role="tab" aria-controls="v-pills-callforward"
                                   aria-selected="false">
                                    <i class="mdi mdi-settings-outline d-md-none d-block"></i>
                                    <span class="d-none d-md-block">Call Forward
                                        <span class="float-end text-end
                                            forward_all_enabled_err_badge
                                            forward_all_destination_err_badge
                                            forward_busy_enabled_err_badge
                                            forward_busy_destination_err_badge
                                            forward_no_answer_enabled_err_badge
                                            forward_no_answer_destination_err_badge
                                            forward_user_not_registered_enabled_err_badge
                                            forward_user_not_registered_destination_err_badge
                                            " hidden><span class="badge badge-danger-lighten">error</span></span>
                                    </span>
                                </a>
                            </div>
                        </div> <!-- end col-->

                            <div class="col-sm-10">

                                <div class="tab-content">
                                    <div class="text-sm-end" id="action-buttons">
                                        <a href="{{ route('ring-groups.index') }}" class="btn btn-light me-2">Cancel</a>
                                        <button class="btn btn-success" type="submit" id="submitFormButton"><i class="uil uil-down-arrow me-2"></i> Save </button>
                                        {{-- <button class="btn btn-success" type="submit">Save</button> --}}
                                    </div>
                                    <div class="tab-pane fade active show" id="v-pills-home" role="tabpanel" aria-labelledby="v-pills-home-tab">
                                        <!-- Basic Info Content-->
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <h4 class="mt-2">Basic information</h4>

                                                <p class="text-muted mb-4">Provide basic information about the ring group</p>

                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_extension" class="form-label">Ring Group number <span class="text-danger">*</span></label>
                                                                <input class="form-control" type="text" placeholder="xxx" id="ring_group_extension"
                                                                       name="ring_group_extension" value="{{ $ringGroup->ring_group_extension }}"
                                                                @if ($ringGroup->exists) readonly @endif />
                                                                <div id="ring_group_extension_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_greeting" class="form-label">Greeting</label>
                                                                <select class="select2 form-control" data-toggle="select2" data-placeholder="Choose ..."
                                                                        id="ring_group_greeting" name="ring_group_greeting">
                                                                    <option value=""></option>
                                                                </select>
                                                                <div id="ring_group_greeting_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_strategy" class="form-label">Strategy</label>
                                                                <select class="select2 form-control" data-toggle="select2" data-placeholder="Choose ..." id="ring_group_strategy" name="ring_group_strategy">
                                                                    <option value="simultaneous" @if($ringGroup->ring_group_strategy == 'simultaneous') selected="selected" @endif>Simultaneous</option>
                                                                    <option value="sequence" @if($ringGroup->ring_group_strategy == 'sequence') selected="selected" @endif>Sequence</option>
                                                                    <option value="random" @if($ringGroup->ring_group_strategy == 'random') selected="selected" @endif>Random</option>
                                                                    <option value="enterprise" @if($ringGroup->ring_group_strategy == 'enterprise') selected="selected" @endif>Enterprise</option>
                                                                    <option value="rollover" @if($ringGroup->ring_group_strategy == 'rollover') selected="selected" @endif>Rollover</option>
                                                                </select>
                                                                <div id="ring_group_strategy_err" class="text-danger text-error error_message"></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_ringback" class="form-label">Ring Back</label>
                                                                <select class="select2 form-control" data-toggle="select2" data-placeholder="Choose ..."
                                                                        id="ring_group_ringback" name="ring_group_ringback">
                                                                    <option value=""></option>
                                                                </select>
                                                                <div id="ring_group_ringback_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                            </div>

                                        </div> <!-- end row-->

                                    </div>
                                    <div class="tab-pane fade" id="v-pills-callforward" role="tabpanel" aria-labelledby="v-pills-callforward-tab">
                                        <!-- Settings Content-->
                                        <div class="tab-pane show active">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h4 class="mb-2 mt-0">Forward all calls</h4>
                                                    <p class="text-muted mb-2">Ensure customers and colleagues can reach you, regardless of your physical location. Automatically redirect all incoming calls to another phone number of your choice.</p>
                                                    <div class="row">
                                                        <div class="mb-2">
                                                            <input type="hidden" name="forward_all_enabled" value="false">
                                                            <input type="checkbox" id="forward_all_enabled" value="true" name="forward_all_enabled" data-option="forward_all" class="forward_checkbox"
                                                                   @if ($ringGroup->forward_all_enabled == "true") checked @endif
                                                                   data-switch="primary"/>
                                                            <label for="forward_all_enabled" data-on-label="On" data-off-label="Off"></label>
                                                            <div class="text-danger forward_all_enabled_err error_message"></div>
                                                        </div>
                                                    </div>
                                                    <div id="forward_all_phone_number" class="row @if($ringGroup->forward_all_enabled == "false") d-none @endif">
                                                        <div class="col-md-12">
                                                            <p>
                                                            @include('layouts.partials.destinationSelector', [
                                                                                'type' => 'forward',
                                                                                'id' => 'all',
                                                                                'value' => $ringGroup->forward_all_destination,
                                                                                'extensions' => $extensions
                                                            ])
                                                            <div class="text-danger forward_all_destination_err error_message"></div>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <hr />
                                            {{--
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h4 class="mb-2 mt-0">When user is busy</h4>
                                                    <p class="text-muted mb-2">Automatically redirect incoming calls to a different phone number if the phone is busy or Do Not Disturb is enabled.</p>
                                                    <div class="row">
                                                        <div class="mb-2">
                                                            <input type="hidden" name="forward_busy_enabled" value="false">
                                                            <input type="checkbox" id="forward_busy_enabled" value="true" name="forward_busy_enabled" data-option="forward_busy" class="forward_checkbox"
                                                                   @if ($ringGroup->forward_busy_enabled == "true") checked @endif
                                                                   data-switch="primary"/>
                                                            <label for="forward_busy_enabled" data-on-label="On" data-off-label="Off"></label>
                                                            <div class="text-danger forward_busy_enabled_err error_message"></div>
                                                        </div>
                                                    </div>
                                                    <div id="forward_busy_phone_number" class="row @if($ringGroup->forward_busy_enabled == "false") d-none @endif">
                                                        <div class="col-md-12">
                                                            <p>
                                                            @include('layouts.partials.destinationSelector', [
                                                                                'type' => 'forward',
                                                                                'id' => 'busy',
                                                                                'value' => $ringGroup->forward_busy_destination,
                                                                                'extensions' => $extensions
                                                            ])
                                                            <div class="text-danger forward_busy_destination_err error_message"></div>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <hr />
                                            --}}
                                            {{--
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h4 class="mb-2 mt-0">When user does not answer the call</h4>
                                                    <p class="text-muted mb-2">Automatically redirect incoming calls to a different phone number if no answer.</p>
                                                    <div class="row">
                                                        <div class="mb-2">
                                                            <input type="hidden" name="forward_no_answer_enabled" value="false">
                                                            <input type="checkbox" id="forward_no_answer_enabled" value="true" name="forward_no_answer_enabled" data-option="forward_no_answer" class="forward_checkbox"
                                                                   @if ($ringGroup->forward_no_answer_enabled == "true") checked @endif
                                                                   data-switch="primary"/>
                                                            <label for="forward_no_answer_enabled" data-on-label="On" data-off-label="Off"></label>
                                                            <div class="text-danger forward_no_answer_enabled_err error_message"></div>
                                                        </div>
                                                    </div>
                                                    <div id="forward_no_answer_phone_number" class="row @if($ringGroup->forward_no_answer_enabled == "false") d-none @endif">
                                                        <div class="col-md-12">
                                                            <p>
                                                            @include('layouts.partials.destinationSelector', [
                                                                                'type' => 'forward',
                                                                                'id' => 'no_answer',
                                                                                'value' => $ringGroup->forward_no_answer_destination,
                                                                                'extensions' => $extensions
                                                            ])
                                                            <div class="text-danger forward_no_answer_destination_err error_message"></div>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <hr />
                                            --}}
                                            {{--
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h4 class="mb-2 mt-0">When internet connection is down</h4>
                                                    <p class="text-muted mb-2">Automatically redirect incoming calls to a different phone number if no user registered.</p>
                                                    <div class="row">
                                                        <div class="mb-2">
                                                            <input type="hidden" name="forward_user_not_registered_enabled" value="false">
                                                            <input type="checkbox" id="forward_user_not_registered_enabled" value="true" name="forward_user_not_registered_enabled" data-option="forward_user_not_registered" class="forward_checkbox"
                                                                   @if ($ringGroup->forward_user_not_registered_enabled == "true") checked @endif
                                                                   data-switch="primary"/>
                                                            <label for="forward_user_not_registered_enabled" data-on-label="On" data-off-label="Off"></label>
                                                            <div class="text-danger forward_user_not_registered_enabled_err error_message"></div>
                                                        </div>
                                                    </div>
                                                    <div id="forward_user_not_registered_phone_number" class="row @if($ringGroup->forward_user_not_registered_enabled == "false") d-none @endif">
                                                        <div class="col-md-12">
                                                            <p>
                                                            @include('layouts.partials.destinationSelector', [
                                                                                'type' => 'forward',
                                                                                'id' => 'user_not_registered',
                                                                                'value' => $ringGroup->forward_user_not_registered_destination,
                                                                                'extensions' => $extensions
                                                            ])
                                                            <div class="text-danger forward_not_registered_destination_err error_message"></div>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <hr />
                                            --}}
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h4 class="mb-2 mt-0">Sequential ring</h4>
                                                    <p class="text-muted mb-2">List and determine the order of up to 10 phone numbers or SIP URI addresses you would like to ring after your primary phone when you receive a call.</p>
                                                    <div class="row">
                                                        <div class="mb-2">
                                                            <input type="hidden" name="ring_group_follow_me_enabled" value="false">
                                                            <input type="checkbox" id="ring_group_follow_me_enabled" value="true" name="ring_group_follow_me_enabled" data-option="ring_group_follow_me" class="forward_checkbox"
                                                                   @if ($ringGroup->ring_group_follow_me_enabled == "true") checked @endif
                                                                   data-switch="primary"/>
                                                            <label for="ring_group_follow_me_enabled" data-on-label="On" data-off-label="Off"></label>
                                                            <div class="text-danger ring_group_follow_me_enabled_err error_message"></div>
                                                        </div>
                                                    </div>
                                                    <div id="ring_group_follow_me_phone_number" class="row @if($ringGroup->ring_group_follow_me_enabled == "false") d-none @endif">
                                                        <div class="col-md-12">
                                                            <div class="row mb-3">
                                                                <div class="col-5">
                                                                    <label class="form-label" style="padding-top: 10px;">Ring my main phone first for </label>
                                                                </div>
                                                                <div class="col-2">
                                                                    <select data-toggle="select2" title="Ring my main phone first" name="follow_me_ring_my_phone_timeout">
                                                                        <option value="">Disabled</option>
                                                                        @for ($i = 1; $i < 20; $i++)
                                                                            <option value="{{ $i * 5 }}" @if ($follow_me_ring_my_phone_timeout == $i*5) selected @endif>
                                                                                {{ $i }} @if ($i >1 ) Rings @else Ring @endif - {{ $i * 5 }} Sec
                                                                            </option>
                                                                        @endfor
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                                <h4 class="mt-2">Sequential order</h4>
                                                                <p class="text-muted mb-2">You can drag-n-drop lines to adjust current sequential.</p>
                                                                <table class="table table-centered table-responsive table-sm mb-0 sequential-table">
                                                                    <thead>
                                                                    <tr>
                                                                        <th style="width: 20px;">Order</th>
                                                                        <th>Destination</th>
                                                                        <th style="width: 150px">Delay</th>
                                                                        <th style="width: 150px">Number of rings</th>
                                                                        <th style="width: 130px;">Answer confirmation required</th>
                                                                        <th>Action</th>
                                                                    </tr>
                                                                    </thead>
                                                                    @php $b = 0 @endphp
                                                                    <tbody id="destination_sortable">
                                                                    @foreach($follow_me_destinations as $destination)
                                                                        <tr id="row{{$destination->follow_me_destination_uuid}}">
                                                                            @php $b++ @endphp
                                                                            <td class="drag-handler"><i class="mdi mdi-drag"></i> <span>{{ $b }}</span></td>
                                                                            <td>
                                                                                @include('layouts.partials.destinationSelector', [
                                                                                    'type' => 'follow_me_destinations',
                                                                                    'id' => $destination->follow_me_destination_uuid,
                                                                                    'value' => $destination->follow_me_destination,
                                                                                    'extensions' => $extensions
                                                                                ])
                                                                            </td>
                                                                            <td>
                                                                                <select id="destination_delay_{{$destination->follow_me_destination_uuid}}" name="follow_me_destinations[{{$destination->follow_me_destination_uuid}}][delay]">
                                                                                    @for ($i = 0; $i < 20; $i++)
                                                                                        <option value="{{ $i * 5 }}" @if ($destination->follow_me_delay == $i*5) selected @endif>
                                                                                            {{ $i }} @if ($i >1 ) Rings @else Ring @endif - {{ $i * 5 }} Sec
                                                                                        </option>
                                                                                    @endfor
                                                                                </select>
                                                                            </td>
                                                                            <td>
                                                                                <select id="destination_timeout_{{$destination->follow_me_destination_uuid}}" name="follow_me_destinations[{{$destination->follow_me_destination_uuid}}][timeout]">
                                                                                    @for ($i = 1; $i < 21; $i++)
                                                                                        <option value="{{ $i * 5 }}" @if ($destination->follow_me_timeout == $i*5) selected @endif>
                                                                                            {{ $i }} @if ($i >1 ) Rings @else Ring @endif - {{ $i * 5 }} Sec
                                                                                        </option>
                                                                                    @endfor
                                                                                </select>
                                                                            </td>
                                                                            <td>
                                                                                <input type="hidden" name="follow_me_destinations[{{$destination->follow_me_destination_uuid}}][prompt]" value="false">
                                                                                <input type="checkbox" id="destination_prompt_{{$destination->follow_me_destination_uuid}}" value="true" name="follow_me_destinations[{{$destination->follow_me_destination_uuid}}][prompt]"
                                                                                       @if ($destination->follow_me_prompt == "1") checked @endif
                                                                                       data-switch="primary"/>
                                                                                <label for="destination_prompt_{{$destination->follow_me_destination_uuid}}" data-on-label="On" data-off-label="Off"></label>
                                                                            </td>
                                                                            <td>
                                                                                <div id="tooltip-container-actions">
                                                                                    <a href="javascript:confirmDeleteDestinationAction('row{{$destination->follow_me_destination_uuid}}');" class="action-icon">
                                                                                        <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i>
                                                                                    </a>
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                    </tbody>
                                                                </table>
                                                                <div id="addDestinationBar" class="my-1" @if($ringGroup->getGroupDestinations()->count() >= 10) style="display: none;" @endif>
                                                                    <a href="javascript:addDestinationAction(this);" class="btn btn-success">
                                                                        <i class="mdi mdi-plus" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Add destination"></i>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- End Settings Content-->
                                    </div>
                                </div> <!-- end tab-content-->
                            </div> <!-- end col-->
                    </div>
                    <!-- end row-->
                    </form>

                </div> <!-- end card-body-->
            </div> <!-- end card-->
        </div> <!-- end col -->
    </div>
    <!-- end row-->

</div> <!-- container -->

@endsection

@push('scripts')

<script>
    $(document).ready(function() {
        const form = $('#ringGroupForm');
        $('#submitFormButton').on('click', function(e) {
            e.preventDefault();
            $('.loading').show();

            //Reset error messages
            $('.error_message').text("");

            var url = form.attr('action');

            $.ajax({
                type : "POST",
                url : url,
                cache: false,
                data : form.serialize(),
                beforeSend: function() {
                    //Reset error messages
                    form.find('.error').text('');
                    $('.error_message').text("");
                    $('.btn').attr('disabled', true);
                    $('.loading').show();
                },
                complete: function (xhr,status) {
                    $('.btn').attr('disabled', false);
                    $('.loading').hide();
                },
                success: function(result) {
                    $('.loading').hide();
                    $.NotificationApp.send("Success",result.message,"top-right","#10c469","success");
                    //window.location.href = "{{ route('ring-groups.index')}}";
                },
                error: function(error) {
                    $('.loading').hide();
                    $('.btn').attr('disabled', false);
                    if(error.status == 422){
                        if(error.responseJSON.errors) {
                            $.each( error.responseJSON.errors, function( key, value ) {
                                if (value != '') {
                                    form.find('#'+key+'_err').text(value);
                                    printErrorMsg(value);
                                }
                            });
                        } else {
                            printErrorMsg(error.responseJSON.message);
                        }
                    } else {
                        printErrorMsg(error.responseJSON.message);
                    }
                }
            })
        });
    });
</script>
@endpush
