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
                                                                    {{-- Check if this is a mobile app --}}
                                                                    @if (preg_match('/Bria|Push/i', $registration['agent'])>0)
                                                                        <i class="mdi mdi-cellphone-link"><span class="ms-2">Bria Mobile App</span></i>
                                                                    @elseif (preg_match('/Ringotel/i', $registration['agent'])>0)
                                                                        <i class="mdi mdi-cellphone-link"><span class="ms-2">Mobile App</span></i>
                                                                    @else 
                                                                        <i class="uil uil-phone"><span class="ms-2">{{ $registration['agent'] }}</span></i>
                                                                    @endif
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


<!-- MobileAppModal -->
<div id="MobileAppModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="MobileAppModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="MobileAppModalLabel">Mobile App Settings</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
            </div>
                <div class="modal-body">

                    <div class="card">
                        <div class="card-body">
                            <div class="dropdown float-end">
                                {{-- <a href="#" class="dropdown-toggle arrow-none card-drop" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="mdi mdi-dots-horizontal"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end" style="">
                                    <!-- item-->
                                    <a href="javascript:void(0);" class="dropdown-item">View Profile</a>
                                    <!-- item-->
                                    <a href="javascript:void(0);" class="dropdown-item">Project Info</a>
                                </div> --}}
                            </div>

                            <div class="text-center">
                                {{-- <img src="assets/images/users/avatar-1.jpg" class="rounded-circle avatar-md img-thumbnail" alt="friend"> --}}
                                <h3 class="mt-3 my-1"><span id="mobileAppName"></span> </h3>
                                <p class="mb-0 text-muted"></i>Ext: <span id="mobileAppExtension"></span></p>
                                <hr class="bg-dark-lighten my-3">
                                <h5 class="mt-3 mb-3 fw-semibold text-muted">Select an action below</h5>
                            
                                <button id="appDeactivateButton" type="button" class="btn btn-warning me-2 btn-sm" hidden><i class="mdi mdi-power-plug-off me-1"></i> <span>Deactivate</span> </button>
                                <button id="appActivateButton" type="button" class="btn btn-success me-2 btn-sm" hidden><i class="mdi mdi-power-plug me-1"></i> <span>Activate</span> </button>
                                {{-- <a href="javascript:void(0);" class="btn w-100 btn-light" data-bs-toggle="tooltip" data-bs-placement="top" title="" data-bs-original-title="Message" aria-label="Message"><i class="mdi mdi-message-processing-outline"></i></a> --}}

                                <button type="button" class="btn btn-primary me-2 btn-sm"><i class="uil-lock-alt me-1"></i> <span>Reset password</span> </button>

                                {{-- <button id="appUserDeleteButton" type="button" class="btn btn-danger"><i class="uil uil-multiply"></i> <span>Delete</span> </button> --}}

                                <a href="javascript:appUserDeleteAction('{{ route('appsDeleteUser', ':id') }}');" id="appUserDeleteButton" class="btn btn-danger btn-sm">
                                    <i class="uil uil-multiply me-1"></i><span>Delete</span>
                                </a>
                            </div>

                            <div class="alert alert-danger mt-3" id="appMobileAppError" style="display:none">
                                <ul></ul>
                            </div>

                            <div class="alert alert-success mt-3" id="appMobileAppSuccess" style="display:none">
                                <ul></ul>
                            </div>

                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<!-- createMobileAppSuccessModal -->
<div id="createMobileAppSuccessModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="createMobileAppSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="createMobileAppSuccessModalLabel">Create mobile app user</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
            </div>
                <div class="modal-body">

                    <h3 class="text-success">New mobile app was user sucessfully created.</h3>
                    <p>You have successfully created mobile app credentials. Please use the generated password to login. 
                        You will not be able to view the password again. However, you can reset the password at any time.</p>
                    <table class="attributes" width="100%" cellpadding="0" cellspacing="0">
                      <tr>
                        <td class="attributes_content">
                          <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                              <td class="attributes_item"><strong>Domain:</strong><span class="ms-1" id="domainSpan"></span></td>
                            </tr>
                            <tr>
                              <td class="attributes_item"><strong>Extension:</strong><span class="ms-1" id="extensionSpan"></span></td>
                            </tr>
                            <tr>
                                <td class="attributes_item"><strong>Username:</strong><span class="ms-1" id="usernameSpan"></span></td>
                            </tr>
                            <tr>
                            <td class="attributes_item"><strong>Password:</strong><span class="ms-1" id="passwordSpan"></span></td>
                            </tr>  
                          </table>
                        </td>
                      </tr>
                    </table>

                    <p class="mt-2">If the user has an email address on file, we will email a copy of the credentials.</p>
                    
                    <h3 class="mt-3">Next steps</h3>
                    <p>Use the links below to download {{ config('app.name', 'Laravel') }} apps. Then log in using the credentials shown above or scan a QR code via the mobile app interface.</p>
                    
                    <table class="body-action" align="center" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                          <td align="center">
                            <!-- Border based button https://litmus.com/blog/a-guide-to-bulletproof-buttons-in-email-design -->
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td align="center">
                                  <table border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                      <td>
                      
                                        <a href="{{ getDefaultSetting('mobile_apps', 'google_play_link') }}">
                                          <img class="max-width" border="0" style="display:block; color:#000000; text-decoration:none; font-family:Helvetica, arial, sans-serif; font-size:16px; height:auto 
                                            !important;" width="189" alt="Download for Android" data-proportionally-constrained="true" data-responsive="true" 
                                            src="https://cdn.mcauto-images-production.sendgrid.net/b9e58e76174a4c84/88af7fc9-c74b-43ec-a1e2-a712cd1d3052/646x250.png">
                                        </a>
                      
                      
                                      </td>
                                    </tr>
                                  </table>
                                </td>
                              </tr>
                            </table>
                          </td>
                          <td>
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td align="center">
                                  <table border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                      <td>
                                        <a href="{{ getDefaultSetting('mobile_apps', 'apple_store_link') }}"><img class="max-width" border="0" style="display:block; color:#000000; 
                                          text-decoration:none; font-family:Helvetica, arial, sans-serif; font-size:16px; height:auto !important;" width="174" alt="Download for iOS" data-proportionally-constrained="true" data-responsive="true" 
                                          src="https://cdn.mcauto-images-production.sendgrid.net/b9e58e76174a4c84/bb2daef8-a40d-4eed-8fb4-b4407453fc94/320x95.png">
                                        </a>
                                      </td>
                                    </tr>
                                  </table>
                                </td>
                              </tr>
                            </table>
                      
                          </td>
                        </tr>
                        <tr>
                          <td align="center">
                            <!-- Border based button https://litmus.com/blog/a-guide-to-bulletproof-buttons-in-email-design -->
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td align="center">
                                  <table border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                      <td>
                                        <a href="{{ $action_url ?? ''}}" class="button button--" target="_blank">Get it for <strong>Windows</strong></a>
                      
                      
                                      </td>
                                    </tr>
                                  </table>
                                </td>
                              </tr>
                            </table>
                          </td>
                          <td>
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                              <tr>
                                <td align="center">
                                  <table border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                      <td>
                                        <a href="{{ $action_url ?? ''}}" class="button button--" target="_blank">Download for <strong>Mac</strong></a>
                      
                                      </td>
                                    </tr>
                                  </table>
                                </td>
                              </tr>
                            </table>
                      
                          </td>
                        </tr>
                      </table>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>

                </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<!-- createMobileAppSuccessModal -->
<div id="createMobileAppDeactivatedSuccessModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="createMobileAppDeactivatedSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="createMobileAppDeactivatedSuccessModalLabel">Create mobile app user</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
            </div>
                <div class="modal-body">

                    <h3 class="text-success">Success</h3>
                    <p class="mb-3">You successfully created an unactivated user. To register with {{ config('app.name', 'Laravel') }} apps, please activate the user. 
                        Unactivated users are visible in the contacts list in {{ config('app.name', 'Laravel') }} apps.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>

                </div>
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
            //Hide error message
            $("#appUserError").find("ul").html('');
            $("#appUserError").css('display','none');
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
                    $('.loading').hide();
                    if (response.mobile_app){
                        if (!response.mobile_app.status || response.mobile_app.status==2) {
                            $('#appActivateButton').attr("hidden", false);
                        } else if (response.mobile_app.status==1){
                            $('#appDeactivateButton').attr("hidden", false);
                        }
                        dataObj = new Object();
                        dataObj.mobile_app = response.mobile_app;
                        $('#MobileAppModal').data(dataObj).modal("show");
                        $('#mobileAppName').text(response.name);
                        $('#mobileAppExtension').text(response.extension);

                    } else {
                        $('#createMobileAppModal').modal("show");

                        response.connections.forEach(function(connection) {
                            var newOption = new Option(connection.name, connection.id, false, false);
                            $('#connectionSelect2').append(newOption).trigger('change');
                        });

                        $('#org_id').val(response.org_id);
                        $('#app_domain').val(response.app_domain);
                        $('#extension_uuid').val(response.extension_uuid);

                    } 
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
                //console.log(response);
                // remove the spinner and change button to default
                $("#appUserCreateSubmitButton").html('');
                $("#appUserCreateSubmitButton").append('Create User');
                $("#appUserCreateSubmitButton").prop( "disabled", false );

                if (response.error){
                    $("#appUserError").find("ul").html('');
                    $("#appUserError").css('display','block');
                    $("#appUserError").find("ul").append('<li>'+response.error.message+'</li>');
                    
                 } else {
                    $('#createMobileAppModal').modal("hide");
                    if (response.user.status == 1) {
                        $('#createMobileAppSuccessModal').modal("show");

                        $('#usernameSpan').text(response.user.username);
                        $('#extensionSpan').text(response.user.username);
                        $('#passwordSpan').text(response.user.password);
                        $('#domainSpan').text(response.user.domain);
                    } else if(response.user.status == 2) {
                        $('#createMobileAppDeactivatedSuccessModal').modal("show");
                    }

                }
            })
            .fail(function (jqXHR, testStatus, error) {
                    // console.log(error);
                    printErrorMsg(error);
            });
        });

    });


    // Submit request to delete mobile user
    function appUserDeleteAction(url,id=''){
            var mobile_app = $("#MobileAppModal").data("mobile_app");
            url = url.replace(':id', mobile_app.extension_uuid );

            //Change button to spinner
            $("#appUserDeleteButton").html('');
            $("#appUserDeleteButton").append('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading...');
            $("#appUserDeleteButton").prop( "disabled", true );

            // //Hide error message
            $("#appMobileAppError").find("ul").html('');
            $("#appMobileAppError").css('display','none');
            $("#appMobileAppSuccess").find("ul").html('');
            $("#appMobileAppSuccess").css('display','none');

            $.ajax({
                type : "POST",
                url : url,
                data: {
                        'mobile_app' : mobile_app,
                        '_method': 'DELETE',
                    },
            })
            .done(function(response) {
                // console.log(response);
                // remove the spinner and change button to default
                $("#appUserDeleteButton").html('');
                $("#appUserDeleteButton").append('<i class="uil uil-multiply"></i> <span>Delete</span>');
                $("#appUserDeleteButton").prop( "disabled", false );

                if (response.error){
                    $("#appMobileAppError").find("ul").html('');
                    $("#appMobileAppError").css('display','block');
                    $("#appMobileAppError").find("ul").append('<li>'+response.error.message+'</li>');
                    
                 } else {
                    $('#MobileAppModal').modal("hide");
                    $.NotificationApp.send("Success",response.success.message,"top-right","#10c469","success");

                }
            })
            .fail(function (jqXHR, testStatus, error) {
                    // console.log(error);
                    printErrorMsg(error);
            });
        };


</script>
@endpush