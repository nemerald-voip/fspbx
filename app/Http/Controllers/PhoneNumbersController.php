<?php

namespace App\Http\Controllers;

use App\Models\Destinations;
use App\Models\Devices;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class PhoneNumbersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @return Redirector|Response|RedirectResponse|Application
     */
    public function index(Request $request
    ): Redirector|Response|RedirectResponse|Application {
        if (!userCheckPermission("destination_view")) {
            return redirect('/');
        }

       // die('asdasdasd');

        $this->filters = [];

        $this->filters['search'] = $request->filterData['search'] ?? null;

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
            'Phonenumbers',
            [
                'data' => function () {
                    return $this->getPhoneNumbers();
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
                'routeDevicesBulkUpdate' => route('devices.bulkUpdate'),
                'routeDevices' => route('devices.index'),
                'routeSendEventNotifyAll' => route('extensions.send-event-notify-all')
            ]
        );
    }

    /**
     * @return LengthAwarePaginator
     */
    public function getPhoneNumbers(): LengthAwarePaginator
    {
        $phoneNumbers = $this->builder($this->filters)->paginate(50);
        foreach ($phoneNumbers as $phoneNumber) {
            /*$device->device_address_tokenized = $device->device_address;
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
            $device->destroy_path = route('devices.destroy', $device);*/
        }
        return $phoneNumbers;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = []): Builder
    {
        $phoneNumbers = Destinations::query();
        /*if (isset($filters['showGlobal']) and $filters['showGlobal']) {
            $devices->join('v_domains', 'v_domains.domain_uuid', '=', 'v_devices.domain_uuid')
                ->whereIn('v_domains.domain_uuid', Session::get('domains')->pluck('domain_uuid'));
        } else {
            $devices->where('v_devices.domain_uuid', Session::get('domain_uuid'));
        }
        $devices->leftJoin('v_device_profiles', 'v_device_profiles.device_profile_uuid', '=',
            'v_devices.device_profile_uuid');
        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter".ucfirst($field))) {
                    $this->$method($devices, $value);
                }
            }
        }*/
       // $devices->orderBy('device_label');
        return $phoneNumbers;
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Destinations  $destinations
     * @return \Illuminate\Http\Response
     */
    public function show(Destinations $destinations)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Destinations  $destinations
     * @return \Illuminate\Http\Response
     */
    public function edit(Destinations $destinations)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Destinations  $destinations
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Destinations $destinations)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Destinations  $destinations
     * @return \Illuminate\Http\Response
     */
    public function destroy(Destinations $destinations)
    {
        //
    }
}
