@extends('layouts.partials.listing.layout', ['pageTitle' => 'Extensions', 'breadcrumbs' => []])

@section('pagination')
    @include('layouts.partials.listing.pagination', ['collection' => $extensions])
@endsection

@section('actionbar')
    @if ($permissions['import'])
        <button type="button" class="btn btn-sm btn-outline-info mb-2 me-2" data-bs-toggle="modal"
            data-bs-target="#extension-upload-modal"><i class="uil uil-upload me-1"></i>Import</button>
    @endif
    @if ($permissions['add_new'])
        <a href="{{ route('extensions.create') }}" class="btn btn-sm btn-success mb-2 me-2">
            <i class="uil uil-plus me-1"></i>
            Add New
        </a>
    @endif
    @if ($permissions['delete'])
        <a href="javascript:confirmDeleteAction('{{ route('extensions.destroy', ':id') }}');"
            id="deleteMultipleActionButton" class="btn btn-danger btn-sm mb-2 me-2 disabled">Delete Selected</a>
    @endif
    {{-- <button type="button" class="btn btn-light mb-2">Export</button> --}}
@endsection

@section('searchbar')
    <form id="filterForm" method="GET" action="{{ url()->current() }}?page=1"
        class="row gy-2 gx-2 align-items-center justify-content-xl-start justify-content-between">
        <div class="col-auto">
            <label for="search" class="visually-hidden">Search</label>
            <div class="input-group input-group-merge">
                <input type="search" class="form-control" name="search" id="search" value="{{ $searchString }}"
                    placeholder="Search..." />
                <input type="button" class="btn btn-light" name="clear" id="clearSearch" value="Clear" />
            </div>
        </div>
        <div class="d-none"><input type="submit" name="submit" value="Ok" /></div>
    </form>
@endsection

@section('table-head')
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
        <th>Description</th>
        <th style="width: 140px;">Action</th>
    </tr>
@endsection

@section('table-body')
    @if ($extensions->count() == 0)
        @include('layouts.partials.listing.norecordsfound', ['colspan' => 9])
    @else
        @foreach ($extensions as $extension)
            <tr id="id{{ $extension->extension_uuid }}">
                <td>
                    <div class="form-check">
                        <input type="checkbox" name="action_box[]" value="{{ $extension->extension_uuid }}"
                            class="form-check-input action_checkbox">
                        <label class="form-check-label">&nbsp;</label>
                    </div>
                </td>
                <td>
                    @if ($extension['registrations'])
                        {{-- <h6><span class="badge bg-success rounded-pill dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">&nbsp;&nbsp;&nbsp;</span></h6> --}}
                        <a class="badge bg-success rounded-pill dropdown-toggle text-success" href="#"
                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span></span></a>
                        <div class="dropdown-menu p-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title text-primary">Registered devices</h5>
                                    @foreach ($extension['registrations'] as $registration)
                                        <p class="card-text">
                                            {{-- Check if this is a mobile app --}}
                                            @if (preg_match('/Bria|Push/i', $registration['agent']) > 0)
                                                <i class="mdi mdi-cellphone-link"><span class="ms-2">Bria Mobile
                                                        App</span></i>
                                            @elseif (preg_match('/Ringotel/i', $registration['agent']) > 0)
                                                <i class="mdi mdi-cellphone-link"><span class="ms-2">Mobile App</span></i>
                                            @else
                                                <i class="uil uil-phone"><span
                                                        class="ms-2">{{ $registration['agent'] }}</span></i>
                                            @endif
                                        </p>
                                    @endforeach
                                </div> <!-- end card-body-->
                            </div>

                        </div>
                    @else
                        <h6><span class="badge bg-light rounded-pill">&nbsp;&nbsp;&nbsp;</span>
                        </h6>
                    @endif

                </td>
                <td>
                    <a href="{{ route('extensions.edit', $extension) }}"
                        class="text-body fw-bold me-2">{{ $extension['extension'] }}</a>
                </td>
                <td>
                    <a href="{{ route('extensions.edit', $extension) }}"
                        class="text-body fw-bold me-1">{{ $extension['effective_caller_id_name'] }}
                    </a>
                    @if ($extension['do_not_disturb'] == 'true')
                        <small><span class="badge badge-outline-danger">DND</span></small>
                    @endif
                    @if ($extension['forward_all_enabled'] == 'true')
                        <small><span class="badge badge-outline-primary">FWD</span></small>
                    @endif
                    @if ($extension['follow_me_enabled'] == 'true')
                        <small><span class="badge badge-outline-primary">Sequence</span></small>
                    @endif
                </td>
                <td>
                    {{-- @if ($extension->voicemail->exists) --}}
                    {{ $extension->voicemail->voicemail_mail_to ?? '' }}
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

                        <a href="{{ route('extensions.edit', $extension) }}" class="action-icon" title="Edit">
                            <i class="mdi mdi-lead-pencil" data-bs-container="#tooltip-container-actions"
                                data-bs-toggle="tooltip" data-bs-placement="bottom" title="Edit user"></i>
                        </a>

                        <a href="#" data-attr="{{ route('mobileAppUserSettings', $extension) }}"
                            class="action-icon mobileAppButton" title="Mobile App Settings">
                            <i class="mdi mdi-cellphone-cog" data-bs-container="#tooltip-container-actions"
                                data-bs-toggle="tooltip" data-bs-placement="bottom" title="Mobile App Settings"></i>
                        </a>

                        <a href="javascript:confirmDeleteAction('{{ route('extensions.destroy', ':id') }}','{{ $extension->extension_uuid }}');"
                            class="action-icon">
                            <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions"
                                data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i>
                        </a>

                        @if (userCheckPermission('extension_advanced'))
                            {{-- <div class="dropdown"> --}}
                            <a class="dropdown-toggle arrow-none card-drop" href="#" id="dropdownAdvancedOptions"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="mdi mdi-dots-vertical"></i>
                            </a>

                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownAdvancedOptions">
                                <a href="#" data-attr="{{ route('extensions.sip.show', $extension) }}"
                                    class="dropdown-item sipCredentialsButton">SIP
                                    Credentials</a>

                            </div>
                            {{-- </div> --}}
                        @endif
                    </div>
                    {{-- End of action buttons --}}

                </td>
            </tr>
        @endforeach
    @endif
@endsection




@section('includes')
    @include('layouts.extensions.extensionUploadModal')
    @include('layouts.extensions.createMobileAppModal')
    @include('layouts.extensions.mobileAppModal')
    @include('layouts.extensions.createMobileAppSuccessModal')
    @include('layouts.extensions.createMobileAppDeactivatedSuccessModal')
    @include('layouts.extensions.sipCredentialsModal')
    @include('layouts.extensions.extensionUploadResultModal');
@endsection


@push('scripts')
    <!-- dropzone js -->
    <script src="{{ asset('assets/libs/dropzone/dropzone.min.js') }}"></script>

    <script>
        Dropzone.autoDiscover = false;

        // set the dropzone container id
        const id = "#file_dropzone";
        const dropzone = document.querySelector(id);

        // set the preview element template
        var previewNode = dropzone.querySelector(".dropzone-item");
        previewNode.id = "";
        var previewTemplate = previewNode.parentNode.innerHTML;
        previewNode.parentNode.removeChild(previewNode);

        var fileDropzone = new Dropzone(id, {
            url: '{{ route('extensions.import') }}', // Set the url for your upload script location
            autoProcessQueue: false,
            uploadMultiple: false,
            parallelUploads: 5,
            maxFilesize: 5, // Max filesize in MB
            maxFiles: 1,
            previewTemplate: previewTemplate,
            previewsContainer: id + " .dropzone-items", // Define the container to display the previews
            clickable: id +
                " .dropzone-select", // Define the element that should be used as click trigger to select files.
            thumbnailWidth: 200,
            acceptedFiles: ".csv,.xls,.xlsx",
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
            // accept: function(file, done) {
            //     var reader = new FileReader();
            //     reader.onload = handleReaderLoad;
            //     reader.readAsDataURL(file);

            //     function handleReaderLoad(e) {
            //         var filePayload = e.target.result;
            //         files.push ({'name': file.upload.filename,  'data': filePayload})
            //         // file.upload.filename: 
            //     }

            //     done();
            // }

        });

        fileDropzone.on("addedfile", function(file) {
            // Hookup the start button
            const dropzoneItems = dropzone.querySelectorAll('.dropzone-item');
            dropzoneItems.forEach(dropzoneItem => {
                dropzoneItem.style.display = '';
            });

        });

        fileDropzone.on("removedfile", function(file) {
            //
        });

        fileDropzone.on("success", function(file) {
            this.removeAllFiles(true);
            $('#extension-upload-modal').modal("hide");
            $('#extensionUploadResultModal').modal("show");
            $('#dropzoneSuccess').show();
            $('#dropzoneError').hide();

            // if (fileDropzone.getRejectedFiles().length == 0) {
            // //No errors
            // } else {
            //     console.log("Errors");
            // }

            // Successful Notification
            $.NotificationApp.send("Success", "Extensions have been successfully imported", "top-right", "#10c469",
                "success");

            setTimeout(function() {
                window.location.reload();
            }, 1000);

        });
        fileDropzone.on("complete", function(file) {
            //
        });

        fileDropzone.on("error", function(file, message) {
            this.removeAllFiles(true);
            $('#extension-upload-modal').modal("hide");
            $('#extensionUploadResultModal').modal("show");
            $('#dropzoneError').html(message.error);
            $('#dropzoneError').show();
            $('#dropzoneSuccess').hide();
            // Warning Notification
            $.NotificationApp.send("Warning", message.error, "top-right", "#ff5b5b", "error");
        });



        $(document).ready(function() {

            $("#connectionSelect2").select2({
                dropdownParent: $("#createMobileAppModal")
            });

            localStorage.removeItem('activeTab');

            // Open Modal with mobile app settings
            $('.mobileAppButton').on('click', function(e) {
                e.preventDefault();
                let href = $(this).attr('data-attr');
                //Hide error message
                $("#appMobileAppError").find("ul").html('');
                $("#appMobileAppError").css('display', 'none');
                //Hide success message
                $("#appMobileAppSuccess").find("ul").html('');
                $("#appMobileAppSuccess").css('display', 'none');
                $('.loading').show();
                //Reset buttons to default
                $("#appUserDeleteButton").html('');
                $("#appUserDeleteButton").append('<i class="uil uil-multiply"></i> <span>Delete</span>');
                $("#appUserDeleteButton").prop("disabled", false);
                $("#appUserResetPasswordButton").html('');
                $("#appUserResetPasswordButton").append(
                    '<i class="uil-lock-alt me-1"></i> <span>Reset password</span>');
                $("#appUserResetPasswordButton").prop("disabled", false);
                $("#activate").prop('checked', true);

                $.ajax({
                        type: "POST",
                        url: href,
                        cache: false,
                    })
                    .done(function(response) {
                        // console.log(response);
                        if (response.error) {
                            $('.loading').hide();
                            printErrorMsg(response.error);
                        } else {
                            $('.loading').hide();
                            if (response.mobile_app) {
                                if (!response.mobile_app.status || response.mobile_app.status == 2 ||
                                    response.mobile_app.status == -1) {
                                    $("#appUserSetStatusButton").html('');
                                    $("#appUserSetStatusButton").append(
                                        '<i class="mdi mdi-power-plug-off me-1"></i> <span>Activate</span>'
                                    );
                                    $("#appUserSetStatusButton").addClass('btn-success');
                                    $("#appUserSetStatusButton").removeClass('btn-warning');
                                    $("#appUserSetStatusButton").prop("disabled", false);
                                    $('#appUserResetPasswordButton').hide();
                                } else if (response.mobile_app.status == 1) {
                                    $("#appUserSetStatusButton").html('');
                                    $("#appUserSetStatusButton").append(
                                        '<i class="mdi mdi-power-plug-off me-1"></i> <span>Deactivate</span>'
                                    );
                                    $("#appUserSetStatusButton").addClass('btn-warning');
                                    $("#appUserSetStatusButton").removeClass('btn-success');
                                    $("#appUserSetStatusButton").prop("disabled", false);
                                    $('#appUserResetPasswordButton').show();
                                }
                                dataObj = new Object();
                                dataObj.mobile_app = response.mobile_app;
                                $('#MobileAppModal').data(dataObj).modal("show");
                                $('#mobileAppName').text(response.name);
                                $('#mobileAppExtension').text(response.extension);

                            } else {
                                $('#createMobileAppModal').modal("show");

                                response.connections.forEach(function(connection) {
                                    var newOption = new Option(connection.name, connection.id,
                                        false, false);
                                    $('#connectionSelect2').append(newOption).trigger('change');
                                });

                                $('#org_id').val(response.org_id);
                                $('#app_domain').val(response.app_domain);
                                $('#extension_uuid').val(response.extension_uuid);

                            }
                        }
                    })
                    .fail(function(jqXHR, testStatus, error) {
                        //console.log(error);
                        printErrorMsg(error);
                        $('.loading').hide();

                    });
            });

            // Extension import submit form
            $('#importExtensionsSubmit').on('click', function(e) {
                e.preventDefault();
                fileDropzone.processQueue();

            })


            // Open Modal to show SIP credentials
            $('.sipCredentialsButton').on('click', function(e) {
                e.preventDefault();
                let href = $(this).attr('data-attr');
                $('.loading').show();

                $.ajax({
                        type: "GET",
                        url: href,
                        cache: false,
                    })
                    .done(function(response) {
                        //console.log(response);
                        if (response.error) {
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
                    .fail(function(jqXHR, testStatus, error) {
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
                        $.NotificationApp.send("Success", "The username was copied to your clipboard",
                            "top-right", "#10c469", "success");
                    },
                    function() {
                        /* clipboard write failed */
                        $.NotificationApp.send("Warning",
                            'Opps! Your browser does not support the Clipboard API', "top-right",
                            "#ff5b5b", "error");
                    });
            });

            // Copy to clipboard
            $('#copyDomainToClipboardButton').on('click', function(e) {
                e.preventDefault();
                navigator.clipboard.writeText($('#sip_domain').val()).then(
                    function() {
                        /* clipboard successfully set */
                        $.NotificationApp.send("Success", "The domain was copied to your clipboard",
                            "top-right", "#10c469", "success");
                    },
                    function() {
                        /* clipboard write failed */
                        $.NotificationApp.send("Warning",
                            'Opps! Your browser does not support the Clipboard API', "top-right",
                            "#ff5b5b", "error");
                    });
            });

            // Copy to clipboard
            $('#copyPasswordToClipboardButton').on('click', function(e) {
                e.preventDefault();
                navigator.clipboard.writeText($('#sip_password').val()).then(
                    function() {
                        /* clipboard successfully set */
                        $.NotificationApp.send("Success", "The password was copied to your clipboard",
                            "top-right", "#10c469", "success");
                    },
                    function() {
                        /* clipboard write failed */
                        $.NotificationApp.send("Warning",
                            'Opps! Your browser does not support the Clipboard API', "top-right",
                            "#ff5b5b", "error");
                    });
            });


            // Submit form to create a new user
            $('#createUserForm').on('submit', function(e) {
                e.preventDefault();
                //Change button to spinner
                $("#appUserCreateSubmitButton").html('');
                $("#appUserCreateSubmitButton").append(
                    '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading...'
                );
                $("#appUserCreateSubmitButton").prop("disabled", true);

                //Hide error message
                $("#appUserError").find("ul").html('');
                $("#appUserError").css('display', 'none');

                var url = '{{ route('appsCreateUser') }}';

                $.ajax({
                        type: "POST",
                        url: url,
                        data: $(this).serialize(),
                    })
                    .done(function(response) {
                        // console.log(response);
                        // remove the spinner and change button to default
                        $("#appUserCreateSubmitButton").html('');
                        $("#appUserCreateSubmitButton").append('Create User');
                        $("#appUserCreateSubmitButton").prop("disabled", false);

                        if (response.error) {
                            $("#appUserError").find("ul").html('');
                            $("#appUserError").css('display', 'block');
                            $("#appUserError").find("ul").append('<li>' + response.error.message +
                                '</li>');

                        } else {
                            $('#createMobileAppModal').modal("hide");
                            if (response.user.status == 1) {
                                $('#createMobileAppSuccessModal').modal("show");
                                $('#createMobileAppSuccessModalLabel').text("Create mobile app user");
                                $('#createMobileAppSuccessModalTitle').text(
                                    'New mobile app was user sucessfully created.');
                                $('#usernameSpan').text(response.user.username);
                                $('#extensionSpan').text(response.user.username);
                                $('#passwordSpan').text(response.user.password);
                                $('#domainSpan').text(response.user.domain);
                                $('#qrCode').html('<img src="data:image/png;base64, ' + response
                                    .qrcode + '" />');
                            } else if (response.user.status == -1) {
                                $('#createMobileAppDeactivatedSuccessModal').modal("show");
                            }

                        }
                    })
                    .fail(function(jqXHR, testStatus, error) {
                        // console.log(error);
                        printErrorMsg(error);
                    });
            });

        });


        // Submit request to delete mobile user
        function appUserDeleteAction(url, id = '') {
            var mobile_app = $("#MobileAppModal").data("mobile_app");
            url = url.replace(':id', mobile_app.extension_uuid);

            //Change button to spinner
            $("#appUserDeleteButton").html('');
            $("#appUserDeleteButton").append(
                '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading...'
            );
            $("#appUserDeleteButton").prop("disabled", true);

            // //Hide error message
            $("#appMobileAppError").find("ul").html('');
            $("#appMobileAppError").css('display', 'none');
            $("#appMobileAppSuccess").find("ul").html('');
            $("#appMobileAppSuccess").css('display', 'none');

            $.ajax({
                    type: "POST",
                    url: url,
                    data: {
                        'mobile_app': mobile_app,
                        '_method': 'DELETE',
                    },
                })
                .done(function(response) {
                    // console.log(response);
                    // remove the spinner and change button to default
                    $("#appUserDeleteButton").html('');
                    $("#appUserDeleteButton").append('<i class="uil uil-multiply"></i> <span>Delete</span>');
                    $("#appUserDeleteButton").prop("disabled", false);

                    if (response.error) {
                        $("#appMobileAppError").find("ul").html('');
                        $("#appMobileAppError").css('display', 'block');
                        $("#appMobileAppError").find("ul").append('<li>' + response.error.message + '</li>');

                    } else {
                        $('#MobileAppModal').modal("hide");
                        $.NotificationApp.send("Success", response.success.message, "top-right", "#10c469", "success");

                    }
                })
                .fail(function(jqXHR, testStatus, error) {
                    // console.log(error);
                    printErrorMsg(error);
                });
        };


        // Submit request to reset password for mobile user
        function appUserResetPasswordAction(url, id = '') {
            var mobile_app = $("#MobileAppModal").data("mobile_app");
            url = url.replace(':id', mobile_app.extension_uuid);

            //Change button to spinner
            $("#appUserResetPasswordButton").html('');
            $("#appUserResetPasswordButton").append(
                '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading...'
            );
            $("#appUserResetPasswordButton").prop("disabled", true);

            // //Hide error message
            $("#appMobileAppError").find("ul").html('');
            $("#appMobileAppError").css('display', 'none');
            $("#appMobileAppSuccess").find("ul").html('');
            $("#appMobileAppSuccess").css('display', 'none');

            $.ajax({
                    type: "POST",
                    url: url,
                    data: {
                        'mobile_app': mobile_app,
                    },
                })
                .done(function(response) {
                    //console.log(response);
                    // remove the spinner and change button to default
                    $("#appUserResetPasswordButton").html('');
                    $("#appUserResetPasswordButton").append(
                        '<i class="uil-lock-alt me-1"></i> <span>Reset password</span>');
                    $("#appUserResetPasswordButton").prop("disabled", false);

                    if (response.error) {
                        $("#appMobileAppError").find("ul").html('');
                        $("#appMobileAppError").css('display', 'block');
                        $("#appMobileAppError").find("ul").append('<li>' + response.error.message + '</li>');

                    } else {
                        $('#MobileAppModal').modal("hide");
                        // $("#appMobileAppSuccess").find("ul").html('');
                        // $("#appMobileAppSuccess").css('display','block');
                        // $("#appMobileAppSuccess").find("ul").append('<li>'+response.success.message+'</li>');
                        $('#createMobileAppSuccessModal').modal("show");
                        $('#createMobileAppSuccessModalLabel').text("Reset Password");
                        $('#createMobileAppSuccessModalTitle').text('Success');

                        $('#usernameSpan').text(response.user.username);
                        $('#extensionSpan').text(response.user.username);
                        $('#passwordSpan').text(response.user.password);
                        $('#domainSpan').text(response.user.domain);
                        $('#qrCode').html('<img src="data:image/png;base64, ' + response.qrcode + '" />');

                    }
                })
                .fail(function(jqXHR, testStatus, error) {
                    // console.log(error);
                    printErrorMsg(error);
                });
        };


        // Submit request to reset password for mobile user
        function appUserSetStatusAction(url, id = '') {
            var mobile_app = $("#MobileAppModal").data("mobile_app");
            url = url.replace(':id', mobile_app.extension_uuid);
            // console.log (mobile_app.status);
            // Set new status
            if (!mobile_app.status || mobile_app.status == -1) {
                mobile_app.status = 1;
            } else {
                mobile_app.status = -1
            }

            //Change button to spinner
            $("#appUserSetStatusButton").html('');
            $("#appUserSetStatusButton").append(
                '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading...'
            );
            $("#appUserSetStatusButton").prop("disabled", true);

            // //Hide error message
            $("#appMobileAppError").find("ul").html('');
            $("#appMobileAppError").css('display', 'none');
            $("#appMobileAppSuccess").find("ul").html('');
            $("#appMobileAppSuccess").css('display', 'none');

            $.ajax({
                    type: "POST",
                    url: url,
                    data: {
                        'mobile_app': mobile_app,
                    },
                })
                .done(function(response) {
                    // console.log(response);
                    if (response.error) {

                        // remove the spinner and change button to default
                        if (mobile_app.status == -1) {
                            $("#appUserSetStatusButton").html('');
                            $("#appUserSetStatusButton").append(
                                '<i class="mdi mdi-power-plug-off me-1"></i> <span>Deactivate</span>');
                            $("#appUserSetStatusButton").addClass('btn-warning');
                            $("#appUserSetStatusButton").removeClass('btn-success');
                            $("#appUserSetStatusButton").prop("disabled", false);
                            $('#appUserResetPasswordButton').show();
                            mobile_app.status = 1;
                        }
                        if (mobile_app.status == 1) {
                            $("#appUserSetStatusButton").html('');
                            $("#appUserSetStatusButton").append(
                                '<i class="mdi mdi-power-plug-off me-1"></i> <span>Activate</span>');
                            $("#appUserSetStatusButton").addClass('btn-success');
                            $("#appUserSetStatusButton").removeClass('btn-warning');
                            $("#appUserSetStatusButton").prop("disabled", false);
                            $('#appUserResetPasswordButton').hide();
                            mobile_app.status = -1;
                        }
                        dataObj = new Object();
                        dataObj.mobile_app = mobile_app;
                        $('#MobileAppModal').data(dataObj);

                        $("#appMobileAppError").find("ul").html('');
                        $("#appMobileAppError").css('display', 'block');
                        $("#appMobileAppError").find("ul").append('<li>' + response.error.message + '</li>');

                    } else {
                        // remove the spinner and change button to default
                        if (mobile_app.status == 1) {
                            $("#appUserSetStatusButton").html('');
                            $("#appUserSetStatusButton").append(
                                '<i class="mdi mdi-power-plug-off me-1"></i> <span>Deactivate</span>');
                            $("#appUserSetStatusButton").addClass('btn-warning');
                            $("#appUserSetStatusButton").removeClass('btn-success');
                            $("#appUserSetStatusButton").prop("disabled", false);
                            $('#appUserResetPasswordButton').show();
                        }
                        if (mobile_app.status == -1) {
                            $("#appUserSetStatusButton").html('');
                            $("#appUserSetStatusButton").append(
                                '<i class="mdi mdi-power-plug-off me-1"></i> <span>Activate</span>');
                            $("#appUserSetStatusButton").addClass('btn-success');
                            $("#appUserSetStatusButton").removeClass('btn-warning');
                            $("#appUserSetStatusButton").prop("disabled", false);
                            $('#appUserResetPasswordButton').hide();
                        }

                        $("#appMobileAppSuccess").find("ul").html('');
                        $("#appMobileAppSuccess").css('display', 'block');
                        $("#appMobileAppSuccess").find("ul").append('<li>' + response.success.message + '</li>');

                    }
                })
                .fail(function(jqXHR, testStatus, error) {
                    // console.log(error);
                    printErrorMsg(error);
                });

        };
    </script>
@endpush
