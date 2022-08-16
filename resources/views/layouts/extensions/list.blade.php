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
                                    <th style="width: 20px;"></th>
                                    <th>Extension</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Outbound Caller ID</th>
                                    {{-- <th>Status</th> --}}
                                    <th>Desctiption</th>
                                    <th style="width: 140px;">Action</th>
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
                                            @if ($extension['registrations'])
                                                {{-- <h6><span class="badge bg-success rounded-pill dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">&nbsp;&nbsp;&nbsp;</span></h6> --}}
                                                <a class="badge bg-success rounded-pill dropdown-toggle text-success" href="#"  data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span></span></a>
                                                <div class="dropdown-menu p-3" >
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h5 class="card-title text-primary">Registered devices</h5>
                                                            @foreach ($extension['registrations'] as $registration)
                                                                <p class="card-text">
                                                                    <i class="uil uil-phone">&nbsp;{{ $registration['agent'] }}</i>
                                                                </p>
                                                            @endforeach
                                                        </div> <!-- end card-body-->
                                                    </div>
                                                    
                                                </div>
                                            @else 
                                                <h6><span class="badge bg-light rounded-pill">&nbsp;&nbsp;&nbsp;</span></h6>
                                            @endif
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
                                                
                                                <a href="#"
                                                    data-attr="{{ route('mobileAppUserSettings',$extension) }}" class="action-icon mobileAppButton" title="Mobile App Settings"> 
                                                    <i class="mdi mdi-cellphone-cog" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Mobile App Settings"></i>
                                                </a>

                                                <a href="javascript:confirmDeleteAction('{{ route('extensions.destroy', ':id') }}','{{ $extension->extension_uuid }}');" class="action-icon"> 
                                                    <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i>
                                                </a>

                                                @if (userCheckPermission('extension_advanced'))
                                                    {{-- <div class="dropdown"> --}}
                                                        <a class="dropdown-toggle arrow-none card-drop" href="#" id="dropdownAdvancedOptions" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                            <i class="mdi mdi-dots-vertical"></i>
                                                        </a>
                                                        
                                                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownAdvancedOptions">
                                                            <a href="#" data-attr="{{ route('extensions.sip.show',$extension) }}" class="dropdown-item sipCredentialsButton">SIP Credentials</a>
                                                            
                                                        </div>
                                                    {{-- </div> --}}
                                                    
                                                @endif
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
            <form class="ps-3 pe-3" action="" id="createUserSuccessForm">
                <div class="modal-body">


                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button id="" type="submit" class="btn btn-primary">Create user</button>
                </div>
            </form>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<!-- Sip Credentials modal -->
<div id="sipCredentialsModal"  class="modal fade" id="bs-example-modal-sm" tabindex="-1" role="dialog" aria-labelledby="sipCredentialsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="sipCredentialsModalLabel">User SIP Credentials</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="sip_username" class="form-label">Username</label>
                    <div class="input-group input-group-merge">
                        <input type="username" id="sip_username" name="sip_username" class="form-control" readonly="" placeholder="">
                        <div class="input-group-text" id="copyUsernameToClipboardButton">
                            <span class="dripicons-copy" data-bs-container="#copyUsernameToClipboardButton" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Copy to Clipboard"></span>
                        </div>
                    </div>
                </div>
                
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group input-group-merge">
                        <input type="password" id="sip_password" class="form-control" placeholder="" readonly="" name="sip_password">
                        <div class="input-group-text" data-password="false" id="showPasswordButton">
                            <span class="password-eye" data-bs-container="#showPasswordButton" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Show Password"></span>
                        </div>
                        <div class="input-group-text" id="copyPasswordToClipboardButton">
                            <span class="dripicons-copy" data-bs-container="#copyPasswordToClipboardButton" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Copy to Clipboard"></span>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="sip_domain" class="form-label">Domain</label>
                    <div class="input-group input-group-merge">
                        <input type="domain" id="sip_domain" name="sip_domain" class="form-control" readonly="" placeholder="">
                        <div class="input-group-text" id="copyDomainToClipboardButton">
                            <span class="dripicons-copy" data-bs-container="#copyDomainToClipboardButton" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Copy to Clipboard"></span>
                        </div>
                    </div>
                </div>
                  <p>*Do not share these credenatils with anyone</p>
            </div>
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


        // Open Modal to show SIP credentials
        $('.sipCredentialsButton').on('click', function(e) {
            e.preventDefault();
            let href = $(this).attr('data-attr');
            $('.loading').show();

            $.ajax({
                type : "GET",
                url : href,
                cache: false,
            })
            .done(function(response) {
                //console.log(response);
                if (response.error){
                    $('.loading').hide();
                    printErrorMsg(response.error);
                } else {
                    $('#sipCredentialsModal').modal("show");

                    $('#sip_username').val(response.username);
                    $('#sip_password').val(response.password);
                    $('#sip_domain').val(response.domain);

                    $('.loading').hide();
 
                }
            })
            .fail(function (jqXHR, testStatus, error) {
                    // console.log(error);
                    $('#loader').hide();
                    printErrorMsg(error);
                    

            });
        });

        // Copy to clipboard
        $('#copyUsernameToClipboardButton').on('click', function(e) {
            e.preventDefault();
            navigator.clipboard.writeText($('#sip_username').val()).then(
                function() {
                    /* clipboard successfully set */
                    $.NotificationApp.send("Success","The username was copied to your clipboard","top-right","#10c469","success");
                }, 
                function() {
                    /* clipboard write failed */
                    $.NotificationApp.send("Warning",'Opps! Your browser does not support the Clipboard API',"top-right","#ff5b5b","error");
            });
        });

        // Copy to clipboard
        $('#copyDomainToClipboardButton').on('click', function(e) {
            e.preventDefault();
            navigator.clipboard.writeText($('#sip_domain').val()).then(
                function() {
                    /* clipboard successfully set */
                    $.NotificationApp.send("Success","The domain was copied to your clipboard","top-right","#10c469","success");
                }, 
                function() {
                    /* clipboard write failed */
                    $.NotificationApp.send("Warning",'Opps! Your browser does not support the Clipboard API',"top-right","#ff5b5b","error");
            });
        });

        // Copy to clipboard
        $('#copyPasswordToClipboardButton').on('click', function(e) {
            e.preventDefault();
            navigator.clipboard.writeText($('#sip_password').val()).then(
                function() {
                    /* clipboard successfully set */
                    $.NotificationApp.send("Success","The password was copied to your clipboard","top-right","#10c469","success");
                }, 
                function() {
                    /* clipboard write failed */
                    $.NotificationApp.send("Warning",'Opps! Your browser does not support the Clipboard API',"top-right","#ff5b5b","error");
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