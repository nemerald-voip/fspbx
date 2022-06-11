@extends('layouts.horizontal', ["page_title"=> "Users"])

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.css" integrity="sha512-3pIirOrwegjM6erE5gPSwkUzO+3cTjpnV9lexlNZqvupR64iZBnOOTiiLPb9M36zpMScbmUNIcHUqKD47M719g==" crossorigin="anonymous" referrerpolicy="no-referrer" />

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
                                <a href="{{ Route('usersCreateUser') }}" class="btn btn-success mb-2 me-2">Add User</a>
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
                                            <input type="checkbox" class="form-check-input" id="selectallCheckbox">
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
                                                <input type="checkbox" name="action_box[]" value="{{$user['user_uuid']}}" class="form-check-input action_checkbox">
                                                <label class="form-check-label" >&nbsp;</label>
                                            </div>
                                        </td>
                                        <td><a href="javascript:;" class="text-body fw-bold">{{ $user['username'] }}</a> </td>
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
                                            <a href="{{ route('editUser', base64_encode($user['user_uuid'])) }}" class="action-icon" title="Edit"> <i class="mdi mdi-square-edit-outline"></i></a>
                                            <a href="javascript:resetPassword('{{ $user['user_email'] }}');" class="action-icon"> <i class="mdi mdi-account-key-outline" title="Reset Password"></i></a>
                                            <a href="javascript:void(0);" class="action-icon"> <i class="mdi mdi-delete" title="Delete"></i></a>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js" integrity="sha512-VEd+nq25CkR676O+pLBnDW09R7VQX9Mdiij052gVCp5yVH3jGtH70Ho/UUv4mJDsEdTvqRCFZg0NKGiojGnUCw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js" integrity="sha512-AA1Bzp5Q0K1KanKKmvN/4d3IRKVlv9PYgwFPvm32nPO6QS8yH1HO7LbgB1pgiOxPtfeg5zEn2ba64MUcqJx6CA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
{{-- @yield('script') --}}
{{-- @yield('script-bottom') --}}

<script>
    $(document).ready(function() {
        $('#selectallCheckbox').on('change',function(){
            if($(this).is(':checked')){
                $('.action_checkbox').attr('checked',true);
            } else {
                $('.action_checkbox').attr('checked',false);
            }
        });
    });

    function resetPassword(user_email){

        swal({
            title: "Are you sure?",
            text: "You want to send reset password email!",
            icon: "warning",
            buttons: true,
            dangerMode: false,
            cancel: {
                text: "No, cancel it!",
                value: false,
                closeModal: true,
            },
            confirm: {
                text: "Yes, I am sure!",
                value: true,
                closeModal: true
            }
            })
            .then((willsend) => {
            if (willsend) {
                sendResetEmail(user_email);
            }
            });

    }

    function sendResetEmail(user_email){

        $('.loading').show();
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $.ajax({
            url: "{{ url('password/email') }}", // point to server-side PHP script
            dataType: "json",
            cache: false,
            data: {
                email:user_email
            },
            type: 'post',
            success: function(res) {
                $('.loading').hide();
                if (res.errors==undefined) {
                    toastr.success('Email Sent Successfully!');
                }
            },
            error: function(res){
                $('.loading').hide();
                  $.each(res.responseJSON.errors,function(key,error){
                        toastr.error(error);
                });
            }
        });
    }
    
</script>



@endsection