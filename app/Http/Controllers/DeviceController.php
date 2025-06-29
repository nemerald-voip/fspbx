<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Domain;
use App\Models\Devices;
use App\Data\DeviceData;
use App\Models\Extensions;
use App\Models\DeviceLines;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Services\DeviceActionService;
use App\Services\LineKeyTypesService;
use Spatie\QueryBuilder\QueryBuilder;
use App\Services\FreeswitchEslService;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\Session;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Services\CloudProvisioningService;
use App\Http\Requests\BulkUpdateDeviceRequest;

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
    public function index(Request $request)
    {
        if (!userCheckPermission("device_view")) {
            return redirect('/');
        }

        $perPage = 50;
        $currentDomain = session('domain_uuid');

        // If the filter is not present, assign default value before QueryBuilder
        if (!$request->has('filter.showGlobal')) {
            $request->merge([
                'filter' => array_merge(
                    $request->input('filter', []),
                    ['showGlobal' => false]
                ),
            ]);
        }

        $devices = QueryBuilder::for(Devices::class)
            ->select([
                'domain_uuid',
                'device_uuid',
                'device_template',
                'device_label',
                'device_profile_uuid',
                'device_address',
            ])
            // allow ?filter[username]=foo or ?filter[user_email]=bar
            ->allowedFilters([
                // Only email and name_formatted
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('device_address', 'ilike', "%{$value}%")
                            ->orWhere('device_template', 'ilike', "%{$value}%")
                            ->orWhereHas('profile', function ($q2) use ($value) {
                                $q2->where('device_profile_name', 'ilike', "%{$value}%");
                            })
                            ->orWhereHas('lines.extension', function ($q3) use ($value) {
                                $q3->where('extension', 'ilike', "%{$value}%")
                                    ->orWhere('effective_caller_id_name', 'ilike', "%{$value}%");
                            });
                    });
                }),
                AllowedFilter::callback('showGlobal', function ($query, $value) use ($currentDomain) {
                    // If showGlobal is falsey (0, '0', false, null), restrict to the current domain
                    if (!$value || $value === '0' || $value === 0 || $value === false) {
                        $query->where('domain_uuid', $currentDomain);
                    }
                    // else, do nothing and show all domains
                }),
            ])

            ->with(['lines' => function ($query) use ($currentDomain) {
                $query->select('device_line_uuid', 'line_number', 'device_uuid', 'auth_id', 'domain_uuid')
                    ->with([
                        'extension' => function ($q) use ($currentDomain) {
                            $q->select('extension_uuid', 'extension', 'effective_caller_id_name')
                                ->where('domain_uuid', $currentDomain);
                        },

                    ]);
            }])
            ->with(['profile' => function ($query) {
                $query->select('device_profile_uuid', 'device_profile_name', 'device_profile_description');
            }])
            ->with(['cloudProvisioning' => function ($query) {
                $query->select('uuid', 'device_uuid', 'status');
            }])

            ->allowedSorts(['device_address'])
            ->defaultSort('device_address')
            ->paginate($perPage);

        // wrap in your DTO
        $devicesDto = DeviceData::collect($devices);

        // logger( $devicesDto);

        return Inertia::render(
            $this->viewName,
            [
                'data' => $devicesDto,

                'routes' => [
                    'current_page' => route('devices.index'),
                    'store' => route('devices.store'),
                    'select_all' => route('devices.select.all'),
                    'bulk_delete' => route('devices.bulk.delete'),
                    'bulk_update' => route('devices.bulk.update'),
                    'item_options' => route('devices.item.options'),
                    'restart' => route('devices.restart'),
                    //'cloud_provisioning_domains' => route('cloud-provisioning.domains'),
                    //'cloud_provisioning' => route('cloud-provisioning.index'),
                    'cloud_provisioning_status' => route('cloud-provisioning.status'),
                    'cloud_provisioning_register' => route('cloud-provisioning.register'),
                    'cloud_provisioning_deregister' => route('cloud-provisioning.deregister'),
                    'cloud_provisioning_create_organization' => route('cloud-provisioning.organization.create'),
                    'cloud_provisioning_update_organization' => route('cloud-provisioning.organization.update'),
                    'cloud_provisioning_destroy_organization' => route('cloud-provisioning.organization.destroy'),
                    'cloud_provisioning_pair_organization' => route('cloud-provisioning.organization.pair'),
                    'cloud_provisioning_get_all_orgs' => route('cloud-provisioning.organization.all'),
                    'cloud_provisioning_get_api_token' => route('cloud-provisioning.token.get'),
                    'cloud_provisioning_update_api_token' => route('cloud-provisioning.token.update'),
                    'cloud_provisioning_item_options' => route('cloud-provisioning.item.options'),
                    'cloud_provisioning_sync_devices' => route('cloud-provisioning.devices.sync'),
                ]
            ]
        );
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

        return $data;
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
    public function store(StoreDeviceRequest $request)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $inputs = $validated;
            $inputs['device_vendor'] = explode("/", $inputs['device_template'])[0] ?? null;
            if ($inputs['device_vendor'] === 'poly') {
                $inputs['device_vendor'] = 'polycom';
            }
            $inputs['device_address'] = $inputs['device_address_modified'];

            // Create device
            $device = new Devices();
            $device->fill($inputs);
            $device->save();

            // Create device lines
            if (!empty($inputs['device_keys']) && is_array($inputs['device_keys'])) {
                foreach ($inputs['device_keys'] as $index => $line) {
                    $extension = Extensions::where('extension', $line['auth_id'])
                        ->where('domain_uuid', $inputs['domain_uuid'])
                        ->first();
                    if ($extension) {
                        $deviceLines = new DeviceLines();
                        $deviceLines->fill([
                            'device_uuid' => $device->device_uuid,
                            'line_number' => $line['line_number'],
                            'line_type_id' => $line['line_type_id'] ?? 'line',
                            'server_address' => session('domain_name'),
                            'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary'),
                            'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary'),
                            'server_address_primary' => $line['server_address_primary'] ?? get_domain_setting('server_address_primary'),
                            'server_address_secondary' => $line['server_address_secondary'] ?? get_domain_setting('server_address_secondary'),
                            'display_name' => $line['display_name'],
                            'user_id' => $extension ? $extension->extension : null,
                            'auth_id' => $extension ? $extension->extension : $line['auth_id'],
                            'label' => $line['display_name'],
                            'password' => $extension ? $extension->password : null,
                            'sip_port' => $line['sip_port'] ?? get_domain_setting('line_sip_port'),
                            'sip_transport' => $line['sip_transport'] ?? get_domain_setting('line_sip_transport'),
                            'register_expires' => $line['register_expires'] ?? get_domain_setting('register_expires'),
                            'shared_line' => $line['shared_line'] ?? null,
                            'device_line_uuid' => Str::uuid(),
                            'domain_uuid' => $device->domain_uuid,
                            'enabled' => 'true',
                        ]);

                        $deviceLines->save();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Device created successfully.']]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            logger('DeviceController@store error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to create device']]
            ], 500);
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
    public function update(UpdateDeviceRequest $request, Devices $device)
    {
        $inputs = $request->validated();

        if (!$device) {
            return response()->json([
                'success' => false,
                'errors' => ['model' => ['Device not found']]
            ], 404);
        }

        try {
            DB::beginTransaction();
            // Prepare device inputs for update
            $inputs['device_vendor'] = explode("/", $inputs['device_template'])[0] ?? null;
            if ($inputs['device_vendor'] === 'poly') {
                $inputs['device_vendor'] = 'polycom';
            }
            // Device DB uses device_address, set it from device_address_modified
            $inputs['device_address'] = $inputs['device_address_modified'];

            $device->update($inputs);

            // Create new device lines
            if (array_key_exists('device_keys', $inputs)) {
                if (empty($inputs['device_keys'])) {
                    // Field is present but empty: remove all device lines
                    $device->lines()->delete();
                } else {
                    // Field is present and has items: remove all then recreate
                    $device->lines()->delete();

                    foreach ($inputs['device_keys'] as $index => $line) {
                        $extension = Extensions::where('extension', $line['auth_id'])
                            ->where('domain_uuid', $inputs['domain_uuid'])
                            ->first();

                        $deviceLineData = [
                            'device_uuid' => $device->device_uuid,
                            'line_number' => $line['line_number'],
                            'line_type_id' => $line['line_type_id'] ?? null,
                            'server_address' => $line['server_address'],
                            'server_address_primary' => $line['server_address_primary'],
                            'server_address_secondary' => $line['server_address_secondary'],
                            'display_name' => $line['display_name'],
                            'user_id' => $extension ? $extension->extension : null,
                            'auth_id' => $extension ? $extension->extension : $line['auth_id'],
                            'label' => $line['display_name'],
                            'password' => $extension ? $extension->password : null,
                            'sip_port' => $line['sip_port'],
                            'sip_transport' => $line['sip_transport'],
                            'register_expires' => $line['register_expires'],
                            'shared_line' => $line['shared_line'] ?? null,
                            'device_line_uuid' => $line['device_line_uuid'] ?? null,
                            'domain_uuid' => $device->domain_uuid,
                            'enabled' => 'true',
                        ];

                        $deviceLines = new DeviceLines();
                        $deviceLines->fill($deviceLineData);
                        $deviceLines->save();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Device updated succesfully.']]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            logger('DeviceController@update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update this device']]
            ], 500);
        }
    }



    public function assign(UpdateDeviceRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Find the device by address (or address_modified)
        $device = Devices::where('device_address', $data['device_address_modified'] ?? $data['device_address'])->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'errors' => ['model' => ['Device not found']]
            ], 404);
        }

        try {
            // Assign or update device_keys
            if (!empty($data['device_keys']) && is_array($data['device_keys'])) {
                foreach ($data['device_keys'] as $index => $line) {
                    $extension = Extensions::where('extension', $line['user_id'])
                        ->where('domain_uuid', $data['domain_uuid'])
                        ->first();

                    if ($extension) {

                        // Try to find an existing line for this device/line_number
                        $deviceLine = DeviceLines::where('device_uuid', $device->device_uuid)
                            ->where('line_number', $line['line_number'])
                            ->first();

                        $deviceLineData = [
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
                            'shared_line' => $line['shared_line'] ?? null,
                            'enabled' => 'true',
                            'domain_uuid' => $device->domain_uuid,
                        ];

                        if ($deviceLine) {
                            // Update existing line
                            $deviceLine->fill($deviceLineData)->save();
                        } else {
                            // Create new line
                            $deviceLine = new DeviceLines();
                            $deviceLine->fill($deviceLineData)->save();
                        }

                    }
                }
                $device->save();
            }

            return response()->json([
                'messages' => ['success' => ['Device assigned/updated.']]
            ], 200);
        } catch (\Exception $e) {
            logger('DeviceController@assign error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to assign device']]
            ], 500);
        }
    }

    /**
     * Remove the specified users from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUnassign()
    {
        if (! userCheckPermission('user_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        try {
            DB::beginTransaction();

            $uuids = request('items', []); // Device UUIDs
            $extension_uuid = request('extension_uuid');

            if (empty($uuids) || !$extension_uuid) {
                return response()->json([
                    'messages' => ['error' => ['No devices or extension provided.']]
                ], 400);
            }

            // Get the actual extension value (number), not just UUID, if you map by extension string
            $extension = Extensions::where('extension_uuid', $extension_uuid)->first();
            if (! $extension) {
                return response()->json([
                    'messages' => ['error' => ['Extension not found.']]
                ], 404);
            }

            $affected = DeviceLines::whereIn('device_uuid', $uuids)
                ->where('auth_id', $extension->extension)
                ->where('domain_uuid', session('domain_uuid'))
                ->delete();

            DB::commit();

            return response()->json([
                'messages' => ['success' => ["Unassigned extension from {$affected} device line(s)."]]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('DeviceControler@bulkUnassign error: '
                . $e->getMessage()
                . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while unassigning the selected devices.']]
            ], 500);
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

            $itemUuid = request('itemUuid');

            // 1) Base payload: either an existing user DTO or a “new user” stub
            if ($itemUuid) {
                $device = QueryBuilder::for(Devices::class)
                    ->select([
                        'domain_uuid',
                        'device_uuid',
                        'device_template',
                        'device_label',
                        'device_profile_uuid',
                        'device_address',
                        'device_vendor',
                    ])
                    ->with(['lines' => function ($query) {
                        $query->select('device_line_uuid', 'line_number', 'device_uuid', 'auth_id', 'domain_uuid');
                        // ->with([
                        //     'extension' => function ($q) use ($currentDomain) {
                        //         $q->select('extension_uuid', 'extension', 'effective_caller_id_name')
                        //             ->where('domain_uuid', $currentDomain);
                        //     },

                        // ]);
                    }])
                    ->with(['profile' => function ($query) {
                        $query->select('device_profile_uuid', 'device_profile_name', 'device_profile_description');
                    }])
                    ->with(['cloudProvisioning' => function ($query) {
                        $query->select('uuid', 'device_uuid', 'status');
                    }])
                    ->whereKey($itemUuid)
                    ->firstOrFail();

                $deviceDto = DeviceData::from($device);
                $updateRoute = route('devices.update', ['device' => $itemUuid]);
            } else {
                // New device defaults
                $deviceDto     = new DeviceData(
                    device_uuid: '',
                    device_profile_uuid: '',
                    device_address: '',
                    device_template: '',
                );
                $updateRoute = null;
            }

            // $device = $this->model::find(request('itemUuid'));

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

            $routes = [
                'store_route' => route('devices.store'),
                'assign_route' => route('devices.assign'),
                // 'unassign_route' => route('devices.store'),
            ];

            $lines = [];
            if ($deviceDto) {
                $lines = DeviceLines::where('device_uuid', request('itemUuid'))
                    ->get([
                        'device_line_uuid',
                        'line_number',
                        'user_id',
                        'auth_id',
                        'display_name',
                        'shared_line',
                        'server_address',
                        'server_address_primary',
                        'server_address_secondary',
                        'sip_port',
                        'sip_transport',
                        'register_expires',
                        'domain_uuid',
                    ])
                    ->map(function ($line) use ($deviceDto) {
                        if ($line->shared_line) {
                            $line->line_type_id = 'sharedline';
                        } else {
                            $vendorLineTypes = [
                                'yealink'     => '15',
                                'polycom'     => 'line',
                                'grandstream' => 'line',
                                // Add more vendors here as needed
                            ];

                            // Use the mapped value, or default to 'line'
                            $line->line_type_id = $vendorLineTypes[$deviceDto->device_vendor] ?? 'line';
                        }
                        return $line;
                    });

                // logger($lines);

                $routes = array_merge($routes, [
                    'update_route' => $updateRoute,
                ]);
            }

            $lineKeyTypes = [];
            if ($deviceDto) {
                if ($deviceDto->device_vendor == 'yealink') {
                    $lineKeyTypes = LineKeyTypesService::getYealinkKeyTypes();
                } else if ($deviceDto->device_vendor == 'polycom') {
                    $lineKeyTypes = LineKeyTypesService::getPolycomKeyTypes();
                } else {
                    $lineKeyTypes = LineKeyTypesService::getGenericKeyTypes();
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

            $cloudProviders = [
                [
                    'name' => 'Polycom',
                    'icon' => 'CloudIcon',
                    'slug' => 'polycom',
                ],
            ];
            $defaultLineOptions = [
                'server_address' => 'admin.localhost',
                'server_address_primary' => get_domain_setting('server_address_primary'),
                'server_address_secondary' => get_domain_setting('server_address_secondary'),
                'sip_port' => get_domain_setting('line_sip_port'),
                'sip_transport' => get_domain_setting('line_sip_transport'),
                'register_expires' => get_domain_setting('line_register_expires'),
                'domain_uuid' => $domain_uuid,
            ];

            // Construct the itemOptions object
            $itemOptions = [
                'item' => $deviceDto ?? null,
                'templates' => getVendorTemplateCollection(),
                'profiles' => getProfileCollection($domain_uuid),
                'extensions' => $extensionOptions,
                'domains' => $domainOptions,
                'lines' => $lines,
                'line_key_types' => $lineKeyTypes,
                'sip_transport_types' => $sipTransportTypes,
                'default_line_options' => $defaultLineOptions,
                'cloud_providers' => $cloudProviders,
                'routes' => $routes,
                'permissions' => $this->getUserPermissions(),
                // Define options for other fields as needed
            ];

            return $itemOptions;
        } catch (\Exception $e) {
            logger('DeviceController@getItemOptions error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
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

    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['device_key_create'] = userCheckPermission('device_key_add');
        $permissions['device_key_update'] = userCheckPermission('device_key_edit');
        $permissions['device_key_destroy'] = userCheckPermission('device_key_delete');


        return $permissions;
    }
}
