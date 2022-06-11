@extends('layouts.horizontal', ["page_title"=> "Users"])

@section('content')
<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Users</h4>
            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-2">

                        <div class="col-xl-12">
                            <div class="text-xl-end mt-xl-0 mt-2">
                                <a href="{{ Route('usersCreateUser') }}" class="btn btn-success mb-2 me-2">Add</a>
                                {{-- <button type="button" class="btn btn-light mb-2">Export</button> --}}
                            </div>
                        </div><!-- end col-->
                    </div>

                    <div class="table-responsive">
                        <table class="table table-centered mb-0" id="user_list">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 20px;">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="customCheck1">
                                            <label class="form-check-label" for="customCheck1">&nbsp;</label>
                                        </div>
                                    </th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Enabled</th>
                                    <th style="width: 125px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>

                                @foreach ($users as $key=>$user)
                                        <tr>
                                        <td>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input appCompanyCheckbox">
                                                <label class="form-check-label" >&nbsp;</label>
                                            </div>
                                        </td>
                                        <td><a href="" class="text-body fw-bold">{{ $user['username'] }}</a> </td>
                                        <td>
                                            {{ $user['user_email'] }} 
                                        </td>
                                        <td>
                                            @if ($user['user_enabled']=='true') 
                                                <h5><span class="badge bg-success"></i>Enabled</span></h5>
                                            @else 
                                                <h5><span class="badge bg-warning">Disabled</span></h5>
                                            @endif
                                        </td>
                                        <td>
                                            {{-- <a href="javascript:void(0);" class="action-icon"> <i class="mdi mdi-eye"></i></a> --}}
                                            <a href="{{ route('editUser', base64_encode($user['user_uuid'])) }}" class="action-icon"> <i class="mdi mdi-square-edit-outline"></i></a>
                                            {{-- <a href="javascript:void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a> --}}
                                        </td>
                                    </tr>
                                @endforeach


                                {{-- @php
                                    $i = 1;
                                @endphp
                                @foreach ($companies as $company)
                                    <tr>
                                        <td>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input appCompanyCheckbox" id="@php print 'companyCheck'.$i; @endphp" 
                                                    value="{{ $company['domain_uuid'] }}">
                                                <label class="form-check-label" for="@php print 'companyCheck'.$i; @endphp">&nbsp;</label>
                                            </div>
                                        </td>
                                        <td><a href="" class="text-body fw-bold">{{ $company['name'] }}</a> </td>
                                        <td>
                                            {{ $company['domain'] }} 
                                        </td>
                                        <td>
                                            @if ($company['status']) 
                                                <h5><span class="badge bg-success"></i>Provisioned</span></h5>
                                            @else 
                                                <h5><span class="badge bg-warning">Inactive</span></h5>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">Coming Soon...</small>
                                        </td>
                                        <td>
                                            <small class="text-muted">Coming Soon...</small>
                                        </td>
                                        <td>
                                            <a href="javascript:void(0);" class="action-icon"> <i class="mdi mdi-eye"></i></a>
                                            <a href="javascript:void(0);" class="action-icon"> <i class="mdi mdi-square-edit-outline"></i></a>
                                            <a href="javascript:void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a>
                                        </td>
                                    </tr>
                                    @php 
                                        $i++;
                                    @endphp
                                @endforeach --}}

                            </tbody>
                        </table>
                    </div>
                </div> <!-- end card-body-->
            </div> <!-- end card-->
        </div> <!-- end col -->
    </div>
    <!-- end row -->

</div> <!-- container -->

<script src="{{asset('assets/js/vendor.js')}}"></script>
{{-- @yield('script') --}}
{{-- @yield('script-bottom') --}}

<script>
    
</script>



@endsection