@extends('layouts.horizontal', ["page_title"=> "Fax Inbox"])

@section('content')

<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('faxes.index') }}">Virtual Fax Machines</a></li>
                        <li class="breadcrumb-item active">Fax Inbox</li>
                    </ol>
                </div>
                <h4 class="page-title">Fax Inbox</h4>
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
                            <label class="form-label">Showing {{ $files->count() ?? 0 }}  results for incoming faxes</label>
                        </div>
                        <div class="col-xl-8">
                            <div class="text-xl-end mt-xl-0 mt-2">
                                
                                @if ($permissions['delete'])
                                    <a href="javascript:confirmDeleteAction('{{ route('faxes.file.deleteReceivedFax', ':id') }}');" id="deleteMultipleActionButton" class="btn btn-danger mb-2 me-2 disabled">
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
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Date</th>
                                    <th style="width: 125px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>

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
                                            <span class="text-body fw-bold">
                                                {{ $file->fax_caller_id_name ?? ''}}
                                            </span>
                                            <br>
                                            <span class="text-body fw-bold ">
                                                {{ $file->fax_caller_id_number ?? '' }}
                                            </span>
                                        </td>
                                        {{-- <td>{{ $file->fax_file_type }}</td> --}}

                                        <td>
                                            {{ $file->fax_destination ?? '' }}
                                        </td>
                                        
                                        <td>{{ $file->fax_date }}</td>

                                        <td>
                                            <div id="tooltip-container-actions">
                                                <a href="{{ route('downloadInboxFaxFile', $file->fax_file_uuid ) }}" class="action-icon">
                                                    <i class="mdi mdi-download" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Download"></i>
                                                </a>

                                                @if ($permissions['delete'])
                                                    <a href="javascript:confirmDeleteAction('{{ route('faxes.file.deleteReceivedFax', ':id') }}','{{ $file->fax_file_uuid }}');" class="action-icon">
                                                        <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i>
                                                    </a>
                                                @endif
                                            </div>
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