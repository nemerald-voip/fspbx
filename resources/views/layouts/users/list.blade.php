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
                        <div class="col-xl-4">
                            <label class="form-label">Showing {{ $users->count() ?? 0 }}  results for Users</label>
                        </div>
                        <div class="col-xl-8">
                            <div class="text-xl-end mt-xl-0 mt-2">
                                <a href="{{ route('users.create') }}" class="btn btn-success mb-2 me-2">Add User</a>
                                <a href="javascript:confirmDeleteAction('{{ route('users.destroy', ':id') }}');" id="deleteMultipleActionButton" class="btn btn-danger mb-2 me-2 disabled">Delete Selected</a>
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
                                            <label class="form-check-label" for="selectallCheckbox">&nbsp;</label>
                                        </div>
                                    </th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Enabled</th>
                                    <th style="width: 125px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>

                                @foreach ($users as $key=>$user)
                                    <tr id="id{{ $user->user_uuid  }}">
                                        <td>
                                            <div class="form-check">
                                                <input type="checkbox" name="action_box[]" value="{{ $user->user_uuid }}" class="form-check-input action_checkbox">
                                                <label class="form-check-label" >&nbsp;</label>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route('users.edit',$user) }}" class="text-body fw-bold">
                                                @if ($user->user_adv_fields) 
                                                    {{ $user->user_adv_fields->first_name }} {{ $user->user_adv_fields->last_name }}
                                                @else
                                                    {{ $user->username }}
                                                @endif
                                            </a> 
                                        </td>
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
                                            
                                            {{-- Action Buttons --}}
                                            <div id="tooltip-container-actions">

                                                <a href="{{ route('users.edit',$user) }}" class="action-icon" title="Edit"> 
                                                    <i class="mdi mdi-square-edit-outline" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Edit user"></i>
                                                </a>
                                                <a href="javascript:resetPassword('{{ $user['user_email'] }}');" class="action-icon"> 
                                                    <i class="mdi mdi-account-key-outline" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Reset Password"></i>
                                                </a>

                                                <a href="javascript:confirmDeleteAction('{{ route('users.destroy', ':id') }}','{{ $user->user_uuid }}');" class="action-icon"> 
                                                    <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i>
                                                </a>
                                            </div>
                                            {{-- End of action buttons --}}
                                        </td>
                                    </tr>
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

<script src="{{asset('assets/js/vendor.js')}}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js" integrity="sha512-VEd+nq25CkR676O+pLBnDW09R7VQX9Mdiij052gVCp5yVH3jGtH70Ho/UUv4mJDsEdTvqRCFZg0NKGiojGnUCw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js" integrity="sha512-AA1Bzp5Q0K1KanKKmvN/4d3IRKVlv9PYgwFPvm32nPO6QS8yH1HO7LbgB1pgiOxPtfeg5zEn2ba64MUcqJx6CA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
{{-- @yield('script') --}}
{{-- @yield('script-bottom') --}}

<script>
    $(document).ready(function() {
        $('#selectallCheckbox').on('change',function(){
            if($(this).is(':checked')){
                $('.action_checkbox').prop('checked',true);
            } else {
                $('.action_checkbox').prop('checked',false);
            }
        });

        $('.action_checkbox').on('change',function(){
            if(!$(this).is(':checked')){
                $('#selectallCheckbox').prop('checked',false);
            } else {
                if(checkAllbox()){
                    $('#selectallCheckbox').prop('checked',true);
                }
            }
        });
    });

    function checkAllbox(){
        var checked=true;
        $('.action_checkbox').each(function(key,val){
            if(!$(this).is(':checked')){
                checked=false;
            }
        });
        return checked;
    }

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

    function deleteUser(user_id){
         $('.loading').show();
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $.ajax({
            url: "{{ Route('deleteUser') }}", // point to server-side PHP script
            dataType: "json",
            cache: false,
            data: {
                contact_id:user_id
            },
            type: 'post',
            success: function(res) {
                $('.loading').hide();
                if (res.success) {
                    toastr.success('Deleted Successfully!');
                       setTimeout(function (){
                        window.location.reload();
                    }, 2000);
                }
            },
            error: function(res){
                $('.loading').hide();
                toastr.error('Something went wrong!');
            }
        });
    }
    function checkSelectedBoxAvailable(){
        var has=false;
        $('.action_checkbox').each(function(key,val){
        if($(this).is(':checked')){
            has=true;
        }});
        return has;
    }

    function confirmDelete(user_id=''){
        if(user_id==''){
            var has=checkSelectedBoxAvailable();
            
            if(!has){
                swal({
                    title: "Users!",
                    text: "No users selected!",
                    icon: "error",
                    button: "OK",
                });
                return false;
            }
        }
        swal({
            title: "Are you sure?",
            text: "You want to delete these record!",
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

                if(user_id==''){
                    user_id=[];
                    $('.action_checkbox').each(function(key,val){
                        if($(this).is(':checked')){
                            user_id.push($(this).val());
                        }
                    });
                }
                deleteUser(user_id);
            }
            }); 
    }
    
</script>



@endsection