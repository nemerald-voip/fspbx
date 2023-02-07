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
                    <div class="table-responsive">
                        <table class="table table-centered mb-0" id="faxqueue_list">
                            <thead class="table-light">
                                <tr>
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
