@extends('layouts.partials.listing.layout', ["pageTitle"=> 'Sent Faxes', 'breadcrumbs' => [
    'Virtual Fax Machines' => 'faxes.index',
    'Fax Sent' => ''
]])

@section('pagination')
    @include('layouts.partials.listing.pagination', ['collection' => $files])
@endsection

@section('actionbar')
    @if ($permissions['delete'])
        <a href="javascript:confirmDeleteAction('{{ route('faxes.file.deleteFaxFile', ':id') }}');" id="deleteMultipleActionButton" class="btn btn-danger mb-2 me-2 disabled">
            Delete Selected
        </a>
    @endif
    {{-- <button type="button" class="btn btn-light mb-2">Export</button> --}}
@endsection

@section('searchbar')
    <form id="filterForm" method="GET" action="{{url()->current()}}?page=1" class="row gy-2 gx-2 align-items-center justify-content-xl-start justify-content-between">
        <div class="col-auto">
            <div class="d-flex align-items-center">
                <label for="status-select" class="me-2">Status</label>
                <select class="form-select" name="status" id="status-select">
                    @foreach ($statuses as $key => $status)
                        <option value="{{ $key }}" @if ($selectedStatus == $key) selected @endif>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>
@endsection

@section('table-head')
    <tr>
        <th style="width: 20px;">
            @if ($permissions['delete'])
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="selectallCheckbox">
                    <label class="form-check-label" for="selectallCheckbox">&nbsp;</label>
                </div>
            @endif
        </th>
        <th>From</th>
        <th>To</th>
        {{-- <th>View</th> --}}
        <th>Date</th>
        <th>Status</th>
        <th>Last attempt</th>
        <th>Retry Count</th>
        <th>Actions</th>
    </tr>
@endsection

@section('table-body')
    @foreach ($files as $key=>$file)
        <tr id="id{{ $file->fax_file_uuid  }}">
            <td>
                @if ($permissions['delete'])
                    <div class="form-check">
                        <input type="checkbox" name="action_box[]" value="{{ $file->fax_file_uuid }}" class="form-check-input action_checkbox">
                        <label class="form-check-label" >&nbsp;</label>
                    </div>
                @endif
            </td>
            <td>
                @if($file->fax_caller_id_name!='')
                    <span class="text-body fw-bold">
                                                {{ $file->fax_caller_id_name ?? ''}}
                                            </span>
                    <br>
                @endif
                <span class="text-body fw-bold ">
                                                {{ $file->fax_caller_id_number ?? '' }}
                                            </span>
            </td>
            <td>
                {{ $file->fax_destination }}
            </td>
            {{-- <td>
                {{ $file->fax_file_type }}
        </td> --}}
            <td>
                <span class="text-body text-nowrap">{{ $file->fax_date->format('D, M d, Y ')}}</span>
                <span class="text-body text-nowrap">{{ $file->fax_date->format('h:i:s A') }}</span>
            </td>
            <td>
                @if ($file->fax_status == "sent")
                    <h5><span class="badge bg-success">Sent</span></h5>
                @elseif($file->fax_status == "failed")
                    <h5><span class="badge bg-danger">Failed</span></h5>
                @else
                    <h5><span class="badge bg-info">{{ ucfirst($file->fax_status) }}</span></h5>
                @endif
            </td>
            <td>
                @if ($file->fax_retry_date)
                    <span class="text-body text-nowrap">{{ $file->fax_retry_date->format('D, M d, Y ') }}</span>
                    <span class="text-body text-nowrap">{{ $file->fax_retry_date->format('h:i:s A') }}</span>
                @endif
            </td>
            <td>
                {{ $file->fax_retry_count }}
            </td>
            <td>
                <div id="tooltip-container-actions">
                    @if ($file->faxQueue)
                        @if($file->faxQueue->fax_status == 'waiting' or $file->faxQueue->fax_status == 'trying')
                            <a href="{{ route('faxes.file.updateStatus', [$file->faxQueue->fax_queue_uuid]) }}" class="action-icon">
                                <i class="mdi mdi-cancel" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Cancel trying"></i>
                            </a>
                        @else
                            <a href="{{ route('faxes.file.updateStatus', [$file->faxQueue->fax_queue_uuid, 'waiting']) }}" class="action-icon">
                                <i class="mdi mdi-restart" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Retry"></i>
                            </a>
                        @endif
                    @endif
                    <a href="{{ route('downloadSentFaxFile', $file->fax_file_uuid ) }}" class="action-icon">
                        <i class="mdi mdi-download" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Download"></i>
                    </a>
                    @if ($permissions['delete'])
                        <a href="javascript:confirmDeleteAction('{{ route('faxes.file.deleteFaxFile', ':id') }}','{{ $file->fax_file_uuid }}');" class="action-icon">
                            <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i>
                        </a>
                    @endif
                </div>
            </td>
        </tr>
    @endforeach
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

        $('#status-select').on('change', function () {
            $('#filterForm').submit();
        })

        $('#formFilter').on('submit', function () {
            var location = window.location.protocol +"//" + window.location.host + window.location.pathname;
            location += '?page=1' + $('#filterForm').serialize();
            window.location.href = location;
        })
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
