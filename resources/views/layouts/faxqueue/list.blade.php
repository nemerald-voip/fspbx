@extends('layouts.horizontal', ["page_title"=> "Fax Queue"])

@section('content')

<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Fax Queue</h4>
            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-12">
                            <div class="text-xl-end mt-xl-0 mt-2">
                                @if ($permissions['add_new'])
                                    <a href="{{ route('faxqueue.create') }}" class="btn btn-success mb-2 me-2">
                                        <i class="mdi mdi-plus-circle me-1"></i>
                                        Add New
                                    </a>
                                @endif
                                @if ($permissions['delete'])
                                    <a href="javascript:confirmDeleteAction('{{ route('faxqueue.destroy', ':id') }}');" id="deleteMultipleActionButton" class="btn btn-danger mb-2 me-2 disabled">
                                        Delete Selected
                                    </a>
                                @endif
                                {{-- <button type="button" class="btn btn-light mb-2">Export</button> --}}
                            </div>
                        </div><!-- end col-->
                    </div>
                    <div class="row mt-3">
                        <div class="col-4">
                            <label class="form-label">Showing {{ $faxqueues->firstItem() }} - {{ $faxqueues->lastItem() }} of {{ $faxqueues->total() }} results for Fax Queues</label>
                        </div>
                        <div class="col-8">
                            <div class="float-end">
                                {{ $faxqueues->links() }}
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-centered mb-0" id="faxqueue_list">
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
                                    <th>Date</th>
                                    <th>Caller ID Number</th>
                                    <th>Email Address</th>
                                    <th>Status</th>
                                    <th>Notify Date</th>
                                    <th>Retry Date</th>
                                    <th>Retry Count</th>
                                </tr>
                            </thead>
                            <tbody>

                                @foreach ($faxqueues as $key=>$faxqueue)
                                    <tr id="id{{ $faxqueue->fax_queue_uuid }}">
                                        <td>
                                            @if ($permissions['delete'])
                                                <div class="form-check">
                                                    <input type="checkbox" name="action_box[]" value="{{ $faxqueue->fax_queue_uuid }}" class="form-check-input action_checkbox">
                                                    <label class="form-check-label" >&nbsp;</label>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $faxqueue['fax_date']->format('D, M d, Y h:i:s A') }}
                                        </td>
                                        <td>
                                            {{ $faxqueue['fax_caller_id_number'] }}
                                        </td>
                                        <td>
                                            {{ $faxqueue['fax_email_address'] }}
                                        </td>
                                        <td>
                                            {{ $faxqueue['fax_status'] }}
                                        </td>
                                        <td>
                                            {{ $faxqueue['fax_notify_date']->format('D, M d, Y h:i:s A') }}
                                        </td>
                                        <td>
                                            {{ $faxqueue['fax_retry_date']->format('D, M d, Y h:i:s A') }}
                                        </td>
                                        <td>
                                            {{ $faxqueue['fax_retry_count'] }}
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
    </script>
@endpush
