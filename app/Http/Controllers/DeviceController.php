<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\DeviceLines;
use App\Models\Devices;
use App\Models\Extensions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The DeviceController class is responsible for handling device-related operations, such as listing, creating, and storing devices.
 *
 * @package App\Http\Controllers
 */
class DeviceController extends Controller
{
    public array $filters = [];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request
    ): Redirector|Response|RedirectResponse|Application {
        if (!userCheckPermission("device_view")) {
            return redirect('/');
        }

        $this->filters = [];

        if (!empty($request->filterData['search'])) {
            $this->filters['search'] = $request->filterData['search'];
        }

        if (!empty($request->filterData['showGlobal'])) {
            $this->filters['showGlobal'] = $request->filterData['showGlobal'] == 'true';
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
                'deviceGlobalView' => (isset($this->filters['showGlobal']) && $this->filters['showGlobal']),
                'routeDevicesStore' => route('devices.store'),
                'routeDevicesOptions' => route('devices.options'),
                'routeDevices' => route('devices.index'),
                'routeSendEventNotifyAll' => route('extensions.send-event-notify-all')
            ]
        );
    }

    /**
     * @return LengthAwarePaginator
     */
    public function getDevices(): LengthAwarePaginator
    {
        $devices = $this->builder($this->filters)->paginate(50);
        foreach ($devices as $device) {
            $device->device_address_tokenized = $device->device_address;
            $device->device_address = formatMacAddress($device->device_address);
            if ($device->lines()->first() && $device->lines()->first()->extension()) {
                $device->extension = $device->lines()->first()->extension()->extension;
                $device->extension_description = ($device->lines()->first()->extension()->effective_caller_id_name) ? '('.trim($device->lines()->first()->extension()->effective_caller_id_name).')' : '';
                $device->extension_uuid = $device->lines()->first()->extension()->extension_uuid;
                $device->extension_edit_path = route('extensions.edit', $device->lines()->first()->extension());
                $device->send_notify_path = route('extensions.send-event-notify',
                    $device->lines()->first()->extension());
            }
            $device->edit_path = route('devices.edit', $device);
            $device->destroy_path = route('devices.destroy', $device);
        }
        return $devices;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = []): Builder
    {
        $devices = Devices::query();
        if (isset($filters['showGlobal']) and $filters['showGlobal']) {
            $devices->join('v_domains', 'v_domains.domain_uuid', '=', 'v_devices.domain_uuid');
        } else {
            $devices->where('v_devices.domain_uuid', Session::get('domain_uuid'));
        }
        $devices->leftJoin('v_device_profiles', 'v_device_profiles.device_profile_uuid', '=', 'v_devices.device_profile_uuid');
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

    /**
     * @param $query
     * @param $value
     * @return void
     */
    protected function filterSearch($query, $value): void
    {
        // Case-insensitive partial string search in the specified fields
        $query->where(function ($query) use ($value) {
            $macAddress = tokenizeMacAddress($value);
            $query->where('device_address', 'ilike', '%'.$macAddress.'%')
                ->orWhere('device_label', 'ilike', '%'.$value.'%')
                ->orWhere('device_vendor', 'ilike', '%'.$value.'%')
                ->orWhere('device_profile_name', 'ilike', '%'.$value.'%')
                ->orWhere('device_template', 'ilike', '%'.$value.'%');
        });
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreDeviceRequest  $request
     * @return JsonResponse
     */
    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $inputs = $request->validated();

        if($inputs['extension_uuid']) {
            $extension = Extensions::find($inputs['extension_uuid']);
        } else {
            $extension = null;
        }

        $device = new Devices();
        $device->fill([
            'device_address' => tokenizeMacAddress($inputs['device_address']),
            'device_label' => $extension->extension ?? null,
            'device_vendor' => explode("/", $inputs['device_template'])[0],
            'device_enabled' => 'true',
            'device_enabled_date' => date('Y-m-d H:i:s'),
            'device_template' => $inputs['device_template'],
            'device_profile_uuid' => $inputs['device_profile_uuid'],
            'device_description' => '',
        ]);
        $device->save();

        if($extension) {
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
        }


        return response()->json([
            'status' => 'success',
            'device' => $device,
            'message' => 'Device has been created and assigned.'
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  Devices  $device
     * @return void
     */
    public function show(Devices $device)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Request  $request
     * @param  Devices  $device
     * @return JsonResponse
     */
    public function edit(Request $request, Devices $device): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json([
                'message' => 'XHR request expected'
            ], 405);
        }

        if ($device->extension()) {
            $device->extension_uuid = $device->extension()->extension_uuid;
        }

        $device->device_address = formatMacAddress($device->device_address);
        $device->update_path = route('devices.update', $device);
        $device->options = [
            'templates' => getVendorTemplateCollection(),
            'profiles' => getProfileCollection($device->domain_uuid),
            'extensions' => getExtensionCollection($device->domain_uuid)
        ];

        return response()->json([
            'status' => 'success',
            'device' => $device
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateDeviceRequest  $request
     * @param  Devices  $device
     * @return JsonResponse
     */
    public function update(UpdateDeviceRequest $request, Devices $device): JsonResponse
    {
        $inputs = $request->validated();
        $inputs['device_vendor'] = explode("/", $inputs['device_template'])[0];
        $device->update($inputs);

        if($request['extension_uuid']) {
            $extension = Extensions::find($request['extension_uuid']);
            if (($device->extension() && $device->extension()->extension_uuid != $request['extension_uuid']) or !$device->extension()) {
                $deviceLinesExist = DeviceLines::query()->where(['device_uuid' => $device->device_uuid])->first();
                if ($deviceLinesExist) {
                    $deviceLinesExist->delete();
                }

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
                    'domain_uuid' => $device->domain_uuid
                ]);
                $deviceLines->save();
                $device->device_label = $extension->extension;
                $device->save();
            }
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
     * @param  Devices  $device
     * @return Response
     */
    public function destroy(Devices $device): Response
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

    public function options(): JsonResponse
    {
        return response()->json([
            'templates' => getVendorTemplateCollection(),
            'profiles' => getProfileCollection(Session::get('domain_uuid')),
            'extensions' => getExtensionCollection(Session::get('domain_uuid'))
        ]);
    }
}
