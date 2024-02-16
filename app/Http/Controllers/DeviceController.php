<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\DeviceLines;
use App\Models\DeviceProfile;
use App\Models\Devices;
use App\Models\DeviceVendor;
use App\Models\Extensions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

class DeviceController extends Controller
{
    public $filters;

    /**
     * Display a listing of the resource.
     *
     * @return Application|Factory|View
     */
    public function index(Request $request)
    {
        if (!userCheckPermission("device_view")) {
            return redirect('/');
        }

        /*$scopes = ['global', 'local'];
        $searchString = $request->get('search');
        $searchStringKey = strtolower(trim($searchString));
        $selectedScope = $request->get('scope', 'local');
        $devices = Devices::query();
        if (in_array($selectedScope, $scopes) && $selectedScope == 'local') {
            $devices
                ->where('domain_uuid', Session::get('domain_uuid'));
        } else {
            $devices
                ->join('v_domains','v_domains.domain_uuid','=','v_devices.domain_uuid');
        }
        if (!empty($searchStringKey)) {
            $devices->where(function ($query) use ($searchStringKey) {
                $query
                    ->orWhereLike('device_address', str_replace([':', '-', '.'], '', $searchStringKey))
                    ->orWhereLike('device_label', $searchStringKey)
                    ->orWhereLike('device_vendor', $searchStringKey)
                    ->orWhereLike('device_template', $searchStringKey);
            });
        }
        $devices = $devices->orderBy('device_label');

        $devicesToRestart = $devices->get()->filter(function ($device) {
            return $device->extension();
        });*/

        //$data = array();
        //$data['devices'] = $devices->paginate(5)->onEachSide(1);;
        //$data['devicesToRestartCount'] = $devicesToRestart->count();
        //$data['searchString'] = $searchString;
        //$data['permissions']['device_restart'] = isSuperAdmin();
        //$data['selectedScope'] = $selectedScope;

        //return view('layouts.devices.list')->with($data);

        $this->filters = [];

        if (!empty($request->filterData['search'])) {
            $this->filters['search'] = $request->filterData['search'];
        }

        unset(
            $extensionsCollection,
            $extension,
            $profilesCollection,
            $profile,
            $templateDir,
            $dir,
            $dirs,
            $vendorsCollection,
            $vendor);

        return Inertia::render(
            'devices',
            [
                'data' => function () {
                    return $this->getDevices();
                },
                'menus' => function () {
                    return Session::get('menu');
                },
                'domainSelectPermission' => function () {
                    return Session::get('domain_select');
                },
                'domains' => function () {
                    return Session::get("domains");
                },
                'deviceRestartPermission' => function () {
                    return isSuperAdmin();
                },
                'selectedDomain' => function () {
                    return Session::get('domain_name');
                },
                'selectedDomainUuid' => function () {
                    return Session::get('domain_uuid');
                },
                'deviceGlobalView' => false,
                'routeDevicesStore' => route('devices.store'),
                'routeDevices' => route('devices.index'),
                'routeSendEventNotifyAll' => route('extensions.send-event-notify-all'),
                'templates' => getVendorTemplateCollection(),
                'profiles' => getProfileCollection(Session::get('domain_uuid')),
                'extensions' => getExtensionCollection(Session::get('domain_uuid'))
            ]
        );
    }

    public function getDevices()
    {
        $devices = $this->builder($this->filters)->paginate(50);
        foreach ($devices as $device) {
            $device->profile_name = $device->profile()->first()->device_profile_name ?? '';
            if ($device->lines()->first() && $device->lines()->first()->extension()) {
                $device->extension = $device->lines()->first()->extension()->extension;
                $device->extension_uuid = $device->lines()->first()->extension()->extension_uuid;
                $device->extension_edit_path = route('extensions.edit', $device->lines()->first()->extension());
                $device->send_notify_path = route('extensions.send-event-notify',
                    $device->lines()->first()->extension());
            }
            //$device->domain_name = Session::get('domain_name');
            $device->edit_path = route('devices.edit', $device);
            $device->destroy_path = route('devices.destroy', $device);
        }

        /*        $devices->transform(function ($cdr) {
                    // Perform any additional processing on start_date if needed
                    // For example, format start_date or add additional data

                    // Add or modify attributes as needed
                    $cdr->start_date = $cdr->start_date;
                    $cdr->start_time = $cdr->start_time;

                    return $cdr;
                });*/
        return $devices;
    }

    public function builder($filters = [])
    {
        $devices = Devices::query();
        $devices->join('v_domains', 'v_domains.domain_uuid', '=', 'v_devices.domain_uuid');
        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter".ucfirst($field))) {
                    $this->$method($devices, $value);
                }
            }
        }
        $devices->orderBy('device_label');
        return $devices;
    }

    protected function filterShowglobal($query, $value)
    {
        if ($value) {
            $query->where('domain_uuid', Session::get('domain_uuid'));
        }
    }

    protected function filterSearch($query, $value)
    {
        // Case-insensitive partial string search in the specified fields
        $query->where(function ($query) use ($value) {
            $query->where('device_address', 'ilike', '%'.$value.'%')
                ->orWhere('device_label', 'ilike', '%'.$value.'%')
                ->orWhere('device_vendor', 'ilike', '%'.$value.'%')
                ->orWhere('device_template', 'ilike', '%'.$value.'%');
        });
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View|Response
     */
    public function create()
    {
        /*
        $domainUuid = Session::get('domain_uuid');

        $profiles = DeviceProfile::where('device_profile_enabled', 'true')
            ->where('domain_uuid', $domainUuid)
            ->orderBy('device_profile_name')->get();

        $vendors = DeviceVendor::where('enabled', 'true')->orderBy('name')->get();
        $extensions = Extensions::where('domain_uuid', $domainUuid)->orderBy('extension')->get();

        $device = new Devices();

        return view('layouts.devices.createOrUpdate')
            ->with('device', $device)
            ->with('profiles', $profiles)
            ->with('vendors', $vendors)
            ->with('extensions', $extensions);*/
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreDeviceRequest  $request
     * @return JsonResponse
     */
    public function store(StoreDeviceRequest $request)
    {
        $inputs = $request->validated();

        $extension = Extensions::find($inputs['extension_uuid']);

        $device = new Devices();
        $device->fill([
            'device_address' => trim(strtolower(str_replace([':', '-', '.'], '', $inputs['device_address']))),
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
            'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary'),
            'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary'),
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
     * @return Response
     */
    public function show(Devices $device)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Devices  $device
     * @return JsonResponse
     */
    public function edit(Request $request, Devices $device)
    {
        if(!$request->ajax()) {
            return response()->json([
                'message' => 'XHR request expected'
            ], 405);
        }

        if($device->extension()) {
            $device->extension_uuid = $device->extension()->extension_uuid;
        }

        $device->update_path = route('devices.update', $device);

        return response()->json([
            'status' => 'success',
            'device' => $device
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateDeviceRequest  $request
     * @param  \App\Models\Device  $device
     * @return JsonResponse
     */
    public function update(UpdateDeviceRequest $request, Devices $device)
    {
        $inputs = $request->validated();
        $device->update($inputs);

        if (($device->extension() && $device->extension()->extension_uuid != $request['extension_uuid']) or !$device->extension()) {
            $deviceLinesExist = DeviceLines::query()->where(['device_uuid' => $device->device_uuid])->first();
            if ($deviceLinesExist) {
                $deviceLinesExist->delete();
            }

            $extension = Extensions::find($request['extension_uuid']);

            // Create device lines
            $deviceLines = new DeviceLines();
            $deviceLines->fill([
                'device_uuid' => $device->device_uuid,
                'line_number' => '1',
                'server_address' => Session::get('domain_name'),
                'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary'),
                'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary'),
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
            $deviceLines->save();
            $device->device_label = $extension->extension;
            $device->save();
        }

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
     * @return Response
     */
    public function destroy(Devices $device)
    {
        if ($device->lines()) {
            $device->lines()->delete();
        }
        $device->delete();

        return Inertia::render('devices', [
            'data' => function () {
                return $this->getDevices();
            },
            'status' => 'success',
            'device' => $device,
            'message' => 'Device has been deleted'
        ]);
    }
}
