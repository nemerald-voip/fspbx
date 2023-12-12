@extends('layouts.app', ["page_title"=> "Device"])

@section('content')
    <!-- Start Content-->
    <div class="container-fluid">
        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('devices.index') }}">Devices</a></li>
                            @if($device->exists)
                                <li class="breadcrumb-item active">Edit Device</li>
                            @else
                                <li class="breadcrumb-item active">Create New Device</li>
                            @endif
                        </ol>
                    </div>
                    @if($device->exists)
                        <h4 class="page-title">Edit Device ({{ $device->device_label }})</h4>
                    @else
                        <h4 class="page-title">Create New Device</h4>
                    @endif

                </div>
            </div>
        </div>
        <!-- end page title -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Body Content-->
                            <div class="row">
                                <div class="col-lg-4 offset-lg-4">
                                    @include('layouts.devices.form', [
                                        'action' => route('devices.store'),
                                        'device' => $device->exists ? $device : null,
                                        'extensions' => $extensions,
                                        'vendors' => $vendors,
                                        'profiles' => $profiles
                                    ])
                                </div>
                            </div> <!-- end row-->
                        </div>
                    </div> <!-- end card-body-->
                </div> <!-- end card-->
            </div> <!-- end col -->
        </div>
        <!-- end row-->
    </div> <!-- container -->
@endsection

@push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        $('#extension-select').select2({
            //sorter: data => data.sort((a, b) => b.text.localeCompare(a.text)),
        });

        $('#profile-select').select2({
            //sorter: data => data.sort((a, b) => b.text.localeCompare(a.text)),
        });

        $('#template-select').select2({
            //sorter: data => data.sort((a, b) => b.text.localeCompare(a.text)),
        });
    });

    $('.save-device-btn').on('click', function(e) {
        e.preventDefault();
        $('.loading').show();

        //Reset error messages
        $('.error_message').text("");

        let form = $('#device_form');

        $.ajax({
            type : "POST",
            url: form.attr('action'),
            cache: false,
            data: form.serialize(),
            dataType: 'json',
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
                window.location.href = "{{ route('devices.index')}}";
            },
            error: function(error) {
                $('.loading').hide();
                $('.btn').attr('disabled', false);
                if(error.status == 422){
                    if(error.responseJSON.errors) {
                        $.each( error.responseJSON.errors, function( key, value ) {
                            if (value != '') {
                                form.find('#'+key+'_error').text(value);
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
        });
    })
    </script>
@endpush
