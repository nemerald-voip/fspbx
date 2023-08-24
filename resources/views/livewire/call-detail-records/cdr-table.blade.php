    <!-- Start Content-->
    <div class="container-fluid">

        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    @if ($breadcrumbs)
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                @foreach ($breadcrumbs as $title => $path)
                                    @if ($path)
                                        <li class="breadcrumb-item"><a href="{{ route($path) }}">{{ $title }}</a>
                                        </li>
                                    @else
                                        <li class="breadcrumb-item active">{{ $title }}</li>
                                    @endif
                                @endforeach
                            </ol>
                        </div>
                    @endif
                    <h4 class="page-title">{{ $page_title }}</h4>
                </div>
            </div>
        </div>
        <livewire:users-table />
        <!-- end page title -->

        {{-- <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-xl-8">
                                <form id="filterForm" method="GET" action="{{url()->current()}}?page=1"
                                      class="row gy-2 gx-2 align-items-center justify-content-xl-start justify-content-between">
                                    <div class="col-auto">
                                        <label for="search" class="visually-hidden">Search</label>
                                        <input type="hidden" name="scope" value="{{ $selectedScope }}">
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
                                        <a href="javascript:confirmDeleteAction('{{ route('emailqueue.destroy', ':id') }}');"
                                           id="deleteMultipleActionButton" class="btn btn-danger btn-sm mb-2 me-2 disabled">
                                            Delete Selected
                                        </a>
                                    @endif
                                    <a href="{{ route('emailqueue.list', ['status' => $selectedStatus, 'search' => $searchString, 'scope' => (($selectedScope == 'local')?'global':'local')]) }}" class="btn btn-sm btn-light mb-2 me-2">
                                        Show {{ (($selectedScope == 'local')?'global':'local') }} queue
                                    </a>
                                </div>
                            </div><!-- end col-->
                        </div>
                        <div class="row mt-3">
                            <div class="col-4">
                                <label class="form-label">Showing {{ $emailQueues->firstItem() }} - {{ $emailQueues->lastItem() }} of {{ $emailQueues->total() }} results for Sent Emails</label>
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
                                    <th>
                                        @if (userCheckPermission('email_queue_delete'))
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="selectallCheckbox">
                                                <label class="form-check-label" for="selectallCheckbox">&nbsp;</label>
                                            </div>
                                        @endif
                                    </th>
                                    <th>Date Time</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Subject</th>
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
                                        <td>
                                            <span class="text-body text-nowrap">{{ $emailQueue->email_date->format('D, M d, Y ')}}</span>
                                            <span class="text-body text-nowrap">{{ $emailQueue->email_date->format('h:i:s A') }}</span>
                                        </td>

                                        <td>{{ $emailQueue->email_from }}</td>
                                        <td>
                                            @if (strlen($emailQueue->email_to) > 30)
                                                <p>{{ substr($emailQueue->email_to, 0, 30)}}</p>
                                                <a type="button" class="btn btn-link p-0" data-bs-toggle="modal" data-bs-target="#FullDetailModal" data-bs-whatever="{{ $emailQueue->email_to }}" data-bs-title="To">
                                                    Show more...
                                                </a>
                                            @else
                                                {{$emailQueue->email_to }}
                                            @endif
                                        </td>
                                        <td>

                                                {{$emailQueue->email_subject }}

                                        </td>
                                        <td>
                                            @if ($emailQueue->email_status == 'sent')
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

                                            
                                        </td>
                                    </tr>
                                @endforeach


                                </tbody>
                            </table>
                        </div>
                    </div> <!-- end card-body-->
                </div> <!-- end card-->
            </div> <!-- end col -->
        </div> --}}
        <!-- end row -->
    </div> <!-- container -->
