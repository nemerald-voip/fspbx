@extends('layouts.partials.listing.layout', ["pageTitle"=> 'Devices'])

@section('pagination')
    @include('layouts.partials.listing.pagination', ['collection' => $devices])
@endsection

@section('actionbar')
    <a href="{{ route('devices.create') }}" class="btn btn-sm btn-success mb-2 me-2">
        <i class="mdi mdi-plus-circle me-1"></i> Add New
    </a>
    <a href="{{ route('devices.index', ['scope' => (($selectedScope == 'local')?'global':'local')]) }}" class="btn btn-sm btn-light mb-2 me-2">
        Show {{ (($selectedScope == 'local')?'global':'local') }} devices
    </a>
    @if($permissions['device_restart'])
        <a href="#" class="btn btn-danger btn-restart-selected-devices btn-sm mb-2 me-2 disabled">
            Restart selected devices
        </a>
        <a href="#" class="btn btn-danger btn-restart-all-devices btn-sm mb-2 me-2">
            Restart all {{$devicesToRestartCount}} devices
        </a>
    @endif
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
        @if ($permissions['device_restart'])
            <th>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="selectallCheckbox">
                    <label class="form-check-label" for="selectallCheckbox">&nbsp;</label>
                </div>
            </th>
        @endif
        @if($selectedScope == 'global')
            <th>Domain</th>
        @endif
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
        @include('layouts.partials.listing.norecordsfound', ['colspan' => (($selectedScope == 'global') ? 8 : 7) ])
    @else
        @foreach ($devices as $key => $device)
            <tr id="id{{ $device->device_uuid }}">
                <td>
                    @if ($permissions['device_restart'] && $device->lines()->first() && $device->lines()->first()->extension())
                        <div class="form-check">
                            <input type="checkbox" name="action_box[]" value="{{ $device->device_uuid }}"
                                   data-restart-url="{{route('extensions.send-event-notify', $device->lines()->first()->extension()->extension_uuid)}}"
                                   class="form-check-input action_checkbox">
                            <label class="form-check-label" >&nbsp;</label>
                        </div>
                    @endif
                </td>
                @if($selectedScope == 'global')
                    <th>{{ $device->domain_name }}</th>
                @endif
                <td>
                    {{ $device->device_address }}
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

                    @if($device->lines()->first() && $device->lines()->first()->extension())
                        @if ($permissions['device_restart'])
                            <a href="{{route('extensions.send-event-notify', ':id')}}" data-extension-id="{{$device->lines()->first()->extension()->extension_uuid}}" class="action-icon btn-restart-device">
                                <i class="mdi mdi-restart" data-bs-container="#tooltip-container-actions"
                                   data-bs-toggle="tooltip" data-bs-placement="bottom" title="Restart Devices"></i>
                            </a>
                        @endif
                    @endif

                    <a href="{{ route('devices.destroy', $device->device_uuid) }}" data-id="{{$device->device_uuid}}" class="action-icon btn-delete-device">
                        <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i>
                    </a>
                </td>
            </tr>
        @endforeach
    @endif
@endsection

@push('scripts')
    @vite(['resources/js/ui/page.devices.js'])






@endpush
