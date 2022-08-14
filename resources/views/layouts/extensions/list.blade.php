@extends('layouts.horizontal', ["page_title"=> "Extensions"])

@section('content')
<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Extensions</h4>
            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-xl-4">
                            <label class="form-label">Showing {{ $extensions->count() ?? 0 }}  results for Extensions</label>
                        </div>
                        <div class="col-xl-8">
                            <div class="text-xl-end mt-xl-0 mt-2">
                                <a href="{{ route('extensions.create') }}" class="btn btn-success mb-2 me-2"><i class="mdi mdi-plus-circle me-2"></i>Add New</a>
                                <a href="javascript:confirmDeleteAction('{{ route('extensions.destroy', ':id') }}');" id="deleteMultipleActionButton" class="btn btn-danger mb-2 me-2 disabled">Delete Selected</a>
                                {{-- <button type="button" class="btn btn-light mb-2">Export</button> --}}
                            </div>
                        </div><!-- end col-->
                    </div>

                    <div class="table-responsive">
                        <table class="table table-centered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 20px;">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="selectallCheckbox">
                                            <label class="form-check-label" for="selectallCheckbox">&nbsp;</label>
                                        </div>
                                    </th>
                                    <th>Extension</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Outbound Caller ID</th>
                                    <th>Status</th>
                                    <th>Desctiption</th>
                                    <th style="width: 125px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $i = 1;
                                @endphp
                                @foreach ($extensions as $extension)
                                    <tr id="id{{ $extension->extension_uuid  }}">
                                        <td>
                                            <div class="form-check">
                                                <input type="checkbox" name="action_box[]" value="{{ $extension->extension_uuid }}" class="form-check-input action_checkbox">
                                                <label class="form-check-label" >&nbsp;</label>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route('extensions.edit',$extension) }}" class="text-body fw-bold">{{ $extension['extension'] }}</a> 
                                        </td>
                                        <td>
                                            <a href="{{ route('extensions.edit',$extension) }}" class="text-body fw-bold">{{ $extension['effective_caller_id_name'] }} </a>
                                        </td>
                                        <td>
                                            {{-- @if ($extension->voicemail->exists) --}}
                                                {{ $extension->voicemail->voicemail_mail_to ?? ""}} 
                                            {{-- @endif --}}
                                        </td>
                                        <td>
                                            {{ $extension['outbound_caller_id_number'] }} 
                                            {{-- @if ($extension['effective_caller_id_name']) 
                                                <h5><span class="badge bg-success"></i>Provisioned</span></h5>
                                            @else 
                                                <h5><span class="badge bg-warning">Inactive</span></h5>
                                            @endif --}}
                                        </td>
                                        <td>
                                            <small class="text-muted">Coming Soon...</small>
                                        </td>
                                        <td>
                                            {{ $extension['description'] }} 
                                        </td>
                                        <td>
                                             {{-- Action Buttons --}}
                                             <div id="tooltip-container-actions">

                                                <a href="{{ route('extensions.edit',$extension) }}" class="action-icon" title="Edit"> 
                                                    <i class="mdi mdi-lead-pencil" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Edit user"></i>
                                                </a>
                                                
                                                <a data-toggle="modal" data-target="#mobileAppModal"
                                                    data-attr="{{ route('mobileAppUserSettings',$extension) }}" class="action-icon mobileAppButton" title="Mobile App Settings"> 
                                                    <i class="mdi mdi-cellphone-cog" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Mobile App Settings"></i>
                                                </a>

                                                <a href="javascript:confirmDeleteAction('{{ route('extensions.destroy', ':id') }}','{{ $extension->extension_uuid }}');" class="action-icon"> 
                                                    <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i>
                                                </a>
                                            </div>
                                            {{-- End of action buttons --}}

                                        </td>
                                    </tr>
                                    @php 
                                        $i++;
                                    @endphp
                                @endforeach

                            </tbody>
                        </table>
                    </div>
                </div> <!-- end card-body-->
            </div> <!-- end card-->
        </div> <!-- end col -->
    </div>
    <!-- end row -->

</div> <!-- container -->


<!-- createMobileAppModal -->
<div id="createMobileAppModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="createMobileAppModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="createMobileAppModalLabel">Create mobile app user</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
            </div>
            <form class="ps-3 pe-3" action="" id="createUserForm">
                <div class="modal-body">

                    <div class="row mb-3">
                        <div class="col-8">
                            <div class="mb-1">
                                <label class="form-label">Activate and generate app credentials</label>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="mb-1 text-sm-end">
                                <input type="hidden" name="activate" value="false">
                                <input type="checkbox" id="activate" name="activate" checked
                                    data-switch="primary"/>
                                <label for="activate" data-on-label="On" data-off-label="Off"></label>
                                <div class="text-danger activate_err error_message"></div>
                            </div>
                        </div>
                        <span class="help-block"><small>Turn this setting off if you need to create contact only and don't need to generate user's app credentials at this time</small></span>
                    </div> <!-- end row -->

                    <div class="alert alert-danger" id="appUserError" style="display:none">
                        <ul></ul>
                    </div>

                    <input type="hidden" name="org_id" id="org_id" value="">
                    <input type="hidden" name="app_domain" id="app_domain" value="">
                    <input type="hidden" name="extension_uuid" id="extension_uuid" value="">

                    <div class="row mb-1">
                        <div class="col-12 text-center">
                            <a class="btn btn-link" data-bs-toggle="collapse"
                                href="#advancedOptions" aria-expanded="false"
                                aria-controls="advancedOptions">
                                Advanced
                                <i class="uil uil-angle-down"></i>
                            </a>
                        </div>
                    </div>
                    <div class="collapse" id="advancedOptions">
                        <div class="col-12">
                            <div class="mb-1">
                                <label class="form-label">Choose Connection</label>
                                <select id="connectionSelect2" data-toggle="select2" title="Connection" name="connection">
                                
                                </select>
                                <div class="text-danger connection_err error_message"></div>
                            </div>
                        </div>
                        <span class="help-block"><small>In most cases the default setting will work. Consult with your administrator if you need to change it</small></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button id="appUserCreateSubmitButton" type="submit" class="btn btn-primary">Create user</button>
                </div>
            </form>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->


<!-- createMobileAppSuccessModal -->
<div id="createMobileAppSuccessModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="createMobileAppSuccessModal" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="createMobileAppSuccessModal">Create mobile app user</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
            </div>
            <form class="ps-3 pe-3" action="" id="createUserForm">
                <div class="modal-body">


                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button id="appUserCreateSubmitButton" type="submit" class="btn btn-primary">Create user</button>
                </div>
            </form>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

@endsection


@push('scripts')
<script>
    $(document).ready(function() {

        $("#connectionSelect2").select2({
            dropdownParent: $("#createMobileAppModal")
        });

        localStorage.removeItem('activeTab');

        // Open Modal with new user settings
        $('.mobileAppButton').on('click', function(e) {
            e.preventDefault();
            let href = $(this).attr('data-attr');
            $('.loading').show();

            $.ajax({
                type : "POST",
                url : href,
                cache: false,
            })
            .done(function(response) {
                // console.log(response);
                if (response.error){
                    $('.loading').hide();
                    printErrorMsg(response.error);
                } else {
                    $('#createMobileAppModal').modal("show");

                    response.connections.forEach(function(connection) {
                        var newOption = new Option(connection.name, connection.id, false, false);
                        $('#connectionSelect2').append(newOption).trigger('change');
                    });

                    $('#org_id').val(response.org_id);
                    $('#app_domain').val(response.app_domain);
                    $('#extension_uuid').val(response.extension_uuid);

                    $('.loading').hide();
 
                }
            })
            .fail(function (jqXHR, testStatus, error) {
                    //console.log(error);
                    printErrorMsg(error);
                    $('#loader').hide();

            });
        });



        // Submit form to create a new user
        $('#createUserForm').on('submit', function(e) {
            e.preventDefault();
            //Change button to spinner
            $("#appUserCreateSubmitButton").html('');
            $("#appUserCreateSubmitButton").append('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading...');
            $("#appUserCreateSubmitButton").prop( "disabled", true );

            //Hide error message
            $("#appUserError").find("ul").html('');
            $("#appUserError").css('display','none');

            var url = '{{ route("appsCreateUser") }}';

            $.ajax({
                type : "POST",
                url : url,
                data: $(this).serialize(),
            })
            .done(function(response) {
                // remove the spinner and change button to default
                $("#appUserCreateSubmitButton").html('');
                $("#appUserCreateSubmitButton").append('Create User');
                $("#appUserCreateSubmitButton").prop( "disabled", false );

                if (response.error){
                    $("#appUserError").find("ul").html('');
                    $("#appUserError").css('display','block');
                    $("#appUserError").find("ul").append('<li>'+response.message+'</li>');
                    
                 } else if (response.success){
                    $('#createMobileAppModal').modal("hide");
                    console.log(response);

                }
            })
            .fail(function (response){
                //
            });
        });

    });
</script>
@endpush