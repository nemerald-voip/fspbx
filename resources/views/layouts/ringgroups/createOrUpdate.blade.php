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
                                    <div class="nav flex-column nav-pills" id="extensionNavPills" role="tablist"
                                         aria-orientation="vertical">
                                        <a class="nav-link active show" id="v-pills-home-tab" data-bs-toggle="pill"
                                           href="#v-pills-home" role="tab" aria-controls="v-pills-home"
                                           aria-selected="true">
                                            <i class="mdi mdi-home-variant d-md-none d-block"></i>
                                            <span class="d-none d-md-block">Basic Information
                                        <span class="float-end text-end
                                            ring_group_extension_badge
                                            " hidden><span class="badge badge-danger-lighten">error</span></span>
                                    </span>
                                        </a>
                                        <a class="nav-link" id="v-pills-callforward-tab" data-bs-toggle="pill"
                                           href="#v-pills-callforward" role="tab" aria-controls="v-pills-callforward"
                                           aria-selected="false">
                                            <i class="mdi mdi-settings-outline d-md-none d-block"></i>
                                            <span class="d-none d-md-block">Call Forward
                                        <span class="float-end text-end
                                            ring_group_forward_enabled_err_badge
                                            ring_group_forward_destination_err_badge
                                            " hidden><span class="badge badge-danger-lighten">error</span></span>
                                    </span>
                                        </a>
                                        <a class="nav-link" id="v-pills-advanced-tab" data-bs-toggle="pill"
                                           href="#v-pills-advanced" role="tab" aria-controls="v-pills-advanced"
                                           aria-selected="false">
                                            <i class="mdi mdi-settings-outline d-md-none d-block"></i>
                                            <span class="d-none d-md-block">Advanced
                                        <span class="float-end text-end
                                            ring_group_forward_enabled_err_badge
                                            ring_group_forward_destination_err_badge
                                            " hidden><span class="badge badge-danger-lighten">error</span></span>
                                    </span>
                                        </a>
                                    </div>
                                </div> <!-- end col-->

                                <div class="col-sm-10">

                                    <div class="tab-content">
                                        <div class="text-sm-end" id="action-buttons">
                                            <a href="{{ route('ring-groups.index') }}"
                                               class="btn btn-light me-2">Cancel</a>
                                            <button class="btn btn-success" type="submit" id="submitFormButton"><i
                                                        class="uil uil-down-arrow me-2"></i> Save
                                            </button>
                                            {{-- <button class="btn btn-success" type="submit">Save</button> --}}
                                        </div>
                                        <div class="tab-pane fade active show" id="v-pills-home" role="tabpanel"
                                             aria-labelledby="v-pills-home-tab">
                                            <!-- Basic Info Content-->
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h4 class="mt-2">Basic information</h4>
                                                    <p class="text-muted mb-4">Provide basic information about the ring group</p>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_extension" class="form-label">Ring
                                                                    Group name <span
                                                                            class="text-danger">*</span></label>
                                                                <input class="form-control" type="text"
                                                                       placeholder="xxx" id="ring_group_name"
                                                                       name="ring_group_extension"
                                                                       value="{{ $ringGroup->ring_group_name }}"
                                                                />
                                                                <div id="ring_group_name_err"
                                                                     class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_extension" class="form-label">Ring
                                                                    Group number <span
                                                                            class="text-danger">*</span></label>
                                                                <input class="form-control" type="text"
                                                                       placeholder="xxx" id="ring_group_extension"
                                                                       name="ring_group_extension"
                                                                       value="{{ $ringGroup->ring_group_extension }}"
                                                                       @if ($ringGroup->exists) readonly @endif />
                                                                <div id="ring_group_extension_err"
                                                                     class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_greeting" class="form-label">Greeting</label>
                                                                <select class="select2 form-control"
                                                                        data-toggle="select2"
                                                                        data-placeholder="Choose ..."
                                                                        id="ring_group_greeting"
                                                                        name="ring_group_greeting">
                                                                    <option value=""></option>
                                                                </select>
                                                                <div id="ring_group_greeting_err"
                                                                     class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                                    <hr />
                                                    <div class="row">
                                                        <h4 class="mt-2">Destinations</h4>
                                                        <p class="text-muted mb-2">You can drag-n-drop lines to adjust current destinations order.</p>
                                                        <table class="table table-centered table-responsive table-sm mb-0 sequential-table">
                                                            <thead>
                                                            <tr>
                                                                <th style="width: 20px;">Order</th>
                                                                <th>Destination</th>
                                                                <th style="width: 150px">Delay</th>
                                                                <th style="width: 150px">Number of rings
                                                                </th>
                                                                <th style="width: 130px;">Answer
                                                                    confirmation required
                                                                </th>
                                                                <th>Action</th>
                                                            </tr>
                                                            </thead>
                                                            @php $b = 0 @endphp
                                                            <tbody id="destination_sortable">
                                                            @foreach($ringGroupDestinations as $destination)
                                                                <tr id="row{{$destination->ring_group_destination_uuid}}">
                                                                    @php $b++ @endphp
                                                                    <td class="drag-handler"><i
                                                                                class="mdi mdi-drag"></i>
                                                                        <span>{{ $b }}</span></td>
                                                                    <td>
                                                                        @include('layouts.partials.destinationSelector', [
                                                                            'type' => 'ring_group_destinations',
                                                                            'id' => $destination->ring_group_destination_uuid,
                                                                            'value' => $destination->destination_number,
                                                                            'extensions' => $extensions
                                                                        ])
                                                                    </td>
                                                                    <td>
                                                                        <select id="destination_delay_{{$destination->ring_group_destination_uuid}}"
                                                                                name="ring_group_destinations[{{$destination->ring_group_destination_uuid}}][delay]">
                                                                            @for ($i = 0; $i < 20; $i++)
                                                                                <option value="{{ $i * 5 }}"
                                                                                        @if ($destination->destination_delay == $i*5) selected @endif>
                                                                                    {{ $i }} @if ($i >1 )
                                                                                        Rings
                                                                                    @else
                                                                                        Ring
                                                                                    @endif - {{ $i * 5 }}Sec
                                                                                </option>
                                                                            @endfor
                                                                        </select>
                                                                    </td>
                                                                    <td>
                                                                        <select id="destination_timeout_{{$destination->ring_group_destination_uuid}}"
                                                                                name="ring_group_destinations[{{$destination->ring_group_destination_uuid}}][timeout]">
                                                                            @for ($i = 1; $i < 21; $i++)
                                                                                <option value="{{ $i * 5 }}"
                                                                                        @if ($destination->destination_timeout == $i*5) selected @endif>
                                                                                    {{ $i }} @if ($i >1 )
                                                                                        Rings
                                                                                    @else
                                                                                        Ring
                                                                                    @endif - {{ $i * 5 }}Sec
                                                                                </option>
                                                                            @endfor
                                                                        </select>
                                                                    </td>
                                                                    <td>
                                                                        <input type="hidden"
                                                                               name="ring_group_destinations[{{$destination->ring_group_destination_uuid}}][prompt]"
                                                                               value="false">
                                                                        <input type="checkbox"
                                                                               id="destination_prompt_{{$destination->ring_group_destination_uuid}}"
                                                                               value="true"
                                                                               name="ring_group_destinations[{{$destination->ring_group_destination_uuid}}][prompt]"
                                                                               @if ($destination->destination_prompt == "1") checked
                                                                               @endif
                                                                               data-switch="primary"/>
                                                                        <label for="destination_prompt_{{$destination->ring_group_destination_uuid}}"
                                                                               data-on-label="On"
                                                                               data-off-label="Off"></label>
                                                                    </td>
                                                                    <td>
                                                                        <div id="tooltip-container-actions">
                                                                            <a href="javascript:confirmDeleteDestinationAction('row{{$destination->ring_group_destination_uuid}}');"
                                                                               class="action-icon">
                                                                                <i class="mdi mdi-delete"
                                                                                   data-bs-container="#tooltip-container-actions"
                                                                                   data-bs-toggle="tooltip"
                                                                                   data-bs-placement="bottom"
                                                                                   title="Delete"></i>
                                                                            </a>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                            </tbody>
                                                        </table>
                                                        <div id="addDestinationBar" class="my-1"
                                                             @if($ringGroup->getGroupDestinations()->count() >= 30) style="display: none;" @endif>
                                                            <a href="javascript:addDestinationAction(this);"
                                                               class="btn btn-success">
                                                                <i class="mdi mdi-plus"
                                                                   data-bs-container="#tooltip-container-actions"
                                                                   data-bs-toggle="tooltip"
                                                                   data-bs-placement="bottom"
                                                                   title="Add destination"></i> Add one
                                                            </a>
                                                        </div>
                                                        <div id="addDestinationBarMultiple" class="my-1"
                                                             @if($ringGroup->getGroupDestinations()->count() >= 30) style="display: none;" @endif>
                                                            <a href="javascript:addDestinationModal(this);"
                                                               class="btn btn-success">
                                                                <i class="mdi mdi-plus"
                                                                   data-bs-container="#tooltip-container-actions"
                                                                   data-bs-toggle="tooltip"
                                                                   data-bs-placement="bottom"
                                                                   title="Add multiple destinations"></i> Add multiple
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <hr class="mb-4" />
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_call_timeout" class="form-label">Call timeout</label>
                                                                <input class="form-control" type="text"
                                                                       placeholder="" id="ring_group_call_timeout"
                                                                       name="ring_group_call_timeout"
                                                                       value="{{ $ringGroup->ring_group_call_timeout }}"
                                                                />
                                                                <div id="ring_group_call_timeout_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_timeout_data" class="form-label">If not answered</label>
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <select class="select2 form-control"
                                                                                data-toggle="select2"
                                                                                data-placeholder="Choose ..."
                                                                                id="ring_group_strategy"
                                                                                name="ring_group_strategy">
                                                                            <option value="" selected>

                                                                            </option>
                                                                            <option value="simultaneous">
                                                                                Ring Groups
                                                                            </option>
                                                                            <option value="dialplans">
                                                                                Dial Plans
                                                                            </option>
                                                                            <option value="extensions">
                                                                                Extensions
                                                                            </option>
                                                                            <option value="timeconditions">
                                                                                Time Conditions
                                                                            </option>
                                                                            <option value="voicemails">
                                                                                Voicemails
                                                                            </option>
                                                                            <option value="others">
                                                                                Others
                                                                            </option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <select class="select2 form-control"
                                                                                data-toggle="select2"
                                                                                data-placeholder="Choose ..."
                                                                                id="ring_group_timeout_data"
                                                                                name="ring_group_timeout_data">
                                                                            <option value="simultaneous">

                                                                            </option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div id="ring_group_timeout_data_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_cid_name_prefix" class="form-label">CID Name Prefix</label>
                                                                <input class="form-control" type="text"
                                                                       placeholder="" id="ring_group_cid_name_prefix"
                                                                       name="ring_group_cid_name_prefix"
                                                                       value="{{ $ringGroup->ring_group_cid_name_prefix }}"
                                                                />
                                                                <div id="ring_group_cid_name_prefix_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_cid_number_prefix" class="form-label">CID Number Prefix</label>
                                                                <input class="form-control" type="text"
                                                                       placeholder="" id="ring_group_cid_number_prefix"
                                                                       name="ring_group_cid_number_prefix"
                                                                       value="{{ $ringGroup->ring_group_cid_number_prefix }}"
                                                                />
                                                                <div id="ring_group_cid_number_prefix_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_description" class="form-label">Description</label>
                                                                <input class="form-control" type="text"
                                                                       placeholder="" id="ring_group_description"
                                                                       name="ring_group_extension"
                                                                       value="{{ $ringGroup->ring_group_description }}" />
                                                                <div id="ring_group_description_err"
                                                                     class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-4">
                                                            <div class="mb-3">
                                                                <label  class="form-label">Enabled</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-2">
                                                            <div class="mb-3 text-sm-end">
                                                                <input type="hidden" name="ring_group_enabled" value="false">
                                                                <input type="checkbox" id="enabled-switch" name="enabled"
                                                                       @if ($ringGroup->ring_group_enabled == "true") checked @endif data-switch="primary"  />
                                                                <label for="enabled-switch" data-on-label="On" data-off-label="Off"></label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>

                                        </div>
                                        <div class="tab-pane fade" id="v-pills-callforward" role="tabpanel"
                                             aria-labelledby="v-pills-callforward-tab">

                                            <div class="tab-pane show active">
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <h4 class="mt-2">Forward calls</h4>
                                                        <p class="text-muted mb-2">Ensure customers and colleagues can
                                                            reach you, regardless of your physical location.
                                                            Automatically redirect all incoming calls to another phone
                                                            number of your choice.</p>
                                                        <div class="row">
                                                            <div class="mb-2">
                                                                <input type="hidden" name="ring_group_forward_enabled"
                                                                       value="false">
                                                                <input type="checkbox" id="ring_group_forward_enabled"
                                                                       value="true" name="ring_group_forward_enabled"
                                                                       data-option="ring_group_forward"
                                                                       class="forward_checkbox"
                                                                       @if ($ringGroup->ring_group_forward_enabled == "true") checked @endif
                                                                       data-switch="primary"/>
                                                                <label for="ring_group_forward_enabled"
                                                                       data-on-label="On" data-off-label="Off"></label>
                                                                <div class="text-danger ring_group_forward_enabled_err error_message"></div>
                                                            </div>
                                                        </div>
                                                        <div id="ring_group_forward_phone_number"
                                                             class="row @if($ringGroup->ring_group_forward_enabled !== "true") d-none @endif">
                                                            <div class="col-md-12">
                                                                <p>
                                                                @include('layouts.partials.destinationSelector', [
                                                                                    'type' => 'ring_group_forward',
                                                                                    'id' => 'all',
                                                                                    'value' => $ringGroup->ring_group_forward_destination,
                                                                                    'extensions' => $extensions
                                                                ])
                                                                <div class="text-danger ring_group_forward_destination_err error_message"></div>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                        <div class="tab-pane fade" id="v-pills-advanced" role="tabpanel"
                                             aria-labelledby="v-pills-home-tab">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h4 class="mt-2">Advanced</h4>
                                                    <p class="text-muted mb-4">Provide advanced information about the ring group</p>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_strategy" class="form-label">Strategy</label>
                                                                <select class="select2 form-control"
                                                                        data-toggle="select2"
                                                                        data-placeholder="Choose ..."
                                                                        id="ring_group_strategy"
                                                                        name="ring_group_strategy">
                                                                    <option value="simultaneous"
                                                                            @if($ringGroup->ring_group_strategy == 'simultaneous') selected="selected" @endif>
                                                                        Simultaneous
                                                                    </option>
                                                                    <option value="sequence"
                                                                            @if($ringGroup->ring_group_strategy == 'sequence') selected="selected" @endif>
                                                                        Sequence
                                                                    </option>
                                                                    <option value="random"
                                                                            @if($ringGroup->ring_group_strategy == 'random') selected="selected" @endif>
                                                                        Random
                                                                    </option>
                                                                    <option value="enterprise"
                                                                            @if($ringGroup->ring_group_strategy == 'enterprise') selected="selected" @endif>
                                                                        Enterprise
                                                                    </option>
                                                                    <option value="rollover"
                                                                            @if($ringGroup->ring_group_strategy == 'rollover') selected="selected" @endif>
                                                                        Rollover
                                                                    </option>
                                                                </select>
                                                                <div id="ring_group_strategy_err" class="text-danger text-error error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_greeting" class="form-label">Greeting</label>
                                                                <select class="select2 form-control"
                                                                        data-toggle="select2"
                                                                        data-placeholder="Choose ..."
                                                                        id="ring_group_greeting"
                                                                        name="ring_group_greeting">
                                                                    <option value=""></option>
                                                                </select>
                                                                <div id="ring_group_greeting_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_caller_id_name" class="form-label">Caller ID Name</label>
                                                                <input class="form-control" type="text"
                                                                       placeholder="" id="ring_group_caller_id_name"
                                                                       name="ring_group_caller_id_name"
                                                                       value="{{ $ringGroup->ring_group_caller_id_name }}"
                                                                />
                                                                <div id="ring_group_caller_id_name_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_caller_id_number" class="form-label">Caller ID Number</label>
                                                                <input class="form-control" type="text"
                                                                       placeholder="" id="ring_group_caller_id_number"
                                                                       name="ring_group_caller_id_number"
                                                                       value="{{ $ringGroup->ring_group_caller_id_number }}"
                                                                />
                                                                <div id="ring_group_caller_id_number_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_distinctive_ring" class="form-label">Distinctive Ring</label>
                                                                <input class="form-control" type="text"
                                                                       placeholder="" id="ring_group_distinctive_ring"
                                                                       name="ring_group_distinctive_ring"
                                                                       value="{{ $ringGroup->ring_group_distinctive_ring }}" />
                                                                <div id="ring_group_distinctive_ring_err"
                                                                     class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_ringback" class="form-label">Ring Back</label>
                                                                <select class="select2 form-control"
                                                                        data-toggle="select2"
                                                                        data-placeholder="Choose ..."
                                                                        id="ring_group_ringback"
                                                                        name="ring_group_ringback">
                                                                    @if (!$moh->isEmpty())
                                                                    <optgroup label="Music on Hold">
                                                                        @foreach ($moh as $music)
                                                                            <option value="local_stream://{{ $music->music_on_hold_name }}"
                                                                                    @if("local_stream://" . $music->music_on_hold_name == $ringGroup->ring_group_ringback)
                                                                                        selected
                                                                                    @endif>
                                                                                {{ $music->music_on_hold_name }}
                                                                            </option>
                                                                        @endforeach
                                                                    </optgroup>
                                                                    @endif
                                                                    <optgroup label="Ringtones">
                                                                        <option value="${us-ring}" @if($ringGroup->ring_group_ringback == '${us-ring}') selected="selected" @endif>${us-ring}</option>
                                                                    </optgroup>
                                                                </select>
                                                                <div id="ring_group_ringback_err"
                                                                     class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-4">
                                                            <div class="mb-3">
                                                                <label  class="form-label">Call Forward</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-2">
                                                            <div class="mb-3 text-sm-end">
                                                                <input type="hidden" name="ring_group_call_forward_enabled" value="false">
                                                                <input type="checkbox" id="enabled-switch" name="ring_group_call_forward_enabled"
                                                                       @if ($ringGroup->ring_group_call_forward_enabled == "true") checked @endif
                                                                       data-switch="primary"/>
                                                                <label for="enabled-switch" data-on-label="On" data-off-label="Off"></label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-4">
                                                            <div class="mb-3">
                                                                <label  class="form-label">Follow Me</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-2">
                                                            <div class="mb-3 text-sm-end">
                                                                <input type="hidden" name="ring_group_follow_me_enabled" value="false">
                                                                <input type="checkbox" id="enabled-switch" name="ring_group_follow_me_enabled"
                                                                       @if ($ringGroup->ring_group_follow_me_enabled == "true") checked @endif
                                                                       data-switch="primary"/>
                                                                <label for="enabled-switch" data-on-label="On" data-off-label="Off"></label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_missed_call_data" class="form-label">Missed Call</label>
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <select class="select2 form-control"
                                                                                data-toggle="select2"
                                                                                data-placeholder="Choose ..."
                                                                                id="ring_group_strategy"
                                                                                name="ring_group_strategy">
                                                                            <option value="" selected>

                                                                            </option>
                                                                            <option value="email">
                                                                                Email
                                                                            </option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <select class="select2 form-control"
                                                                                data-toggle="select2"
                                                                                data-placeholder="Choose ..."
                                                                                id="ring_group_missed_call_data"
                                                                                name="ring_group_missed_call_data">
                                                                            <option value="simultaneous">

                                                                            </option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div id="ring_group_missed_call_data_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_forward_toll_allow" class="form-label">Forward Toll Allow</label>
                                                                <input class="form-control" type="text"
                                                                       placeholder="" id="ring_group_forward_toll_allow"
                                                                       name="ring_group_forward_toll_allow"
                                                                       value="{{ $ringGroup->ring_group_forward_toll_allow }}" />
                                                                <div id="ring_group_forward_toll_allow_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_context" class="form-label">Context</label>
                                                                <input class="form-control" type="text"
                                                                       placeholder="" id="ring_group_context"
                                                                       name="ring_group_forward_context"
                                                                       value="{{ $ringGroup->ring_group_context }}" />
                                                                <div id="ring_group_context_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div> <!-- end tab-content-->
                                </div> <!-- end col-->
                            </div>
                        </form>
                    </div> <!-- end card-body-->
                </div> <!-- end card-->
            </div> <!-- end col -->
        </div>
    </div> <!-- container -->

    <div class="modal fade" id="addDestinationMultipleModal" role="dialog" aria-labelledby="addDestinationMultipleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDestinationMultipleModalLabel">Add Multiple Destinations</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Type up to 15 destinations in the inputs below and hit "Add". We will recognize either the destination is external or internal and fill up the form.</p>
                    <form method="POST" id="addDestinationMultipleForm" action="#" class="form">
                        @php
                            for($i = 0; $i < 15; $i++) {
                                print '<div class="row"><div class="col-md-12 mb-1">
                                       <input class="form-control" type="text"
                                       placeholder="Extension, voicemail, phone, etc..."
                                       name="destination_multiple[]"
                                       value="'.rand(300, 900).'" /></div></div>';
                            }
                        @endphp
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="fillDestinationForm($('#addDestinationMultipleForm'));" class="btn btn-success">Add</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <style>
        .input-group > .select2-container {
            width: auto !important;
            flex: 1 1 auto;
        }

        .select2-container--open {
            z-index: 10000;
        }

        /*
        @media (min-width: 576px) {
            #ForwardDestinationModal > .modal-dialog {
                max-width: 800px;
            }
        }*/
        .drag-handler {
            cursor: all-scroll;
        }

        #addDestinationBar {
            width: auto;
            text-align: center;
        }

        #addDestinationBarMultiple {
            width: auto;
            text-align: center;
        }

        .destination_wrapper {
            width: 415px;
        }

        @media (max-width: 1724px) {
            .sequential-table {
                width: 100%;
            }

            .sequential-table td .destination_wrapper {
                width: auto !important;
            }
        }
    </style>
    <script>
        $(document).ready(function () {
            const form = $('#ringGroupForm');

            applyDestinationSelect2()

            $('#submitFormButton').on('click', function (e) {
                e.preventDefault();
                $('.loading').show();

                //Reset error messages
                $('.error_message').text("");

                var url = form.attr('action');

                $.ajax({
                    type: "POST",
                    url: url,
                    cache: false,
                    data: form.serialize(),
                    beforeSend: function () {
                        //Reset error messages
                        form.find('.error').text('');
                        $('.error_message').text("");
                        $('.btn').attr('disabled', true);
                        $('.loading').show();
                    },
                    complete: function (xhr, status) {
                        $('.btn').attr('disabled', false);
                        $('.loading').hide();
                    },
                    success: function (result) {
                        $('.loading').hide();
                        $.NotificationApp.send("Success", result.message, "top-right", "#10c469", "success");
                        //window.location.href = "{{ route('ring-groups.index')}}";
                    },
                    error: function (error) {
                        $('.loading').hide();
                        $('.btn').attr('disabled', false);
                        if (error.status == 422) {
                            if (error.responseJSON.errors) {
                                $.each(error.responseJSON.errors, function (key, value) {
                                    if (value != '') {
                                        form.find('#' + key + '_err').text(value);
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

            $(document).on('click', '.forward_checkbox', function (e) {
                var checkbox = $(this);
                var cname = checkbox.data('option');
                console.log(cname)
                if (checkbox.is(':checked')) {
                    $('#' + cname + '_phone_number').removeClass('d-none');
                } else {
                    $('#' + cname + '_phone_number').addClass('d-none');
                    $('#' + cname + '_phone_number').find('.mx-1').find('select').val('internal');
                    $('#' + cname + '_phone_number').find('.mx-1').find('select').trigger('change');
                }
            });

            let sortable = new Sortable(document.getElementById('destination_sortable'), {
                delay: 0, // time in milliseconds to define when the sorting should start
                delayOnTouchOnly: false, // only delay if user is using touch
                touchStartThreshold: 0, // px, how many pixels the point should move before cancelling a delayed drag event
                disabled: false, // Disables the sortable if set to true.
                store: null,  // @see Store
                animation: 150,  // ms, animation speed moving items when sorting, `0` — without animation
                easing: "cubic-bezier(1, 0, 0, 1)", // Easing for animation. Defaults to null. See https://easings.net/ for examples.
                handle: ".drag-handler",  // Drag handle selector within list items
                filter: ".ignore-elements",  // Selectors that do not lead to dragging (String or Function)
                preventOnFilter: true, // Call `event.preventDefault()` when triggered `filter`

                ghostClass: "sortable-ghost",  // Class name for the drop placeholder
                chosenClass: "sortable-chosen",  // Class name for the chosen item
                dragClass: "sortable-drag",  // Class name for the dragging item

                swapThreshold: 1, // Threshold of the swap zone
                invertSwap: false, // Will always use inverted swap zone if set to true
                invertedSwapThreshold: 1, // Threshold of the inverted swap zone (will be set to swapThreshold value by default)
                direction: 'vertical', // Direction of Sortable (will be detected automatically if not given)

                forceFallback: false,  // ignore the HTML5 DnD behaviour and force the fallback to kick in

                fallbackClass: "sortable-fallback",  // Class name for the cloned DOM Element when using forceFallback
                fallbackOnBody: false,  // Appends the cloned DOM Element into the Document's Body
                fallbackTolerance: 0, // Specify in pixels how far the mouse should move before it's considered as a drag.

                dragoverBubble: false,
                removeCloneOnHide: true, // Remove the clone element when it is not showing, rather than just hiding it
                emptyInsertThreshold: 5, // px, distance mouse must be from empty sortable to insert drag element into it


                setData: function (/** DataTransfer */dataTransfer, /** HTMLElement*/dragEl) {
                    dataTransfer.setData('Text', dragEl.textContent); // `dataTransfer` object of HTML5 DragEvent
                },

                // Element is chosen
                onChoose: function (/**Event*/evt) {
                    evt.oldIndex;  // element index within parent
                },

                // Element is unchosen
                onUnchoose: function (/**Event*/evt) {
                    // same properties as onEnd
                },

                // Element dragging started
                onStart: function (/**Event*/evt) {
                    evt.oldIndex;  // element index within parent
                },

                // Element dragging ended
                onEnd: function (/**Event*/evt) {
                    var itemEl = evt.item;  // dragged HTMLElement
                    evt.to;    // target list
                    evt.from;  // previous list
                    evt.oldIndex;  // element's old index within old parent
                    evt.newIndex;  // element's new index within new parent
                    evt.oldDraggableIndex; // element's old index within old parent, only counting draggable elements
                    evt.newDraggableIndex; // element's new index within new parent, only counting draggable elements
                    evt.clone // the clone element
                    evt.pullMode;  // when item is in another sortable: `"clone"` if cloning, `true` if moving
                    updateDestinationOrder()
                },

                // Element is dropped into the list from another list
                onAdd: function (/**Event*/evt) {
                    // same properties as onEnd
                },

                // Changed sorting within list
                onUpdate: function (/**Event*/evt) {
                    // same properties as onEnd
                },

                // Called by any change to the list (add / update / remove)
                onSort: function (/**Event*/evt) {
                    // same properties as onEnd
                },

                // Element is removed from the list into another list
                onRemove: function (/**Event*/evt) {
                    // same properties as onEnd
                },

                // Attempt to drag a filtered element
                onFilter: function (/**Event*/evt) {
                    var itemEl = evt.item;  // HTMLElement receiving the `mousedown|tapstart` event.
                },

                // Event when you move an item in the list or between lists
                onMove: function (/**Event*/evt, /**Event*/originalEvent) {
                    // Example: https://jsbin.com/nawahef/edit?js,output
                    evt.dragged; // dragged HTMLElement
                    evt.draggedRect; // DOMRect {left, top, right, bottom}
                    evt.related; // HTMLElement on which have guided
                    evt.relatedRect; // DOMRect
                    evt.willInsertAfter; // Boolean that is true if Sortable will insert drag element after target by default
                    originalEvent.clientY; // mouse position
                    // return false; — for cancel
                    // return -1; — insert before target
                    // return 1; — insert after target
                    // return true; — keep default insertion point based on the direction
                    // return void; — keep default insertion point based on the direction
                },

                // Called when dragging element changes position
                onChange: function (/**Event*/evt) {
                    evt.newIndex // most likely why this event is used is to get the dragging element's current index
                    // same properties as onEnd
                }
            });

            /*$('#createDeviceModal').on('shown.bs.modal', function(e){
                if(typeof e.relatedTarget.dataset.href !== 'undefined') {
                    $('#createDeviceModalLabel').text('Edit Device')
                    // Edit device
                    $.ajax({
                        url: e.relatedTarget.dataset.href,
                        type: 'GET',
                        dataType: 'json',
                        beforeSend: function () {
                            $('.loading').show();
                        },
                        complete: function (xhr, status) {
                            $('.btn').attr('disabled', false);
                            $('.loading').hide();
                        },
                        success: function (result) {
                            $('#device_mac_address').attr('readonly', true).val(result.device_mac_address)
                            $('#template-select').val(result.device_template).trigger('change')
                            $('#profile-select').val(result.device_profile_uuid).trigger('change')
                            $('#device_uuid').val(result.device_uuid)
                        }
                    });
                } else {
                    $('#createDeviceModalLabel').text('Create New Device')
                    $('#device_mac_address').attr('readonly', false).val('')
                    $('#device_uuid').val('')
                    $('#template-select').val('').trigger('change')
                    $('#profile-select').val('').trigger('change')
                }
            });*/

            $(`#ring_group_forward_target_internal_all`).select2();
            $(`#ring_group_forward_type_all`).select2();
        });

        function showHideAddDestination() {
            if ($('#destination_sortable > tr').length > 29) {
                $('#addDestinationBar').hide();
            } else {
                $('#addDestinationBar').show();
            }
        }

        function applyDestinationSelect2() {
            $('#destination_sortable > tr').each(function (i, el) {
                $(el).find('select').each(function (i, el2) {
                    if ($(el2).data('select2')) {
                        $(el2).select2('destroy').hide()
                        $(el2).select2({
                            width: 'element'
                        }).show()
                    } else {
                        $(el2).select2({
                            width: 'element'
                        }).show()
                    }
                });
            })
        }

        function updateDestinationOrder() {
            $('#destination_sortable > tr').each(function (i, el) {
                $(el).find('.drag-handler').find('span').text(i + 1)
            })
        }

        function addDestinationModal(el) {
            $('#addDestinationMultipleModal').modal('show');
        }

        function addDestinationAction(el, type, value) {
            let wrapper = $(`#destination_sortable > tr`)
            let count = wrapper.length
            let newCount = (count + 1)
            type = type || 'internal'
            value = value || ''
            if (newCount > 30) {
                return false;
            }

            let newRow = `
        <tr id="row__NEWROWID__"><td class="drag-handler"><i class="mdi mdi-drag"></i> <span>__NEWROWID__</span></td>
        <td>
        @include('layouts.partials.destinationSelector', [
            'type' => 'ring_group_destinations',
            'id' => '__NEWROWID__',
            'value' => '',
            'extensions' => $extensions
        ])
            </td>
            <td><select id="destination_delay___NEWROWID__" name="ring_group_destinations[newrow__NEWROWID__][delay]">
@for ($i = 0; $i < 20; $i++) <option value="{{ $i * 5 }}" @if ($i == 0) selected @endif>
        {{ $i }} @if ($i >1 ) Rings @else Ring @endif - {{ $i * 5 }} Sec</option> @endfor </select></td>
        <td><select id="destination_timeout___NEWROWID__" name="ring_group_destinations[newrow__NEWROWID__][timeout]">
        @for ($i = 1; $i < 21; $i++) <option value="{{ $i * 5 }}" @if ($i == 5) selected @endif>
        {{ $i }} @if ($i >1 ) Rings @else Ring @endif - {{ $i * 5 }} Sec</option> @endfor </select></td><td>
        <input type="hidden" name="ring_group_destinations[newrow__NEWROWID__][prompt]" value="false">
        <input type="checkbox" id="destination_prompt___NEWROWID__" value="true" name="ring_group_destinations[newrow__NEWROWID__][prompt]" data-option="ring_group_follow_me_enabled" class="forward_checkbox" data-switch="primary"/>
        <label for="destination_prompt___NEWROWID__" data-on-label="On" data-off-label="Off"></label>
        </td><td><div id="tooltip-container-actions"><a href="javascript:confirmDeleteDestinationAction('row__NEWROWID__');" class="action-icon">
        <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i>
        </a></div></td></tr>`;
            newRow = newRow.replaceAll('__NEWROWID__', Math.random().toString(16).slice(2))

            $('#destination_sortable').append(newRow)

            showHideAddDestination()
            updateDestinationOrder()
            applyDestinationSelect2()
        }

        function confirmDeleteDestinationAction(el) {
            if ($(`#${el}`).data('select2')) {
                $(`#${el}`).select2('destroy').hide()
            }
            $(`#${el}`).remove();
            updateDestinationOrder()
            showHideAddDestination()
        }

        function fillDestinationForm(form) {
            const values = form.serializeArray()
            for(let i = 0; i < values.length; i++) {
                addDestinationAction(null, 'external', values[i].value)
                //console.log(values[i])
                values[i].value = '';
            }
            $('#addDestinationMultipleModal').modal('hide');
            //console.log(form.serializeArray())
        }
    </script>
@endpush
