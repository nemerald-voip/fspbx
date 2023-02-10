@extends('layouts.horizontal', ["page_title"=> "Email Queues"])

@section('content')

    <!-- Start Content-->
    <div class="container-fluid">

        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">Email Queue</h4>
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
                                <form id="filterForm" method="GET" action="{{url()->current()}}?page=1"
                                      class="row gy-2 gx-2 align-items-center justify-content-xl-start justify-content-between">
                                    <div class="col-auto">
                                        <label for="search" class="visually-hidden">Search</label>
                                        <input type="search" class="form-control" name="search" id="search"
                                               value="{{ $searchString }}" placeholder="Search...">
                                    </div>
                                    <div class="col-auto">
                                        <div class="d-flex align-items-center">
                                            <label for="status-select" class="me-2">Status</label>
                                            <select class="form-select" name="status" id="status-select">
                                                @foreach ($statuses as $key => $status)
                                                    <option value="{{ $key }}"
                                                            @if ($selectedStatus == $key) selected @endif>{{ $status }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-auto">

                                    </div>
                                </form>
                            </div>
                            <div class="col-xl-4">
                                <div class="text-xl-end mt-xl-0 mt-2">
                                    @if (userCheckPermission('email_queue_delete'))
                                        <a href="javascript:confirmDeleteAction('{{ route('emailqueues.destroy', ':id') }}');"
                                           id="deleteMultipleActionButton" class="btn btn-danger mb-2 me-2 disabled">
                                            Delete Selected
                                        </a>
                                    @endif
                                    {{-- <button type="button" class="btn btn-light mb-2">Export</button> --}}
                                </div>
                            </div><!-- end col-->
                        </div>
                        <div class="col-xl-4">
                            <label class="form-label">Showing {{ $emailQueues->firstItem() }}
                                - {{ $emailQueues->lastItem() }} of {{ $emailQueues->total() }} results for
                                Extensions</label>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-centered mb-0" id="voicemail_list">
                                <thead class="table-light">
                                <tr>
                                    <th style="width: 10px;">
                                        @if (userCheckPermission('email_queue_delete'))
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="selectallCheckbox">
                                                <label class="form-check-label" for="selectallCheckbox">&nbsp;</label>
                                            </div>
                                        @endif
                                    </th>
                                    <th>Date Time</th>
                                    <th>Hostname</th>
                                    <th class="text-center">From</th>
                                    <th>To</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Retry</th>
                                    <th>After Email</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($emailQueues as $emailQueue)
                                    <tr id="id{{ $emailQueue->email_queue_uuid  }}">
                                        <td>
                                            @if (userCheckPermission('email_queue_delete'))
                                                <div class="form-check">
                                                    <input type="checkbox" name="action_box[]"
                                                           value="{{ $emailQueue->email_queue_uuid }}"
                                                           class="form-check-input action_checkbox">
                                                    <label class="form-check-label">&nbsp;</label>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="text-body fw-bold">
                                                {{ $emailQueue->email_date }}
                                                {{ \Carbon\Carbon::createFromTimestamp($emailQueue->email_date)->setTimezone(get_local_time_zone(session('domain_uuid')))->toDayDateTimeString() }}
                                            </span>
                                        </td>
                                        <td>{{ $emailQueue->hostname }}</td>
                                        <td class="text-center">{{ $emailQueue->email_from }}</td>
                                        <td>{{ $emailQueue->email_to }}</td>
                                        <td>{{ $emailQueue->email_subject }}</td>
                                        <td>
                                            @if ($emailQueue->email_status == "sent")
                                                <h5><span class="badge bg-success">Sent</span></h5>
                                            @elseif($emailQueue->email_status == "failed")
                                                <h5><span class="badge bg-danger">Failed</span></h5>
                                            @elseif($emailQueue->email_status == "waiting")
                                                <h5><span class="badge bg-primary">Waiting</span></h5>
                                            @else
                                                <h5>
                                                    <span class="badge bg-info">{{ ucfirst($emailQueue->email_status) }}</span>
                                                </h5>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $emailQueue->email_retry_count }}
                                        </td>
                                        <td>{{ $emailQueue->email_action_after }}</td>
                                        <td>
                                            @if (userCheckPermission('email_queue_edit'))
                                                @if($emailQueue->email_status == 'waiting')
                                                    <a href="{{ route('emailqueues.updateStatus', [$emailQueue->email_queue_uuid]) }}">
                                                        <button type="button" class="btn btn-light mb-2">Cancel</button>
                                                    </a>
                                                @else
                                                    <a href="{{ route('emailqueues.updateStatus', [$emailQueue->email_queue_uuid, 'waiting']) }}">
                                                        <button type="button" class="btn btn-light mb-2">Retry</button>
                                                    </a>
                                                @endif
                                            @else
                                                @if($emailQueue->email_status == 'waiting')
                                                    <button type="button" class="btn btn-light mb-2">Cancel</button>
                                                @else
                                                    <button type="button" class="btn btn-light mb-2">Retry</button>
                                                @endif
                                            @endif
                                            @if (userCheckPermission('email_queue_delete'))
                                                <a href="javascript:confirmDeleteAction('{{ route('emailqueues.destroy', ':id') }}','{{ $emailQueue->email_queue_uuid }}');"
                                                   class="action-icon">
                                                    <i class="mdi mdi-delete"
                                                       data-bs-container="#tooltip-container-actions"
                                                       data-bs-toggle="tooltip" data-bs-placement="bottom"
                                                       title="Delete"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach


                                </tbody>
                            </table>
                        </div>
                    </div> <!-- end card-body-->
                </div> <!-- end card-->
                {{ $emailQueues->links() }}
            </div> <!-- end col -->
        </div>
        <!-- end row -->

    </div> <!-- container -->
@endsection


@push('scripts')
    <script>
        $(document).ready(function () {

            localStorage.removeItem('activeTab');

            $('#selectallCheckbox').on('change', function () {
                if ($(this).is(':checked')) {
                    $('.action_checkbox').prop('checked', true);
                } else {
                    $('.action_checkbox').prop('checked', false);
                }
            });

            $('.action_checkbox').on('change', function () {
                if (!$(this).is(':checked')) {
                    $('#selectallCheckbox').prop('checked', false);
                } else {
                    if (checkAllbox()) {
                        $('#selectallCheckbox').prop('checked', true);
                    }
                }
            });

            $('#status-select').on('change', function () {
                $('#filterForm').submit();
            })

            $('#formFilter').on('submit', function () {
                var location = window.location.protocol + "//" + window.location.host + window.location.pathname;
                location += '?page=1' + $('#filterForm').serialize();
                window.location.href = location;
            })
        });

        function checkAllbox() {
            var checked = true;
            $('.action_checkbox').each(function (key, val) {
                if (!$(this).is(':checked')) {
                    checked = false;
                }
            });
            return checked;
        }

        function checkSelectedBoxAvailable() {
            var has = false;
            $('.action_checkbox').each(function (key, val) {
                if ($(this).is(':checked')) {
                    has = true;
                }
            });
            return has;
        }


    </script>
@endpush