<div class="row">
    <div class="col-lg-12">
        <h4 class="mt-2">Attached Devices</h4>
        <div class="card" id="extensionForm" action="" >
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="col-form-label">Line Number</label>
                            <input type="text" name="line_number" id="line_number" class="form-control" />
                            <div class="error text-danger"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="col-form-label">Select Device</label>
                            <div class="input-group">
                                <select name="device_uuid" class="form-select" id="device-select">
                                    @foreach($devices as $device)
                                        <option value="{{$device->device_uuid}}">{{$device->device_mac_address}}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-primary" type="button" id="add-new-device" data-bs-toggle="modal" data-bs-target="#createDeviceModal">Create</button>
                            </div>
                            <div class="error text-danger" id="device_uuid_error"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mt-4">
                            <button class="btn btn-info assign-device-btn" type="button"> Assign</button>
                            <div class="error text-danger"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <table class="table table-bordered">
            <tr>
                <th>Line</th>
                <th>MAC Address</th>
                <th>Template</th>
                <th>Actions</th>
            </tr>
            @foreach($extension->devices as $device)
                <tr>
                    <td>{{$device->pivot->line_number}}</td>
                    <td>{{$device->device_mac_address}}</td>
                    <td>{{$device->device_template}}</td>
                    <td>
                        <div id="tooltip-container-actions">
                            <a class="action-icon" data-bs-toggle="modal" data-bs-target="#deleteModal" data-href="{{route('extensions.unassign-device', [$extension->extension_uuid, $device->pivot->device_line_uuid ])}}">
                                <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            @endforeach
        </table>
    </div> <!-- end row-->
</div>