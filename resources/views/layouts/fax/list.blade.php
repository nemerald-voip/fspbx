@extends('layouts.horizontal', ["page_title"=> "faxes"])

@section('content')

<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Fax</h4>
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
                            <label class="form-label">Showing {{ $faxes->count() ?? 0 }}  results for faxes</label>
                        </div>
                        <div class="col-xl-8">
                            <div class="text-xl-end mt-xl-0 mt-2">
                                @if ($permissions['add_new'])
                                    <a href="{{ route('faxes.create') }}" class="btn btn-success mb-2 me-2">
                                        <i class="mdi mdi-plus-circle me-1"></i>
                                        Add New
                                    </a>
                                @endif
                                @if ($permissions['delete'])
                                    <a href="javascript:confirmDeleteAction('{{ route('faxes.destroy', ':id') }}');" id="deleteMultipleActionButton" class="btn btn-danger mb-2 me-2 disabled">
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
                                    <th>Name</th>
                                    <th>Extension</th>
                                    <th style="width: 425px;">Email</th>
                                    <th class="text-center">Tools</th>
                                    <th style="width: 125px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>

                                @foreach ($faxes as $key=>$fax)
                                    <tr id="id{{ $fax->fax_uuid  }}">
                                        <td>
                                            @if ($permissions['delete'])
                                                <div class="form-check">
                                                    <input type="checkbox" name="action_box[]" value="{{ $fax->fax_uuid }}" class="form-check-input action_checkbox">
                                                    <label class="form-check-label" >&nbsp;</label>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($permissions['edit']) 
                                                <a href="{{ route('faxes.edit',$fax) }}" class="text-body fw-bold">
                                                    {{ $fax->fax_name }}
                                                </a>                                             
                                            @else
                                                <span class="text-body fw-bold">
                                                    {{ $fax->fax_name }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $fax->fax_extension }} 
                                        </td>
                                        <td>
                                            @foreach ($fax->fax_email as $email)
                                            <span class="m-1 mt-0 mb-2 btn btn-outline-primary rounded-pill btn-sm emailButton">{{ $email }}</span>
                                            @endforeach
                                        </td>

                                        <td  class="text-center">

                                            <div id="tooltip-container-actions text-center">
                                                @if ($permissions['fax_send'])
                                                    <a href="{{ url('faxes/new/').'/'.$fax->fax_uuid }}" class="action-icon" title="New"> 
                                                        <i class="mdi mdi-plus" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="New"></i>
                                                    </a>
                                                    @endif
                                                    
                                                    @if ($permissions['fax_inbox_view'])
                                                    <a href="{{ url('faxes/inbox/').'/'.$fax->fax_uuid }}" class="action-icon"> 
                                                        <i class="mdi mdi-inbox" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Inbox"></i>
                                                    </a>
                                                    @endif
                                                    @if ($permissions['fax_sent_view'])
                                                    <a href="{{ url('faxes/sent/').'/'.$fax->fax_uuid }}" class="action-icon" title="Sent"> 
                                                        <i class="mdi mdi-send-check" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Sent"></i>
                                                    </a>
                                                    @endif
                                                    @if ($permissions['fax_log_view'])
                                                    <a href="{{ url('faxes/log/').'/'.$fax->fax_uuid }}" class="action-icon" title="Logs"> 
                                                        <i class="mdi mdi-fax" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Logs"></i>
                                                    </a>
                                                    @endif
                                                    {{-- @if ($permissions['fax_active_view'])
                                                    <a href="" class="action-icon" title="Active"> 
                                                        <i class="mdi mdi-check" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Active"></i>
                                                    </a>
                                                    @endif --}}
                                            </div>


                                            {{-- <i class="mdi mdi-plus"></i>
                                            <i class="mdi mdi-inbox"></i>
                                            <i class="mdi mdi-send-check"></i>
                                            <i class="mdi mdi-fax"></i> --}}
                                        </td>
                                        <td>
                                            {{-- Action Buttons --}}
                                            <div id="tooltip-container-actions">
                                                @if ($permissions['edit'])
                                                    <a href="{{ route('faxes.edit',$fax) }}" class="action-icon" title="Edit"> 
                                                        <i class="mdi mdi-lead-pencil" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Edit"></i>
                                                    </a>
                                                @endif

                                                @if ($permissions['delete'])
                                                    <a href="javascript:confirmDeleteAction('{{ route('faxes.destroy', ':id') }}','{{ $fax->fax_uuid }}');" class="action-icon"> 
                                                        <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i>
                                                    </a>
                                                @endif
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