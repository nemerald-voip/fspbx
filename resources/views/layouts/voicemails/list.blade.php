@extends('layouts.horizontal', ["page_title"=> "Voicemails"])

@section('content')

<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Voicemails</h4>
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
                            <label class="form-label">Showing {{ $voicemails->count() ?? 0 }}  results for Voicemails</label>
                        </div>
                        <div class="col-xl-8">
                            <div class="text-xl-end mt-xl-0 mt-2">
                                @if ($permissions['add_new'])
                                    <a href="{{ route('users.create') }}" class="btn btn-success mb-2 me-2">
                                        <i class="mdi mdi-plus-circle me-1"></i>
                                        Add New
                                    </a>
                                @endif
                                @if ($permissions['delete'])
                                    <a href="javascript:confirmDeleteAction('{{ route('users.destroy', ':id') }}');" id="deleteMultipleActionButton" class="btn btn-danger mb-2 me-2 disabled">
                                        Delete Selected
                                    </a>
                                @endif
                                {{-- <button type="button" class="btn btn-light mb-2">Export</button> --}}
                            </div>
                        </div><!-- end col-->
                    </div>

                    <div class="table-responsive">
                        <table class="table table-centered mb-0" id="voicemail_list">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 20px;">
                                        @if ($permissions['delete'])
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="selectallCheckbox">
                                                <label class="form-check-label" for="selectallCheckbox">&nbsp;</label>
                                            </div>
                                        @endif
                                    </th>
                                    <th>Voicemail ID</th>
                                    <th>Mail to</th>
                                    <th>Attached</th>
                                    <th>Keep local</th>
                                    <th>Tools</th>
                                    <th></th>
                                    <th>Enabled</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>

                                @foreach ($voicemails as $key=>$voicemail)
                                    <tr id="id{{ $voicemail->voicemail_uuid  }}">
                                        <td>
                                            @if ($permissions['delete'])
                                                <div class="form-check">
                                                    <input type="checkbox" name="action_box[]" value="{{ $voicemail->voicemail_uuid }}" class="form-check-input action_checkbox">
                                                    <label class="form-check-label" >&nbsp;</label>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($permissions['edit']) 
                                                <a href="{{ route('users.edit',$voicemail) }}" class="text-body fw-bold">
                                                    {{ $voicemail->voicemail_id }}
                                                </a>                                             
                                            @else
                                                <span class="text-body fw-bold">
                                                    {{ $voicemail->voicemail_id }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $voicemail['voicemail_mail_to'] }} 
                                        </td>
                                        
                                        <td>
                                            @if($voicemail['voicemail_file']=='attach')
                                            True
                                            @else
                                            False 
                                            @endif
                                        </td>
                                        <td>
                                            {{ ucfirst($voicemail['voicemail_local_after_email']) }}
                                        </td>
                                        <td>
                                            {{-- {{ $voicemail['voicemail_local_after_email'] }} --}}
                                        </td>
                                        <td>
                                        </td>
                                        <td>
                                            @if ($voicemail['voicemail_enabled']=='true') 
                                                <h5><span class="badge bg-success"></i>Enabled</span></h5>
                                            @else 
                                                <h5><span class="badge bg-warning">Disabled</span></h5>
                                            @endif
                                        </td>
                                        
                                        <td>
                                            {{ $voicemail['voicemail_description'] }}
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
@endsection


@push('scripts')
<script>
    $(document).ready(function() {

        localStorage.removeItem('activeTab');

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

    function confirmPasswordResetAction(user_email){
        $('#confirmPasswordResetModal').data("user_email",user_email).modal('show');

    }

    function performConfirmedPasswordResetAction(){
        var user_email = $("#confirmPasswordResetModal").data("user_email");
        $('#confirmPasswordResetModal').modal('hide');

        $.ajax({
            type: 'POST',
            url: "{{ url('password/email') }}",
            cache: false,
            data: {
                email:user_email
                }
            })
            .done(function(response) {

                if (response.error){
                    $.NotificationApp.send("Warning",response.message,"top-right","#ff5b5b","error");

                } else {
                    $.NotificationApp.send("Success",response.message,"top-right","#10c469","success");
                    //$(this).closest('tr').fadeOut("fast");
                }
            })
            .fail(function (response){

                $.NotificationApp.send("Warning",response,"top-right","#ff5b5b","error");
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

    
    
</script>
@endpush