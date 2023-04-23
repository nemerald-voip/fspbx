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


@endpush
