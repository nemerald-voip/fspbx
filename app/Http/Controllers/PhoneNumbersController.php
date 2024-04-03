<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\StorePhoneNumberRequest;
use App\Models\Destinations;
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

class PhoneNumbersController extends Controller
{
    public array $filters = [];

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
                'selectedDomain' => function () {
                    return Session::get('domain_name');
                },
                'selectedDomainUuid' => function () {
                    return Session::get('domain_uuid');
                },
                'destinationTypes' => function () {
                    return [
                        [
                            "name" => "Inbound",
                            "value" => "inbound"
                        ],
                        [
                            "name" => "Outbound",
                            "value" => "outbound"
                        ],
                        [
                            "name" => "Local",
                            "value" => "local"
                        ]
                    ];
                },
                'deviceGlobalView' => (isset($this->filters['showGlobal']) && $this->filters['showGlobal']),
                'routePhoneNumbersStore' => route('phone-numbers.store'),
                //'routeDevicesOptions' => route('devices.options'),
                //'routeDevicesBulkUpdate' => route('devices.bulkUpdate'),
                'routePhoneNumbers' => route('phone-numbers.index'),
                //'routeSendEventNotifyAll' => route('extensions.send-event-notify-all')
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
            }*/
            $phoneNumber->edit_path = route('phone-numbers.edit', $phoneNumber);
            $phoneNumber->destroy_path = route('phone-numbers.destroy', $phoneNumber);
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
        if (isset($filters['showGlobal']) and $filters['showGlobal']) {
            $phoneNumbers->join('v_domains', 'v_domains.domain_uuid', '=', 'v_destinations.domain_uuid')
                ->whereIn('v_domains.domain_uuid', Session::get('domains')->pluck('domain_uuid'));
        } else {
            $phoneNumbers->where('v_destinations.domain_uuid', Session::get('domain_uuid'));
        }
        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter".ucfirst($field))) {
                    $this->$method($phoneNumbers, $value);
                }
            }
        }
        $phoneNumbers->orderBy('destination_number');
        return $phoneNumbers;
    }

    /**
     * @param $query
     * @param $value
     * @return void
     */
    protected function filterSearch($query, $value): void
    {
        if ($value !== null) {
            // Case-insensitive partial string search in the specified fields
            $query->where(function ($query) use ($value) {
                $query->where('destination_number', 'ilike', '%'.$value.'%')
                    ->orWhere('destination_caller_id_number', 'ilike', '%'.$value.'%')
                    ->orWhere('destination_caller_id_name', 'ilike', '%'.$value.'%');
            });
        }
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
     * @param  \App\Http\Requests\StorePhoneNumberRequest  $request
     * @return JsonResponse
     */
    public function store(StorePhoneNumberRequest $request): JsonResponse
    {
        $inputs = $request->validated();

        var_dump($inputs); die;

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

        if ($extension) {
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
     * @param  \App\Models\Destinations  $phone_number
     * @return JsonResponse
     */
    public function edit(Request $request, Destinations $phone_number)
    {
        if (!$request->ajax()) {
            return response()->json([
                'message' => 'XHR request expected'
            ], 405);
        }

        $phone_number->update_path = route('phone-numbers.update', $phone_number);

        return response()->json([
            'status' => 'success',
            'phone_number' => $phone_number
        ]);
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
     * @param  \App\Models\Destinations  $destination
     * @return Response
     */
    public function destroy(Destinations $destination)
    {
        $destination->delete();

        return Inertia::render('Phonenumbers', [
            'data' => function () {
                return $this->getPhoneNumbers();
            },
            'status' => 'success',
            'phone_number' => $destination,
            'message' => 'Phone number has been deleted'
        ]);
    }
}
