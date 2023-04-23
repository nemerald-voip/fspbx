<?php

namespace App\Http\Controllers;

use App\Models\DeviceLines;
use App\Models\DeviceProfile;
use App\Models\Devices;
use App\Models\DeviceVendor;
use App\Models\Extensions;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!userCheckPermission("device_view")) {
            return redirect('/');
        }

        $domainUuid = Session::get('domain_uuid');
        $data = array();
        $data['devices'] = Devices::where('domain_uuid', $domainUuid)
            ->orderBy('device_label')
            ->paginate(10)->onEachSide(1);

        return view('layouts.devices.list')->with($data);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreDeviceRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreDeviceRequest $request)
    {
        $inputs = $request->validated();

        $extension = Extensions::find($inputs['extension_uuid']);

        $device = new Devices();
        $device->fill([
            'device_mac_address' => trim(strtolower(str_replace([':', '-', '.'], '', $inputs['device_mac_address']))),
            'device_label' => $extension->extension,
            'device_vendor' => explode("/", $inputs['device_template'])[0],
            'device_enabled' => 'true',
            'device_enabled_date' => date('Y-m-d H:i:s'),
            'device_template' => $inputs['device_template'],
            'device_profile_uuid' => $inputs['device_profile_uuid'],
            'device_description' => '',
        ]);
        $device->save();

        // Create device lines
        $device->lines = new DeviceLines();
        $device->lines->fill([
            'device_uuid' => $device->device_uuid,
            'line_number' => '1',
            'server_address' => Session::get('domain_name'),
            'server_address_primary' => get_domain_setting('server_address_primary'),
            'server_address_secondary' => get_domain_setting('server_address_secondary'),
            'display_name' => $extension->extension,
            'user_id' => $extension->extension,
            'auth_id' => $extension->extension,
            'label' => $extension->extension,
            'password' => $extension->password,
            'sip_port' => get_domain_setting('line_sip_port'),
            'sip_transport' => get_domain_setting('line_sip_transport'),
            'register_expires' => get_domain_setting('line_register_expires'),
            'enabled' => 'true',
        ]);

        $device->lines->save();

        return response()->json([
            'status' => 'success',
            'device' => $device,
            'message' => 'Device has been created and assigned.'
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Devices  $device
     * @return \Illuminate\Http\Response
     */
    public function show(Devices $device)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Devices  $device
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, Devices $device)
    {
        if($request->ajax()){
            return response()->json($device);
        }

        $domainUuid = Session::get('domain_uuid');

        $profiles = DeviceProfile::where('device_profile_enabled', 'true')
            ->where('domain_uuid', $domainUuid)
            ->orderBy('device_profile_name')->get();

        $vendors = DeviceVendor::where('enabled', 'true')->orderBy('name')->get();
        $extensions = Extensions::where('domain_uuid', $domainUuid)->get();

        return view('layouts.devices.createOrUpdate')
            ->with('device', $device)
            ->with('profiles', $profiles)
            ->with('vendors', $vendors)
            ->with('extensions', $extensions);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateDeviceRequest  $request
     * @param  \App\Models\Device  $device
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateDeviceRequest $request, Devices $device)
    {
        $inputs = $request->validated();

        $device->update($inputs);

        return response()->json([
            'status' => 'success',
            'device' => $device,
            'message' => 'Device has been updated.'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Devices  $device
     * @return \Illuminate\Http\Response
     */
    public function destroy(Devices $device)
    {
        //
    }
}
