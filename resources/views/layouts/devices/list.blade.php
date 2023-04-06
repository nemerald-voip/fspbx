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
        <th>Mac Address</th>
        <th>Name</th>
        <th>Template</th>
        <th>Profile</th>
        <th>Status</th>
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
                    {{ $device->device_uuid }}
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

                </td>
                <td>

                </td>
            </tr>
        @endforeach
    @endif
@endsection

@push('scripts')

@endpush
