@extends('layouts.partials.listing.layout', ["pageTitle"=> 'Devices'])

@section('pagination')
    @include('layouts.partials.listing.pagination', ['collection' => $devices])
@endsection

@section('actionbar')
    <a href="{{ route('devices.create') }}" class="btn btn-success mb-2 me-2">
        <i class="mdi mdi-plus-circle me-1"></i> Add New
    </a>
@endsection

@section('searchbar')
    <form id="filterForm" method="GET" action="{{url()->current()}}?page=1" class="row gy-2 gx-2 align-items-center justify-content-xl-start justify-content-between">
        <div class="col-auto">
            <label for="search" class="visually-hidden">Search</label>
            <div class="input-group input-group-merge">
                <input type="search" class="form-control" name="search" id="search" value="{{ $searchString }}" placeholder="Search..." />
                <input type="button" class="btn btn-light" name="clear" id="clearSearch" value="Clear" />
            </div>
        </div>
        <div class="d-none"><input type="submit" name="submit" value="Ok" /></div>
    </form>
@endsection

@section('table-head')
    <tr>
        <th>MAC Address</th>
        <th>Name</th>
        <th>Template</th>
        <th>Profile</th>
        <th>Assigned extension</th>
        <th>Action</th>
    </tr>
@endsection

@section('table-body')
    @if($devices->count() == 0)
        @include('layouts.partials.listing.norecordsfound', ['colspan' => 6 ])
    @else
        @foreach ($devices as $key => $device)
            <tr id="id{{ $device->device_uuid }}">
                <td>
                    {{ $device->device_mac_address }}
                </td>
                <td>
                    {{ $device->device_label }}
                </td>
                <td>
                    {{ $device->device_template }}
                </td>
                <td>
                    @if($device->profile()->first())
                        {{ $device->profile()->first()->device_profile_name }}
                    @endif
                </td>
                <td>
                    @if($device->lines()->first() && $device->lines()->first()->extension())
                        <a href="{{ route('extensions.edit',$device->lines()->first()->extension()) }}">
                            {{ $device->lines()->first()->extension()->extension }}
                        </a>
                    @endif
                </td>
                <td id="tooltip-container-actions">
                    <a href="{{ route('devices.edit',$device) }}" class="action-icon" title="Edit">
                        <i class="mdi mdi-lead-pencil" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Edit device"></i>
                    </a>

                    <a href="javascript:confirmDeleteAction('{{ route('extensions.destroy', ':id') }}','{{ $device->device_uuid }}');" class="action-icon">
                        <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i>
                    </a>
                </td>
            </tr>
        @endforeach
    @endif
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#clearSearch').on('click', function () {
                $('#search').val('');
                var location = window.location.protocol + "//" + window.location.host + window.location.pathname;
                location += '?page=1';
                window.location.href = location;
            })
        });
    </script>
@endpush
