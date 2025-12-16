<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
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
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Requests\BulkUpdateDeviceRequest;
use App\Services\DeviceCloudProvisioningService;
use App\Traits\ChecksLimits;

/**
 * The DeviceController class is responsible for handling device-related operations, such as listing, creating, and storing devices.
 *
 * @package App\Http\Controllers
 */
class DeviceController extends Controller
{
    use ChecksLimits;

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

        return Inertia::render(
            $this->viewName,
            [

                'routes' => [
                    'current_page' => route('devices.index'),
                    'data_route' => route('devices.data'),
                    'store' => route('devices.store'),
                    'select_all' => route('devices.select.all'),
                    'bulk_delete' => route('devices.bulk.delete'),
                    'bulk_update' => route('devices.bulk.update'),
                    'item_options' => route('devices.item.options'),
                    'restart' => route('devices.restart'),
                    'cloud_provisioning_item_options' => route('cloud-provisioning.item.options'),
                    'cloud_provisioning_get_token' => route('cloud-provisioning.token.get'),
                    'cloud_provisioning_update_api_token' => route('cloud-provisioning.token.update'),
                    'duplicate' => route('devices.duplicate'),

                ]
            ]
        );
    }

    /**
     * Duplicate the specified Device.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function duplicate(Request $request)
    {
        // 1. Validate Input
        $request->validate([
            'uuid' => 'required|uuid|exists:v_devices,device_uuid',
            'new_mac_address' => 'required|string', // Ensure we receive the new MAC
        ]);

        // 2. Permission Check
        if (!userCheckPermission('device_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        try {
            DB::beginTransaction();

            // 3. Sanitize MAC Address
            // Allow: 00-00, 00:00, 0000. Strip non-hex chars and lowercase.
            $rawMac = $request->input('new_mac_address');
            $cleanMac = strtolower(preg_replace('/[^0-9a-f]/i', '', $rawMac));

            // Basic length check
            if (strlen($cleanMac) !== 12) {
                throw new \Exception("Invalid MAC Address format. It must contain 12 hexadecimal characters.");
            }

            // Check uniqueness
            $exists = $this->model::where('device_address', $cleanMac)
                ->where('domain_uuid', session('domain_uuid'))
                ->exists();
                
            if ($exists) {
                throw new \Exception("Device with this MAC address already exists.");
            }

            // 4. Fetch Original
            $original = $this->model::where('device_uuid', $request->uuid)
                ->with(['lines', 'settings'])
                ->firstOrFail();

            // 5. Replicate Parent
            $newDevice = $original->replicate();
            $newDevice->device_uuid = Str::uuid();
            $newDevice->device_label = $original->device_label . ' (Copy)';
            $newDevice->device_address = $cleanMac; // Set the new sanitized MAC
            
            $newDevice->save();

            // 6. Replicate Lines
            if ($original->lines) {
                foreach ($original->lines as $line) {
                    $newLine = $line->replicate();
                    $newLine->device_line_uuid = Str::uuid();
                    $newLine->device_uuid = $newDevice->device_uuid;
                    $newLine->save();
                }
            }

            // 7. Replicate Settings
            if ($original->settings) {
                foreach ($original->settings as $setting) {
                    $newSetting = $setting->replicate();
                    $newSetting->device_setting_uuid = Str::uuid();
                    $newSetting->device_uuid = $newDevice->device_uuid;
                    $newSetting->save();
                }
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Device duplicated successfully.']],
                'device_uuid' => $newDevice->device_uuid
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            // logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            // Return the specific error message if it's one of our validations
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function getData()
    {
        $perPage = 50;
        $currentDomain = session('domain_uuid');

        // If the filter is not present, assign default value before QueryBuilder
        if (!request()->has('filter.showGlobal')) {
            request()->merge([
                'filter' => array_merge(
                    request()->input('filter', []),
                    ['showGlobal' => false]
                ),
            ]);
        }

        $devices = QueryBuilder::for(Devices::class)
            ->select([
                'domain_uuid',
                'device_uuid',
                'device_template',
                'device_template_uuid',
                'device_label',
                'device_profile_uuid',
                'device_address',
                'device_description',
                'device_provisioned_method',
                'device_provisioned_date',

            ])
            // allow ?filter[username]=foo or ?filter[user_email]=bar
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    // Normalize MAC like "00:04:F2-3A:5B:C7" -> "0004f23a5bc7"
                    // This strips ':' and '-' (and any non-hex) and lowercases.
                    $norm = strtolower(preg_replace('/[^0-9a-f]/i', '', $needle));

                    $query->where(function ($q) use ($needle, $norm) {
                        // 1) device_address (DB stores normalized 12-hex)
                        $q->where(function ($q2) use ($needle, $norm) {
                            // partial match on normalized MAC
                            if ($norm !== '') {
                                $q2->orWhereRaw('lower(device_address) LIKE ?', ["%{$norm}%"]);

                                // exact match when a full 12-hex MAC was provided
                                if (strlen($norm) === 12) {
                                    $q2->orWhereRaw('lower(device_address) = ?', [$norm]);
                                }
                            }
                        })

                        // 2) free-text on other columns (keep raw needle to preserve text searches)
                        ->orWhere('device_template', 'ilike', "%{$needle}%")
                        ->orWhereHas('profile', function ($q2) use ($needle) {
                            $q2->where('device_profile_name', 'ilike', "%{$needle}%");
                        })
                        ->orWhereHas('lines.extension', function ($q3) use ($needle) {
                            $q3->where('extension', 'ilike', "%{$needle}%")
                            ->orWhere('effective_caller_id_name', 'ilike', "%{$needle}%");
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
                $query->select('uuid', 'device_uuid', 'last_action', 'status');
            }])
            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description');
            }])
            ->with(['template' => function ($query) {
                $query->select('template_uuid', 'domain_uuid', 'vendor','name');
            }])

            ->allowedSorts(['device_address'])
            ->defaultSort('device_address')
            ->paginate($perPage);

        // wrap in DTO
        $devicesDto = DeviceData::collect($devices);

        // logger( $devicesDto);

        return $devicesDto;

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
                            'outbound_proxy_primary' => $line['outbound_proxy_primary'] ?? get_domain_setting('outbound_proxy_primary'),
                            'outbound_proxy_secondary' => $line['outbound_proxy_secondary'] ?? get_domain_setting('outbound_proxy_secondary') ,
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

        // logger($inputs);

        if (!$device) {
            return response()->json([
                'success' => false,
                'errors' => ['model' => ['Device not found']]
            ], 404);
        }

        try {
            DB::beginTransaction();

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
                            'server_address' => $line['server_address'],
                            'server_address_primary' => $line['server_address_primary'],
                            'server_address_secondary' => $line['server_address_secondary'],
                            'outbound_proxy_primary' => $line['outbound_proxy_primary'],
                            'outbound_proxy_secondary' => $line['outbound_proxy_secondary'],
                            'display_name' => $line['display_name'],
                            'user_id' => $extension ? $extension->extension : null,
                            'auth_id' => $extension ? $extension->extension : $line['auth_id'],
                            'label' => $line['display_name'],
                            'password' => $extension ? $extension->password : null,
                            'sip_port' => $line['sip_port'],
                            'sip_transport' => $line['sip_transport'],
                            'register_expires' => $line['register_expires'],
                            'shared_line' => $line['line_type_id'] == 'sharedline' ? '1' : '',
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

            // Create/update device settings (mirror the device_keys pattern)
            if (array_key_exists('device_settings', $inputs)) {
                if (empty($inputs['device_settings'])) {
                    // Field present but empty → remove all settings for this device
                    $device->settings()->delete();
                } else {
                    // Field present with items → clear and recreate
                    $device->settings()->delete();

                    foreach ($inputs['device_settings'] as $item) {
                        $payload = [
                            'device_uuid'                => $device->device_uuid,
                            'domain_uuid'                => $device->domain_uuid,

                            // Defaults match common FusionPBX conventions; override if sent in payload
                            'device_setting_category'    => $item['device_setting_category'] ?? null,
                            'device_setting_subcategory' => $item['device_setting_subcategory'] ?? null,
                            'device_setting_name'        => $item['device_setting_name']        ?? null,
                            'device_setting_value'       => $item['device_setting_value']       ?? null,
                            'device_setting_enabled'     => $item['device_setting_enabled'] ?? 'false',
                            'device_setting_description' => $item['device_setting_description'] ?? null,
                        ];

                        $device->settings()->create($payload);
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

        // Check for limits
        if (!$itemUuid) {
            if ($resp = $this->enforceLimit(
                'devices',
                \App\Models\Devices::class
            )) {
                return $resp;
            }
        }

            $routes = [];

            // 1) Base payload: either an existing user DTO or a “new user” stub
            if ($itemUuid) {
                $device = QueryBuilder::for(Devices::class)
                    ->select([
                        'domain_uuid',
                        'device_uuid',
                        'device_template',
                        'device_template_uuid',
                        'device_label',
                        'device_profile_uuid',
                        'device_address',
                        'serial_number',
                        'device_vendor',
                        'device_description',
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
                    ->with(['settings' => function ($query) {
                        $query->select('device_setting_uuid', 'device_uuid','device_setting_subcategory', 'device_setting_value', 'device_setting_enabled', 'device_setting_description');
                    }])
                    ->whereKey($itemUuid)
                    ->firstOrFail();

                $deviceDto = DeviceData::from($device);

                $routes = array_merge($routes, [
                    'update_route' => route('devices.update', ['device' => $itemUuid]),
                    'cloud_provisioning_status_route' => route('cloud-provisioning.status', ['device' => $itemUuid]),
                    'cloud_provisioning_reset_route' => route('cloud-provisioning.reset', ['device' => $itemUuid]),
                ]);
            } else {
                // New device defaults
                $deviceDto     = new DeviceData(
                    device_uuid: '',
                );
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

            $routes = array_merge($routes, [
                'store_route' => route('devices.store'),
                'assign_route' => route('devices.assign'),
                'cloud_provisioning_register_route' => route('cloud-provisioning.register'),
                'cloud_provisioning_deregister_route' => route('cloud-provisioning.deregister'),
                'bulk_update_route' => route('devices.bulk.update'),
            ]);


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
                        'outbound_proxy_primary',
                        'outbound_proxy_secondary',
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
                'server_address' => session('domain_name'),
                'server_address_primary' => get_domain_setting('server_address_primary'),
                'server_address_secondary' => get_domain_setting('server_address_secondary'),
                'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary'),
                'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary'),
                'sip_port' => get_domain_setting('line_sip_port'),
                'sip_transport' => get_domain_setting('line_sip_transport'),
                'register_expires' => get_domain_setting('line_register_expires'),
                'domain_uuid' => $domain_uuid,
            ];

            $cloudProviderSelector = app()->make(\App\Services\CloudProviderSelector::class);

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
                // Boolean field indicating if a cloud provider exists for this vendor:
                'cloud_provider_available' => $deviceDto && $cloudProviderSelector->getCloudProvider($deviceDto->device_vendor) !== null,
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
    public function bulkUpdate(BulkUpdateDeviceRequest $request)
    {
        $data = $request->validated();

        $ids = $data['items'] ?? [];

        // Remove "items" from the update data, only use the rest as updates
        unset($data['items']);

        // Only continue if there are actually fields to update
        if (empty($ids) || empty($data)) {
            return response()->json([
                'success' => false,
                'errors' => ['input' => ['No devices or fields provided for update.']]
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Handle field transformations here, e.g. device_vendor
            // For example, if "device_template" is present:
            if (array_key_exists('device_template', $data)) {
                $data['device_vendor'] = explode("/", $data['device_template'])[0] ?? null;
                if ($data['device_vendor'] === 'poly') {
                    $data['device_vendor'] = 'polycom';
                }
            }

            Devices::whereIn('device_uuid', $ids)
                ->chunk(10, function ($devices) use ($data) {
                    foreach ($devices as $device) {
                        $device->fill($data);
                        if ($device->isDirty()) {
                            $device->save();
                        }
                    }
                });

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected items updated']],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update selected items']]
            ], 500);
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
                ->with([
                    'lines' => function ($query) {
                        $query->select('device_uuid', 'device_line_uuid');
                    },
                    'cloudProvisioning',
                ])
                ->get([
                    'device_uuid',
                    'domain_uuid',
                    'device_vendor',
                    'device_address',
                ]);

            foreach ($devices as $device) {
                // Delete all related lines for each device
                if ($device->lines) {
                    foreach ($device->lines as $line) {
                        $line->delete();
                    }
                }

                // Delete related cloud provisioning record
                if ($device->cloudProvisioning) {

                    $params = [
                        'device_uuid' => $device->device_uuid,
                        'domain_uuid' => $device->domain_uuid,
                        'device_vendor' => $device->device_vendor,
                        'device_address' => $device->device_address,
                    ];

                    $deregisterJob = (new DeviceCloudProvisioningService)->deregister($params);
                    $resetJob = app(DeviceCloudProvisioningService::class)->reset($params);

                    dispatch($deregisterJob->chain([$resetJob]));
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

            logger('DeviceControler@bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

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
        $permissions['device_key_advanced'] = userCheckPermission('device_key_advanced');
        $permissions['device_address_update'] = userCheckPermission('device_address');
        $permissions['device_template_update'] = userCheckPermission('device_template');
        $permissions['device_domain_update'] = userCheckPermission('device_domain');
        $permissions['manage_device_cloud_provisioning_settings'] = userCheckPermission('manage_device_cloud_provisioning_settings');
        $permissions['device_setting_view'] = userCheckPermission('device_setting_view');
        $permissions['device_setting_add'] = userCheckPermission('device_setting_add');
        $permissions['device_setting_update'] = userCheckPermission('device_setting_edit');
        $permissions['device_setting_destroy'] = userCheckPermission('device_setting_delete');
        $permissions['manage_device_line_primary_server'] = userCheckPermission('device_line_server_address_primary');
        $permissions['manage_device_line_secondary_server'] = userCheckPermission('device_line_server_address_secondary');
        $permissions['manage_device_line_primary_proxy'] = userCheckPermission('device_line_outbound_proxy_primary');
        $permissions['manage_device_line_secondary_proxy'] = userCheckPermission('device_line_outbound_proxy_secondary');
        $permissions['is_superadmin'] = isSuperAdmin();

        return $permissions;
    }
}
