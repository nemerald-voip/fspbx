<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\Devices;
use App\Models\Settings;
use App\Models\Extensions;
use App\Models\DeviceLines;
use App\Models\SipProfiles;
use Illuminate\Http\Request;
use App\Jobs\SendEventNotify;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Session;
use App\Http\Requests\StoreDeviceRequest;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Requests\UpdateBulkDeviceRequest;

/**
 * The DeviceController class is responsible for handling device-related operations, such as listing, creating, and storing devices.
 *
 * @package App\Http\Controllers
 */
class DeviceController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Devices';
    protected $searchable = ['device_address', 'device_label', 'device_template'];

    public function __construct()
    {
        $this->model = new Devices();
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!userCheckPermission("device_view")) {
            return redirect('/');
        }

        return Inertia::render(
            'Devices',
            [
                'data' => function () {
                    return $this->getData();
                },
                'showGlobal' => function () {
                    return request('filterData.showGlobal') === 'true';
                },
                'itemData' => Inertia::lazy(
                    fn () =>
                    $this->getItemData()
                ),
                'itemOptions' => Inertia::lazy(
                    fn () =>
                    $this->getItemOptions()
                ),
                'routeSendEventNotifyAll' => route('extensions.send-event-notify-all'),
                'routes' => [
                    'current_page' => route('devices.index'),
                    'store' => route('devices.store'),
                    'select_all' => route('devices.select.all'),
                    'bulk_delete' => route('devices.bulk.delete'),
                    'bulk_update' => route('devices.bulk.update'),
                    'restart' => route('devices.restart'),
                ],
            ]
        );
    }

    public function getItemData()
    {
        // Get item data
        $itemData = $this->model::where($this->model->getKeyName(), request('itemUuid'))
            ->select([
                'domain_uuid',
                'device_uuid',
                'device_template',
                'device_label',
                'device_profile_uuid',
                'device_address',
            ])
            ->first();

        // Add update url route info
        $itemData->update_url = route('devices.update', $itemData);
        return $itemData;
    }

    /**
     *  Get device data
     */
    public function getData($paginate = 50)
    {

        // Check if search parameter is present and not empty
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        // Check if showGlobal parameter is present and not empty
        if (!empty(request('filterData.showGlobal'))) {
            $this->filters['showGlobal'] = request('filterData.showGlobal') === 'true';
        } else {
            $this->filters['showGlobal'] = null;
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'device_address'); // Default to 'destination'
        $this->sortOrder = request()->get('sortOrder', 'asc'); // Default to ascending

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
        }

        if (isset($this->filters['showGlobal']) and $this->filters['showGlobal']) {
            // Access domains through the session and filter extensions by those domains
            $domainUuids = Session::get('domains')->pluck('domain_uuid');
            $extensions = Extensions::whereIn('domain_uuid', $domainUuids)
                ->get(['domain_uuid', 'extension', 'effective_caller_id_name']);
        } else {
            // get extensions for session domain
            $extensions = Extensions::where('domain_uuid', session('domain_uuid'))
                ->get(['domain_uuid', 'extension', 'effective_caller_id_name']);
        }

        foreach ($data as $device) {
            // Check each line in the device if it exists
            $device->lines->each(function ($line) use ($extensions, $device) {
                // Find the first matching extension
                $firstMatch = $extensions->first(function ($extension) use ($line, $device) {
                    return $extension->domain_uuid === $device->domain_uuid && $extension->extension === $line->label;
                });

                // Assign the first matching extension to the line
                $line->extension = $firstMatch;
            });
            // logger($device->lines);
        }


        // logger($data);


        // foreach ($data as $device) {


        //     if ($device->lines()->first() && $device->lines()->first()->extension()) {
        //         $device->extension = $device->lines()->first()->extension()->extension;
        //         $device->extension_description = ($device->lines()->first()->extension()->effective_caller_id_name) ? '(' . trim($device->lines()->first()->extension()->effective_caller_id_name) . ')' : '';
        //         $device->extension_uuid = $device->lines()->first()->extension()->extension_uuid;
        //         $device->extension_edit_path = route('extensions.edit', $device->lines()->first()->extension());
        //         $device->send_notify_path = route(
        //             'extensions.send-event-notify',
        //             $device->lines()->first()->extension()
        //         );
        //     }
        //     $device->edit_path = route('devices.edit', $device);
        //     $device->destroy_path = route('devices.destroy', $device);
        // }
        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = []): Builder
    {
        $data =  $this->model::query();

        if (isset($filters['showGlobal']) and $filters['showGlobal']) {
            $data->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description'); // Specify the fields you need
            }]);
            // Access domains through the session and filter devices by those domains
            $domainUuids = Session::get('domains')->pluck('domain_uuid');
            $data->whereHas('domain', function ($query) use ($domainUuids) {
                $query->whereIn($this->model->getTable() . '.domain_uuid', $domainUuids);
            });
        } else {
            // Directly filter devices by the session's domain_uuid
            $domainUuid = Session::get('domain_uuid');
            $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid);
        }

        $data->with(['profile' => function ($query) {
            $query->select('device_profile_uuid', 'device_profile_name', 'device_profile_description');
        }]);

        $data->with(['lines' => function ($query) {
            $query->select('domain_uuid', 'device_line_uuid', 'device_uuid', 'line_number', 'label');
        }]);

        $data->select(
            'device_uuid',
            'device_profile_uuid',
            'device_address',
            'device_label',
            'device_template',
            'domain_uuid',
        );

        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    $this->$method($data, $value);
                }
            }
        }

        // Apply sorting
        $data->orderBy($this->sortField, $this->sortOrder);

        return $data;
    }

    /**
     * @param $query
     * @param $value
     * @return void
     */
    protected function filterSearch($query, $value)
    {
        $searchable = $this->searchable;
        // Case-insensitive partial string search in the specified fields
        $query->where(function ($query) use ($value, $searchable) {
            foreach ($searchable as $field) {
                $query->orWhere($field, 'ilike', '%' . $value . '%');
            }
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

        if ($inputs['extension']) {
            $extension = Extensions::where('extension', $inputs['extension'])
                ->where('domain_uuid', session('domain_uuid'))
                ->first();
        } else {
            $extension = null;
        }

        try {
            // Validate the request data and create a new instance
            $instance = $this->model;
            $instance->fill([
                'device_address' => $inputs['device_address_modified'],
                'domain_uuid' => $inputs['domain_uuid'],
                'device_label' => $extension->extension ?? null,
                'device_vendor' => explode("/", $inputs['device_template'])[0],
                'device_enabled' => 'true',
                'device_enabled_date' => date('Y-m-d H:i:s'),
                'device_template' => $inputs['device_template'],
                'device_profile_uuid' => $inputs['device_profile_uuid'],
                'device_description' => '',
            ]);
            $instance->save();  // Save the new model instance to the database

            if ($extension) {
                // Create device lines
                $instance->lines = new DeviceLines();
                $instance->lines->fill([
                    'device_uuid' => $instance->device_uuid,
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
                $instance->lines->save();
            }

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['New item created']]
            ], 201);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage());

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to create new item']]
            ], 500);  // 500 Internal Server Error for any other errors
        }




        // return response()->json([
        //     'status' => 'success',
        //     'device' => $device,
        //     'message' => 'Device has been created and assigned.'
        // ]);
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
    // public function edit(Request $request, Devices $device): JsonResponse
    // {
    //     logger('here');
    //     if (!$request->ajax()) {
    //         return response()->json([
    //             'message' => 'XHR request expected'
    //         ], 405);
    //     }

    //     if ($device->extension()) {
    //         $device->extension_uuid = $device->extension()->extension_uuid;
    //     }

    //     $device->device_address = formatMacAddress($device->device_address);
    //     $device->update_path = route('devices.update', $device);
    //     $device->options = [
    //         'templates' => getVendorTemplateCollection(),
    //         'profiles' => getProfileCollection($device->domain_uuid),
    //         'extensions' => getExtensionCollection($device->domain_uuid)
    //     ];

    //     return response()->json([
    //         'status' => 'success',
    //         'device' => $device
    //     ]);
    // }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateDeviceRequest  $request
     * @param  Devices  $device
     * @return JsonResponse
     */
    public function update(UpdateDeviceRequest $request, Devices $device): JsonResponse
    {

        if (!$device) {
            // If the model is not found, return an error response
            return response()->json([
                'success' => false,
                'errors' => ['model' => ['Model not found']]
            ], 404); // 404 Not Found if the model does not exist
        }

        try {
            $inputs = array_map(function ($value) {
                return $value === 'NULL' ? null : $value;
            }, $request->validated());


            $inputs['device_vendor'] = explode("/", $inputs['device_template'])[0];
            $inputs['device_address'] = $inputs['device_address_modified'];

            if ($inputs['extension']) {
                $extension = Extensions::where('extension', $inputs['extension'])
                    ->where('domain_uuid', $inputs['domain_uuid'])
                    ->first();

                if ($extension) {
                    $device->device_label = $extension->extension;
                }
            } else {
                $device->device_label = null;
                // Remove existing device lines
                $device->lines()->delete();
            }

            // logger($inputs);
            $device->update($inputs);

            if (isset($extension) && $extension) {
                // Remove existing device lines
                if ($device->lines()->exists()) {
                    $device->lines()->delete();
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


            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Item updated.']]
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update this item']]
            ], 500); // 500 Internal Server Error for any other errors
        }

        return response()->json([
            'success' => false,
            'errors' => ['server' => ['Failed to update this item']]
        ], 500); // 500 Internal Server Error for any other errors

    }

    public function bulkUpdate(UpdateBulkDeviceRequest $request): JsonResponse
    {
        $inputs = $request->validated();
        if (empty($inputs['device_profile_uuid']) && empty($inputs['device_template'])) {
            return response()->json([
                'message' =>  'No option selected to update.',
                'errors' => [
                    'no_option' => [
                        'No option selected to update.'
                    ]
                ]
            ], 422);
        }
        foreach ($inputs['devices'] as $deviceUuid) {
            $device = Devices::find($deviceUuid);
            if (!empty($inputs['device_profile_uuid'])) {
                $device->device_profile_uuid = $inputs['device_profile_uuid'];
            }
            if (!empty($inputs['device_template'])) {
                $device->device_template = $inputs['device_template'];
            }
            $device->save();
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Devices has been updated.'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Devices  $device
     * 
     */
    public function destroy(Devices $device)
    {
        try {
            // throw new \Exception;

            // Delete all device lines
            if ($device->lines()) {
                $device->lines()->delete();
            }

            // Delete Device
            $device->delete();

            return redirect()->back()->with('message', ['server' => ['Item deleted']]);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage());
            return redirect()->back()->with('error', ['server' => ['Server returned an error while deleting this item']]);
        }
    }

    public function getItemOptions()
    {
        $domain_uuid = request('domain_uuid') ?? session('domain_uuid');

        // Define the options for the 'extensions' field
        $extensions = Extensions::where('domain_uuid', $domain_uuid)
            ->get([
                'extension_uuid',
                'extension',
                'effective_caller_id_name',
            ]);

        $extensionOptions = [];
        // Loop through each extension and create an option
        foreach ($extensions as $extension) {
            $extensionOptions[] = [
                'value' => $extension->extension,
                'name' => $extension->name_formatted,
            ];
        }

        $domainOptions = [];
        // Loop through each domain and create an option
        foreach (session('domains') as $domain) {
            $domainOptions[] = [
                'value' => $domain->domain_uuid,
                'name' => $domain->domain_description,
            ];
        }

        // Construct the itemOptions object
        $itemOptions = [
            'templates' => getVendorTemplateCollection(),
            'profiles' => getProfileCollection($domain_uuid),
            'extensions' => $extensionOptions,
            'domains' => $domainOptions,
            // Define options for other fields as needed
        ];

        return $itemOptions;
    }

    public function restart()
    {
        try {

            // Get a collection of SIP registrations 
            $regs = sipRegistrations();

            //Get device info as a collection
            $devices = $this->model::whereIn('device_uuid', request('devices'))
                ->with(['lines' => function ($query) {
                    $query->select('device_uuid', 'auth_id', 'server_address');
                }])
                ->get(['device_uuid']);

            // we are going to push all lines from devices to this collection
            $linesCollection = collect();

            foreach ($devices as $device) {
                $line = $device->lines->first();
                if ($line) {
                    $linesCollection->push($line);
                }
            }

            // logger($devices);

            // Filter and process $regs based on $linesCollection
            $filteredRegs = collect($regs)->filter(function ($reg) use ($linesCollection) {
                [$authId, $domain] = explode('@', $reg['user'], 2);
                return $linesCollection->contains(function ($line) use ($authId, $domain) {
                    return $line['auth_id'] === $authId && $line['server_address'] === $domain;
                });
            })->each(function ($reg) {
                // Determine the agent type based on 'agent' string
                $agent = "";
                if (preg_match('/Bria|Push|Ringotel/i', $reg['agent'])) {
                    $agent = "";
                } elseif (preg_match('/polycom|polyedge/i', $reg['agent'])) {
                    $agent = "polycom";
                } elseif (preg_match("/yealink/i", $reg['agent'])) {
                    $agent = "yealink";
                } elseif (preg_match("/grandstream/i", $reg['agent'])) {
                    $agent = "grandstream";
                }

                // Execute commands if agent is specified
                if (!empty($agent)) {
                    $command = "fs_cli -x 'luarun app.lua event_notify " . $reg['sip_profile_name'] . " reboot " . $reg['user'] . " " . $agent . "'";
                    logger($command);
                    SendEventNotify::dispatch($command)->onQueue('default');
                }
            });

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Selected device(s) scheduled for reboot']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . PHP_EOL);
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Get all items
     *
     * @return \Illuminate\Http\Response
     */
    public function selectAll()
    {
        try {
            if (request()->get('showGlobal')) {
                $uuids = $this->model::get($this->model->getKeyName())->pluck($this->model->getKeyName());
            } else {
                $uuids = $this->model::where('domain_uuid', session('domain_uuid'))
                    ->get($this->model->getKeyName())->pluck($this->model->getKeyName());
            }

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $uuids,
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500); // 500 Internal Server Error for any other errors
        }

        return response()->json([
            'success' => false,
            'errors' => ['server' => ['Failed to select all items']]
        ], 500); // 500 Internal Server Error for any other errors
    }

}
