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
                                        <a href="{{ route('emailqueues.list', ['scope' => (($selectedScope == 'local')?'global':'local')]) }}" class="btn btn-light mb-2 me-2">
                                            Show {{ (($selectedScope == 'local')?'global':'local') }} queue
                                        </a>
                                    {{-- <button type="button" class="btn btn-light mb-2">Export</button> --}}
                                </div>
                            </div><!-- end col-->
                        </div>
                        <div class="row mt-3">
                            <div class="col-4">
                                <label class="form-label">Showing {{ $emailQueues->firstItem() }} - {{ $emailQueues->lastItem() }} of {{ $emailQueues->total() }} results for Email Queues</label>
                            </div>
                            <div class="col-8">
                                <div class="float-end">
                                    {{ $emailQueues->appends(request()->except('page'))->links() }}
                                </div>
                            </div>
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
                                    <th style="width: 200px">Date Time</th>
                                    <th>Hostname</th>
                                    <th class="text-center">From</th>
                                    <th>To</th>
                                    <th style="width: 30px">Subject</th>
                                    <th>Status</th>
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
                                        <td style="width: 200px">
                                            {{ \Carbon\Carbon::parse($emailQueue->email_date)->setTimezone(get_local_time_zone(session('domain_uuid')))->toDayDateTimeString() }}
                                        </td>
                                        <td>{{ $emailQueue->hostname }}</td>
                                        <td class="text-center">{{ $emailQueue->email_from }}</td>
                                        <td>
                                            <span data-toggle="modal" data-target="#exampleModal" data-whatever="{{ $emailQueue->email_to }}">
                                                <a href="#">Show data</a>
                                            </span>
                                        </td>
                                        <td style="width: 30px">{{ strlen($emailQueue->email_subject) > 50 ? substr($emailQueue->email_subject, 0, 50) . '...' : $emailQueue->email_subject }}</td>
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
                                            @if (userCheckPermission('email_queue_edit'))
                                                @if($emailQueue->email_status == 'waiting')
                                                    <a href="{{ route('emailqueues.updateStatus', [$emailQueue->email_queue_uuid]) }}">
                                                        <i class="mdi mdi-cancel"
                                                           data-bs-container="#tooltip-container-actions"
                                                           data-bs-toggle="tooltip" data-bs-placement="bottom"
                                                           title="Cancel"></i>
                                                    </a>
                                                @else
                                                    <a href="{{ route('emailqueues.updateStatus', [$emailQueue->email_queue_uuid, 'waiting']) }}">
                                                        <i class="mdi mdi-repeat"
                                                           data-bs-container="#tooltip-container-actions"
                                                           data-bs-toggle="tooltip" data-bs-placement="bottom"
                                                           title="Retry"></i>
                                                    </a>
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
            </div> <!-- end col -->
        </div>
        <!-- end row -->

    </div> <!-- container -->
    <!-- Modal -->
    <div class="modal fade" id="exampleModal"
         tabindex="-1" role="dialog"
         aria-labelledby="exampleModalLabel"
         aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"
                        id="exampleModalLabel">
                        Modal title
                    </h5>
                    <button type="button" class="close"
                            data-dismiss="modal"
                            aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p> hi</p>
                </div>
                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            data-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
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

            $('#exampleModal').on('show.bs.modal',
                function (event) {

                    // Button that triggered the modal
                    var li = $(event.relatedTarget)

                    // Extract info from data attributes
                    var recipient = li.data('whatever')

                    // Updating the modal content using
                    // jQuery query selectors
                    var modal = $(this)

                    modal.find('.modal-title')
                        .text('New message to ' + recipient)

                    modal.find('.modal-body p')
                        .text('Welcome to ' + recipient)
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