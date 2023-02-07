@extends('layouts.horizontal', ["page_title"=> "Email Queue"])

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
                            <div class="col-xl-4">
                                <label class="form-label">Showing {{ $emailQueues->total() ?? 0 }} results for email
                                    queues</label>
                            </div>
                            <div class="col-xl-8">
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

                        <div class="table-responsive">
                            <table class="table table-centered mb-0" id="voicemail_list">
                                <thead class="table-light">
                                <tr>
                                    <th style="width: 20px;">
                                        @if (userCheckPermission('email_queue_delete'))
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="selectallCheckbox">
                                                <label class="form-check-label" for="selectallCheckbox">&nbsp;</label>
                                            </div>
                                        @endif
                                    </th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th style="width: 425px;">Hostname</th>
                                    <th class="text-center">From</th>
                                    <th style="width: 125px;">To</th>
                                    <th style="width: 125px;">Subject</th>
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
                                            @if (userCheckPermission('email_queue_edit'))
                                                <a href="{{ route('faxes.edit',$emailQueue) }}" class="text-body fw-bold">
                                                    {{ $emailQueue->email_date }}
                                                </a>
                                            @else
                                                <span class="text-body fw-bold">
                                                    {{ $emailQueue->email_date }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $emailQueue->email_date }}
                                        </td>
                                        <td>
                                            $emailQueue->hostname
                                        </td>
                                        <td class="text-center">
                                            $emailQueue->email_from
                                        </td>
                                        <td>
                                            $emailQueue->email_to
                                        </td>
                                        <td>
                                            $emailQueue->email_subject
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