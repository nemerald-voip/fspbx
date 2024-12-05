<?php

namespace App\Http\Controllers;

use App\Services\CloudProvisioningService;
use Inertia\Inertia;
use App\Models\Domain;
use App\Models\Devices;
use App\Models\Extensions;
use App\Models\DeviceLines;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Services\LineKeyTypesService;
use Illuminate\Support\Facades\Session;
use App\Http\Requests\StoreDeviceRequest;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Requests\BulkUpdateDeviceRequest;
use App\Services\DeviceActionService;
use App\Services\FreeswitchEslService;

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
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },
                'showGlobal' => function () {
                    return request('filterData.showGlobal') === 'true';
                },
                'itemData' => Inertia::lazy(
                    fn() =>
                    $this->getItemData()
                ),
                'itemOptions' => Inertia::lazy(
                    fn() =>
                    $this->getItemOptions()
                ),
                'routes' => [
                    'current_page' => route('devices.index'),
                    'store' => route('devices.store'),
                    'select_all' => route('devices.select.all'),
                    'bulk_delete' => route('devices.bulk.delete'),
                    'bulk_update' => route('devices.bulk.update'),
                    'restart' => route('devices.restart'),
                    'cloud_provisioning_status' => route('cloudProvisioning.status'),
                    'cloud_provisioning_register' => route('cloudProvisioning.register'),
                    'cloud_provisioning_deregister' => route('cloudProvisioning.deregister'),
                ]
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
            // Access domains through the session and filter by those domains
            $domainUuids = Session::get('domains')->pluck('domain_uuid');
            $data->whereHas('domain', function ($query) use ($domainUuids) {
                $query->whereIn($this->model->getTable() . '.domain_uuid', $domainUuids);
            });
        } else {
            // Directly filter by the session's domain_uuid
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

        try {
            // Create a new instance of the device model
            $instance = $this->model;

            // Determine the vendor
            $vendor = explode("/", $inputs['device_template'])[0];
            if ($vendor === 'poly') {
                $vendor = 'polycom';
            }

            // Fill the instance with input data
            $instance->fill([
                'device_address' => $inputs['device_address_modified'],
                'domain_uuid' => $inputs['domain_uuid'],
                'device_vendor' => $vendor,
                'device_enabled' => 'true',
                'device_enabled_date' => date('Y-m-d H:i:s'),
                'device_template' => $inputs['device_template'],
                'device_profile_uuid' => $inputs['device_profile_uuid'],
                'device_description' => '',
            ]);

            // Save the new model instance to the database
            $instance->save();

            // Check if lines are passed and is an array
            if (!empty($inputs['lines']) && is_array($inputs['lines'])) {
                foreach ($inputs['lines'] as $index => $line) {
                    $extension = Extensions::where('extension', $line['user_id'])
                        ->where('domain_uuid', $inputs['domain_uuid'])
                        ->first();

                    if ($extension) {
                        $sharedLine = $line['shared_line'] !== null ? "1" : null;
                        // Create a new DeviceLines instance and fill it
                        $deviceLines = new DeviceLines();
                        $deviceLines->fill([
                            'device_uuid' => $instance->device_uuid,
                            'line_number' => $line['line_number'],
                            'server_address' => Session::get('domain_name'),
                            'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary'),
                            'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary'),
                            'server_address_primary' => get_domain_setting('server_address_primary'),
                            'server_address_secondary' => get_domain_setting('server_address_secondary'),
                            'display_name' => $line['display_name'],
                            'user_id' => $extension->extension,
                            'auth_id' => $extension->extension,
                            'label' => $extension->extension,
                            'password' => $extension->password,
                            'sip_port' => get_domain_setting('line_sip_port'),
                            'sip_transport' => get_domain_setting('line_sip_transport'),
                            'register_expires' => get_domain_setting('line_register_expires'),
                            'shared_line' => $sharedLine,
                            'enabled' => 'true',
                            'domain_uuid' => $instance->domain_uuid
                        ]);
                        $deviceLines->save();

                        // Set device label based on the first extension
                        if ($index === 0) {
                            $instance->device_label = $extension->extension;
                        }
                    }
                }
            }

            // Update the instance with the label
            $instance->save();

            if($inputs['device_provisioning']) {
                $instance->registerOnZtp();
            }

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['New item created']]
            ], 201);
        } catch (\Exception $e) {
            // Log the error message
            logger($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to create new item']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
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
            if ($inputs['device_vendor'] === 'poly') {
                $inputs['device_vendor'] = 'polycom';
            }
            $inputs['device_address'] = $inputs['device_address_modified'];

            // Check if lines are passed
            if (!empty($inputs['lines']) && is_array($inputs['lines'])) {
                // Remove existing device lines
                $device->lines()->delete();

                foreach ($inputs['lines'] as $index => $line) {
                    $extension = Extensions::where('extension', $line['user_id'])
                        ->where('domain_uuid', $inputs['domain_uuid'])
                        ->first();

                    if ($extension) {
                        $sharedLine = $line['shared_line'] !== null ? "1" : null;
                        // Create new device line
                        $deviceLines = new DeviceLines();
                        $deviceLines->fill([
                            'device_uuid' => $device->device_uuid,
                            'line_number' => $line['line_number'],
                            'server_address' => $line['server_address'] ?? session('domain_name'),
                            'outbound_proxy_primary' => $line['outbound_proxy_primary'] ?? get_domain_setting('outbound_proxy_primary'),
                            'outbound_proxy_secondary' => $line['outbound_proxy_secondary'] ?? get_domain_setting('outbound_proxy_secondary'),
                            'server_address_primary' => $line['server_address_primary'] ?? get_domain_setting('server_address_primary'),
                            'server_address_secondary' => $line['server_address_secondary'] ?? get_domain_setting('server_address_secondary'),
                            'display_name' => $line['display_name'],
                            'user_id' => $extension->extension,
                            'auth_id' => $extension->extension,
                            'label' => $extension->extension,
                            'password' => $extension->password,
                            'sip_port' => $line['sip_port'] ?? get_domain_setting('line_sip_port'),
                            'sip_transport' => $line['sip_transport'] ?? get_domain_setting('line_sip_transport'),
                            'register_expires' => $line['register_expires'] ?? get_domain_setting('line_register_expires'),
                            'shared_line' => $sharedLine,
                            'enabled' => 'true',
                            'domain_uuid' => $device->domain_uuid
                        ]);
                        $deviceLines->save();

                        // Set device label based on the first extension
                        if ($index === 0) {
                            $device->device_label = $extension->extension;
                        }
                    }
                }
            } else {
                $device->device_label = null;
                // Remove existing device lines
                $device->lines()->delete();
            }

            $deviceAddressBeforeChange = null;
            $deviceVendorBeforeChange = null;
            //if ($inputs['device_provisioning']) {
                if ($inputs['device_address'] != $device->device_address) {
                    $deviceAddressBeforeChange = $device->device_address;
                }
                if ($inputs['device_vendor'] != $device->device_vendor) {
                    $deviceVendorBeforeChange = $device->device_vendor;
                }
            //}

            $device->update($inputs);

            if ($deviceAddressBeforeChange || $deviceVendorBeforeChange) {
                $device->deregisterOnZtp($deviceAddressBeforeChange, $deviceVendorBeforeChange, true);
            }

            if ($inputs['device_provisioning']) {
                $device->registerOnZtp();
            } else {
                $device->deregisterOnZtp();
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
        try {

            $device = $this->model::find(request('itemUuid'));

            $domain_uuid = request('domain_uuid') ?? session('domain_uuid');

            // Define the options for the 'extensions' field
            $extensions = Extensions::where('domain_uuid', $domain_uuid)
                ->orderBy('extension')  // Sorts by the 'extension' field in ascending order
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
            if (session('domains')) {
                foreach (session('domains') as $domain) {
                    $domainOptions[] = [
                        'value' => $domain->domain_uuid,
                        'name' => $domain->domain_description,
                    ];
                }
            }

            $lines = [];
            if (request('itemUuid')) {
                $lines = DeviceLines::where('device_uuid', request('itemUuid'))
                    ->get([
                        'device_line_uuid',
                        'line_number',
                        'user_id',
                        'display_name',
                        'shared_line',
                        'server_address',
                        'server_address_primary',
                        'server_address_secondary',
                        'sip_port',
                        'sip_transport',
                        'register_expires'
                    ])
                    ->map(function ($line) use ($device) {
                        if ($line->shared_line) {
                            $line->line_type_id = 'sharedline';
                        } else {
                            if ($device->device_vendor == 'yealink') $line->line_type_id = "15";
                            if ($device->device_vendor == 'polycom') $line->line_type_id = "line";
                        }
                        return $line;
                    });

                // logger($lines);
            }

            $navigation = [
                [
                    'name' => 'Settings',
                    'icon' => 'Cog6ToothIcon',
                    'slug' => 'settings',
                ],
                [
                    'name' => 'Lines',
                    'icon' => 'AdjustmentsHorizontalIcon',
                    'slug' => 'lines',
                ],
                [
                    'name' => 'Cloud Provisioning',
                    'icon' => 'CloudIcon',
                    'slug' => 'provisioning',
                ],
            ];

            $lineKeyTypes = [];
            if ($device) {
                if ($device->device_vendor == 'yealink') {
                    $lineKeyTypes = LineKeyTypesService::getYealinkKeyTypes();
                } else if ($device->device_vendor == 'polycom') {
                    $lineKeyTypes = LineKeyTypesService::getPolycomKeyTypes();
                }
            } else {
                $lineKeyTypes = [
                    ['value' => 'line', 'name' => 'Line'],
                    ['value' => 'sharedline', 'name' => 'Shared Line'],
                ];
            }

            $sipTransportTypes = [
                ['value' => 'udp', 'name' => 'UDP'],
                ['value' => 'tcp', 'name' => 'TCP'],
                ['value' => 'tls', 'name' => 'TLS'],
                ['value' => 'dns srv', 'name' => 'DNS SRV'],
            ];


            // Construct the itemOptions object
            $itemOptions = [
                'templates' => getVendorTemplateCollection(),
                'profiles' => getProfileCollection($domain_uuid),
                'extensions' => $extensionOptions,
                'domains' => $domainOptions,
                'navigation' => $navigation,
                'lines' => $lines,
                'line_key_types' => $lineKeyTypes,
                'sip_transport_types' => $sipTransportTypes,
                // Define options for other fields as needed
            ];

            return $itemOptions;
        } catch (\Exception $e) {
            logger($e->getMessage());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to get item details']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Bulk update requested items
     *
     * @param  \Illuminate\Http\BulkUpdateDeviceRequest  $request
     * @return JsonResponse
     */
    public function bulkUpdate(BulkUpdateDeviceRequest  $request)
    {
        // $request->items has items IDs that need to be updated
        // $request->validated has the update data

        try {
            // Prepare the data for updating
            $inputs = collect($request->validated())
                ->filter(function ($value) {
                    return $value !== null;
                })->toArray();

            if (isset($inputs['device_template'])) {
                $inputs['device_vendor'] = explode("/", $inputs['device_template'])[0];
                if ($inputs['device_vendor'] === 'poly') {
                    $inputs['device_vendor'] = 'polycom';
                }
            }

            // Check if device_profile_uuid is intended to be NULL and adjust accordingly
            // This will convert string "NULL" to literal null values
            if (array_key_exists('device_profile_uuid', $inputs) && $inputs['device_profile_uuid'] === 'NULL') {
                $inputs['device_profile_uuid'] = null;  // This explicitly sets it to NULL
            }

            // Remove 'lines' from $inputs if it's present, as we don't want to update it directly in v_devices table
            $lines = $inputs['lines'] ?? null;
            unset($inputs['lines']);

            if (sizeof($inputs) > 0) {
                $updated = $this->model::whereIn($this->model->getKeyName(), request()->items)
                    ->update($inputs);
            }

            // Handle device lines
            if ($lines && is_array($lines)) {
                // Delete all existing device lines
                $this->deleteDeviceLines($request->items);

                // Create new device lines based on the array of lines
                $this->createDeviceLines($request->items, $lines);
            }

            return response()->json([
                'messages' => ['success' => ['Selected items updated']],
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update selected items']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }


    public function restart(FreeswitchEslService $eslService, DeviceActionService $deviceActionService)
    {
        try {

            // Get a collection of SIP registrations
            $regs = $eslService->getAllSipRegistrations();

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
            })->each(function ($reg) use ($deviceActionService) {
                $deviceActionService->handleDeviceAction($reg, 'reboot');
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
     * @return JsonResponse
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
    }

    public function deleteDeviceLines($deviceUuids)
    {
        // Retrieve all devices at once with their lines
        $devices = $this->model::whereIn('device_uuid', $deviceUuids)
            ->with(['lines' => function ($query) {
                $query->select('device_uuid', 'device_line_uuid');
            }])
            ->get(['device_uuid']);

        $lineIdsToDelete = [];
        foreach ($devices as $device) {
            // Collect line IDs
            foreach ($device->lines as $line) {
                $lineIdsToDelete[] = $line->device_line_uuid; // Assuming there's an 'id' field
            }

            // $device->update(['device_label' => null]);
            // logger($device);
        }

        // Bulk delete lines
        DeviceLines::whereIn('device_line_uuid', $lineIdsToDelete)->delete();

        // Bulk update all devices to set label to null
        $this->model::whereIn('device_uuid', $deviceUuids)->update(['device_label' => null]);
    }

    public function createDeviceLines($deviceUuids, $lines)
    {
        // Retrieve all devices at once with their lines
        $devices = $this->model::whereIn('device_uuid', $deviceUuids)
            ->get(['device_uuid', 'domain_uuid']);

        foreach ($devices as $device) {
            $domain_name = Domain::find($device->domain_uuid)->domain_name;

            foreach ($lines as $index => $line) {
                $extension = Extensions::where('domain_uuid', $device->domain_uuid)
                    ->where('extension', $line['user_id'])
                    ->select(['extension_uuid', 'extension', 'password'])
                    ->first();

                if ($extension) {
                    $sharedLine = $line['shared_line'] !== null ? "1" : null;

                    // Create new device lines for each line provided
                    $deviceLines = new DeviceLines();
                    $deviceLines->fill([
                        'device_uuid' => $device->device_uuid,
                        'line_number' => $line['line_number'],
                        'server_address' => $domain_name,
                        'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary'),
                        'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary'),
                        'server_address_primary' => get_domain_setting('server_address_primary'),
                        'server_address_secondary' => get_domain_setting('server_address_secondary'),
                        'display_name' => $line['display_name'],
                        'user_id' => $extension->extension,
                        'auth_id' => $extension->extension,
                        'label' => $extension->extension,
                        'password' => $extension->password,
                        'sip_port' => get_domain_setting('line_sip_port'),
                        'sip_transport' => get_domain_setting('line_sip_transport'),
                        'register_expires' => get_domain_setting('line_register_expires'),
                        'shared_line' => $sharedLine,
                        'enabled' => 'true',
                        'domain_uuid' => $device->domain_uuid
                    ]);
                    $deviceLines->save();

                    // Set device label based on the first extension
                    if ($index === 0) {
                        $device->update(['device_label' => $extension->extension]);
                    }
                }
            }
        }
    }


    // public function createDeviceLines($deviceUuids, $lines)
    // {
    //     // Retrieve all devices at once with their lines
    //     $devices = $this->model::whereIn('device_uuid', $deviceUuids)
    //         ->get(['device_uuid', 'domain_uuid']);

    //     $domain_uuid = $devices->first()->domain_uuid;
    //     $domain_name = Domain::find($domain_uuid)->domain_name;

    //     $extension = Extensions::where('domain_uuid', $domain_uuid)
    //         ->where('extension', $extension_number)
    //         ->select([
    //             'extension_uuid',
    //             'extension',
    //             'password'
    //         ])
    //         ->first();

    //     foreach ($devices as $device) {
    //         $deviceLines = new DeviceLines();
    //         $deviceLines->fill([
    //             'device_uuid' => $device->device_uuid,
    //             'line_number' => '1',
    //             'server_address' => $domain_name,
    //             'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary'),
    //             'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary'),
    //             'server_address_primary' => get_domain_setting('server_address_primary'),
    //             'server_address_secondary' => get_domain_setting('server_address_secondary'),
    //             'display_name' => $extension->extension,
    //             'user_id' => $extension->extension,
    //             'auth_id' => $extension->extension,
    //             'label' => $extension->extension,
    //             'password' => $extension->password,
    //             'sip_port' => get_domain_setting('line_sip_port'),
    //             'sip_transport' => get_domain_setting('line_sip_transport'),
    //             'register_expires' => get_domain_setting('line_register_expires'),
    //             'enabled' => 'true',
    //             'domain_uuid' => $domain_uuid
    //         ]);
    //         $deviceLines->save();
    //     }

    //     // // Bulk update all devices to set label to extension number
    //     $this->model::whereIn('device_uuid', $deviceUuids)->update(['device_label' => $extension_number]);
    // }


    /**
     * Remove the specified resource from storage.
     *
     * @param  Devices  $device
     *
     */
    public function BulkDelete(Devices $device)
    {
        try {
            // Begin Transaction
            DB::beginTransaction();

            // Retrieve all devices at once with their lines
            $devices = $this->model::whereIn('device_uuid', request('items'))
                ->with(['lines' => function ($query) {
                    $query->select('device_uuid', 'device_line_uuid');
                }])
                ->get(['device_uuid']);

            foreach ($devices as $device) {
                // Delete all related lines for each device
                if ($device->lines) { // Using eager loaded 'lines'
                    foreach ($device->lines as $line) {
                        $line->delete();
                    }
                }

                // Delete the device itself
                $device->delete();
            }

            // Commit Transaction
            DB::commit();

            return response()->json([
                'messages' => ['server' => ['All selected items have been deleted successfully.']],
            ], 200);
        } catch (\Exception $e) {
            // Rollback Transaction if any error occurs
            DB::rollBack();

            // Log the error message
            logger($e->getMessage());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while deleting the selected items.']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }
}
