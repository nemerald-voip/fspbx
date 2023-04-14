@extends('layouts.partials.listing.layout', ["pageTitle"=> 'Devices'])

@section('pagination')
    @include('layouts.partials.listing.pagination', ['collection' => $devices])
@endsection
{{--
@section('actionbar')

@endsection

@section('searchbar')

@endsection
--}}
@section('table-head')
    <tr>
        <th>MAC Address</th>
        <th>Name</th>
        <th>Template</th>
        <th>Profile</th>
        <th>Assigned to</th>
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
                    {{ $device->device_profile_uuid }}
                </td>
                <td>
                    @if($device->lines()->first())
                        {{ $device->lines()->first()->display_name }}
                    @endif
                </td>
                <td>
                    <a href="javascript:confirmDeleteAction('{{ route('extensions.destroy', ':id') }}','{{ $device->device_uuid }}');" class="action-icon">
                        <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i>
                    </a>
                </td>
            </tr>
        @endforeach
    @endif
@endsection

@push('scripts')

@endpush
