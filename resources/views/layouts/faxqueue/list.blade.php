@extends('layouts.partials.listing.layout', ["pageTitle"=> 'Fax Queue'])

@section('pagination')
    @include('layouts.partials.listing.pagination', ['collection' => $faxQueues])
@endsection

@section('actionbar')
    @if ($permissions['delete'])
        <a href="javascript:confirmDeleteAction('{{ route('faxQueue.destroy', ':id') }}');" id="deleteMultipleActionButton" class="btn btn-danger me-2 disabled">
            Delete Selected
        </a>
    @endif
    {{-- <button type="button" class="btn btn-light mb-2">Export</button> --}}
    <a href="{{ route('faxQueue.list', ['scope' => (($selectedScope == 'local')?'global':'local')]) }}" class="btn btn-light">
        Show {{ (($selectedScope == 'local')?'global':'local') }} queue
    </a>
@endsection

@section('searchbar')
    <form id="filterForm" method="GET" action="{{url()->current()}}?page=1" class="row gy-2 gx-2 align-items-center justify-content-xl-start justify-content-between">
        <div class="col-auto">
            <label for="search" class="visually-hidden">Search</label>
            <div class="input-group input-group-merge">
                <input type="search" class="form-control" name="search" id="search" value="{{ $searchString }}" placeholder="Search...">
                <input type="button" class="btn btn-light" name="clear" id="clearSearch" value="Clear" />
            </div>
        </div>
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
        <input type="hidden" name="scope" value="{{ $selectedScope }}" />
        <div class="d-none"><input type="submit" name="submit" value="Ok" /></div>
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
        @if($selectedScope == 'global')
            <th>Domain</th>
        @endif
        <th>Date</th>
        <th>Caller ID Number</th>
        <th>Email Address</th>
        <th>Status</th>
        <th>Notify Date</th>
        <th>Retry Date</th>
        <th>Retry Count</th>
        <th>Action</th>
    </tr>
@endsection

@section('table-body')
    @if($faxQueues->count() == 0)
        @include('layouts.partials.listing.norecordsfound', ['colspan' => (($selectedScope == 'global') ? 10 : 9) ])
    @else
        @foreach ($faxQueues as $key => $faxQueue)
            <tr id="id{{ $faxQueue->fax_queue_uuid }}">
                <td>
                    @if ($permissions['delete'])
                        <div class="form-check">
                            <input type="checkbox" name="action_box[]" value="{{ $faxQueue->fax_queue_uuid }}" class="form-check-input action_checkbox">
                            <label class="form-check-label" >&nbsp;</label>
                        </div>
                    @endif
                </td>
                @if($selectedScope == 'global')
                    <th>{{ $faxQueue->domain_name }}</th>
                @endif
                <td>
                    {{-- {{ $faxQueue->fax_date->format('D, M d, Y h:i:s A') }} --}}
                    <span class="text-body text-nowrap">{{ $faxQueue->fax_date->format('D, M d, Y ')}}</span>
                    <span class="text-body text-nowrap">{{ $faxQueue->fax_date->format('h:i:s A') }}</span>
                </td>
                <td class="text-nowrap">
                    {{ phone($faxQueue->fax_caller_id_number, "US", $national_phone_number_format) }}</td>
                <td>
                    {{ $faxQueue->fax_email_address }}
                </td>
                <td>
                    @if ($faxQueue->fax_status == "sent")
                        <h5><span class="badge bg-success">Sent</span></h5>
                    @elseif($faxQueue->fax_status == "failed")
                        <h5><span class="badge bg-danger">Failed</span></h5>
                    @else
                        <h5><span class="badge bg-info">{{ ucfirst($faxQueue->fax_status) }}</span></h5>
                    @endif
                </td>
                <td>
                    {{-- {{ $faxQueue->fax_notify_date->format('D, M d, Y h:i:s A') }} --}}
                    <span class="text-body text-nowrap">{{ $faxQueue->fax_notify_date->format('D, M d, Y ')}}</span>
                    <span class="text-body text-nowrap">{{ $faxQueue->fax_notify_date->format('h:i:s A') }}</span>
                </td>
                <td>
                    {{-- {{ $faxQueue->fax_retry_date->format('D, M d, Y h:i:s A') }} --}}
                    <span class="text-body text-nowrap">{{ $faxQueue->fax_retry_date->format('D, M d, Y ')}}</span>
                    <span class="text-body text-nowrap">{{ $faxQueue->fax_retry_date->format('h:i:s A') }}</span>
                </td>
                <td>
                    {{ $faxQueue->fax_retry_count }}
                </td>
                <td>
                    @if($faxQueue->fax_status == 'waiting' or $faxQueue->fax_status == 'trying')
                        <a href="{{ route('faxQueue.updateStatus', [$faxQueue->fax_queue_uuid]) }}">
                            <button type="button" class="btn btn-light mb-2">Cancel</button>
                        </a>
                    @else
                        <a href="{{ route('faxQueue.updateStatus', [$faxQueue->fax_queue_uuid, 'waiting']) }}">
                            <button type="button" class="btn btn-light mb-2">Retry</button>
                        </a>
                    @endif
                </td>
            </tr>
        @endforeach
    @endif
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

            $('#clearSearch').on('click', function () {
                $('#search').val('');
                var location = window.location.protocol +"//" + window.location.host + window.location.pathname;
                location += '?page=1&' + $('#filterForm').serialize();
                window.location.href = location;
            })

            $('#status-select').on('change', function () {
                $('#filterForm').submit();
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
    </script>
@endpush
