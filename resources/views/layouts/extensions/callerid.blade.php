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
                                            <th>Location</th>
                                            <th>Phone Number</th>
                                            <th>Active?</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>California</td>
                                            <td>336-508-2157</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch1"  data-switch="success" />
                                                    <label for="switch1" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Toll Free</td>
                                            <td>646-473-2057</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch2" checked data-switch="success" />
                                                    <label for="switch2" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Oregon</td>
                                            <td>281-308-0793</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch3" data-switch="success" />
                                                    <label for="switch3" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Nevada</td>
                                            <td>606-253-1207</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch4" data-switch="success" />
                                                    <label for="switch4" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
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