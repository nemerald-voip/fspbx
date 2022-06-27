@extends('layouts.singlepage', ["page_title"=> "Caller ID"])

@section('content')
<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                {{-- <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Hyper</a></li>
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Tables</a></li>
                        <li class="breadcrumb-item active">Basic Tables</li>
                    </ol>
                </div> --}}
                <h4 class="page-title">Settings</h4>
            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">User Settings</h4>
                    {{-- <p class="text-muted font-14">
                        For basic styling—light padding and only horizontal dividers—add the base class <code>.table</code> to any <code>&lt;table&gt;</code>.
                    </p> --}}

                    <ul class="nav nav-tabs nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#basic-example-preview" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                Caller ID
                            </a>
                        </li>
                        {{-- <li class="nav-item">
                            <a href="#basic-example-code" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                Code
                            </a>
                        </li> --}}
                    </ul> <!-- end nav-->
                    <div class="tab-content">
                        <div class="tab-pane show active" id="basic-example-preview">
                            <div class="table-responsive-sm">
                                <table class="table table-centered mb-0">
                                    <thead>
                                        <tr>
                                            <th>Label</th>
                                            <th>Phone Number</th>
                                            <th>Active?</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $i = 1;
                                        @endphp
                                        @foreach ($destinations as $destination)
                                            <tr>
                                                <td>{{ $destination['destination_description'] }}</td>
                                                <td>{{ phone($destination['destination_number'],"US",$national_phone_number_format) }}</td>
                                                <td>
                                                    <!-- Switch-->
                                                    <div>
                                                        <input type="checkbox" id="@php print 'switch'.$i; @endphp" class="callerIdCheckbox"
                                                            @if ($destination['isCallerID']) checked @endif 
                                                            value="{{ $destination['destination_uuid'] }}"
                                                            data-switch="success" />
                                                        <label for="@php print 'switch'.$i; @endphp" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                        @php 
                                                            $i++;
                                                        @endphp
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                       
                                    </tbody>
                                </table>
                            </div> <!-- end table-responsive-->
                        </div> <!-- end preview-->

                       
                    </div> <!-- end tab-content-->

                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->


    </div>
    <!-- end row-->


</div> <!-- container -->
@endsection