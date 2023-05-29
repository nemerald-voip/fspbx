@extends('layouts.horizontal', ["page_title"=> "Device"])

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
                                        'device' => $device,
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
    $(document).ready(function() {
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

        $.ajax({
            type : "POST",
            url: $('#device_form').attr('action'),
            cache: false,
            data: $("#device_form").serialize(),
        })
            .done(function(response) {
                //console.log(response);
                $('.loading').hide();

                if (response.error){
                    printErrorMsg(response.error);

                } else {
                    $.NotificationApp.send("Success",response.message,"top-right","#10c469","success");
                    setTimeout(function (){
                        window.location.href = "{{ route('devices.index')}}";
                    }, 1000);

                }
            })
            .fail(function (response){
                $('.loading').hide();
                printErrorMsg(response.responseText);
            });

    })
    </script>
@endpush
