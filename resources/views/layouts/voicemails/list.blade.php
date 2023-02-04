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
                        <div class="col-xl-8">
                            <div class="text-xl-end mt-xl-0 mt-2">
                                @if ($permissions['add_new'])
                                    <a href="{{ route('voicemails.create') }}" class="btn btn-success mb-2 me-2">
                                        <i class="mdi mdi-plus-circle me-1"></i>
                                        Add New
                                    </a>
                                @endif
                                @if ($permissions['delete'])
                                    <a href="javascript:confirmDeleteAction('{{ route('voicemails.destroy', ':id') }}');" id="deleteMultipleActionButton" class="btn btn-danger mb-2 me-2 disabled">
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
                                    <th>Email Address</th>
                                    <th>Messages</th>
                                    <th>Enabled</th>
                                    <th>Description</th>
                                    <th style="width: 125px;">Action</th>
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
                                                <a href="{{ route('voicemails.edit',$voicemail) }}" class="text-body fw-bold">
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
                                            @if ($permissions['voicemail_message_view']) 
                                                <a href="{{ route('voicemails.messages.index',$voicemail) }}" class="text-body fw-bold">
                                                    Show Messages ({{ $voicemail->messages()->count() }})
                                                </a>                                             
                                            @else
                                                {{ $voicemail->messages()->count() }}
                                            @endif
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


                                        <td>
                                            {{-- Action Buttons --}}
                                            <div id="tooltip-container-actions">
                                                @if ($permissions['edit'])
                                                    <a href="{{ route('voicemails.edit',$voicemail) }}" class="action-icon" title="Edit"> 
                                                        <i class="mdi mdi-lead-pencil" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Edit voicemail"></i>
                                                    </a>
                                                @endif

                                                @if ($permissions['delete'])
                                                    <a href="javascript:confirmDeleteAction('{{ route('voicemails.destroy', ':id') }}','{{ $voicemail->voicemail_uuid }}');" class="action-icon"> 
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