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
                                <button type="button" class="btn btn-success mb-2 me-2 ">Add New</button>
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
                                            <input type="checkbox" class="form-check-input" id="customCheck1">
                                            <label class="form-check-label" for="customCheck1">&nbsp;</label>
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
                                    <tr>
                                        <td>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input extensionCheckbox" id="@php print 'extensionCheck'.$i; @endphp" 
                                                    value="{{ $extension['extension_uuid'] }}">
                                                <label class="form-check-label" for="@php print 'extensionCheck'.$i; @endphp">&nbsp;</label>
                                            </div>
                                        </td>
                                        <td><a href="" class="text-body fw-bold">{{ $extension['extension'] }}</a> </td>
                                        <td>
                                            {{ $extension['effective_caller_id_name'] }} 
                                        </td>
                                        <td>
                                            {{ $extension->voicemail()->voicemail_mail_to }} 
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
                                            {{-- <a href="javascript:void(0);" class="action-icon"> <i class="mdi mdi-eye"></i></a> --}}
                                            <a href="javascript:void(0);" class="action-icon"> <i class="mdi mdi-lead-pencil"></i></a>
                                            <a href="javascript:void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a>
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



@endsection