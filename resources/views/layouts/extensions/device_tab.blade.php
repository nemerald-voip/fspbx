<div class="row">
    <div class="col-lg-12">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <div class="input-group">
                        <select name="device_uuid" class="form-select" id="device-select">
                            @foreach($devices as $device)
                                <option value="{{$device->device_uuid}}">{{$device->device_mac_address}} - {{$device->device_template}}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-info assign-device-btn" type="button">Assign device</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6 text-sm-end">
                <button class="btn btn-primary" type="button" id="add-new-device" data-bs-toggle="modal" data-bs-target="#createDeviceModal">Create new device</button>
            </div>
            <div class="error text-danger"></div>
            <div class="error text-danger" id="device_uuid_error"></div>
        </div>
        <h4 class="mb-2 mt-4">Assigned devices</h4>
        <table class="table">
            <tr>
                <th>MAC Address</th>
                <th>Template</th>
                <th>Actions</th>
            </tr>
            @if($extension->devices->count() == 0)
                @include('layouts.partials.listing.norecordsfound', ['colspan' => 3])
            @else
                @foreach($extension->devices as $device)
                    <tr>
                        <td>{{$device->device_mac_address}}</td>
                        <td>{{$device->device_template}}</td>
                        <td>
                            <div id="tooltip-container-actions">
                                <a class="action-icon" title="Edit" data-bs-toggle="modal" data-bs-target="#createDeviceModal" data-href="{{route('devices.edit', [$device->device_uuid])}}">
                                    <i class="mdi mdi-lead-pencil" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Edit device"></i>
                                </a>
                                <a class="action-icon" data-bs-toggle="modal" data-bs-target="#deleteModal" data-href="{{route('extensions.unassign-device', [$extension->extension_uuid, $device->pivot->device_line_uuid ])}}">
                                    <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Un-assign device"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            @endif
        </table>
    </div> <!-- end row-->
</div>
