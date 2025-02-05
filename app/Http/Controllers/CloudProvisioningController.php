<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRingotelOrganizationRequest;
use App\Http\Requests\StoreZtpOrganizationRequest;
use App\Http\Requests\UpdateRingotelOrganizationRequest;
use App\Http\Requests\UpdateZtpOrganizationRequest;
use App\Models\Devices;
use App\Models\Domain;
use App\Models\DomainSettings;
use App\Services\Interfaces\ZtpProviderInterface;
use App\Services\PolycomZtpProvider;
use App\Services\RingotelApiService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class CloudProvisioningController extends Controller
{
    public Domain $model;
    public array $filters = [];
    public string $sortField;
    public string $sortOrder;
    protected string $viewName = 'CloudProvisioning';
    protected PolycomZtpProvider $polycomZtpProvider;
    //protected array $searchable = ['source', 'destination', 'message'];

    public function __construct()
    {
        $this->model = new Domain();
    }

    public function index(): \Inertia\Response
    {
        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },
                'routes' => [
                    'current_page' => route('cloud-provisioning.index'),
                    'create_organization' => route('cloud-provisioning.organization.create'),
                    //'update_organization' => route('cloud-provisioning.organization.update'),
                    //'destroy_organization' => route('cloud-provisioning.organization.destroy'),
                    //'pair_organization' => route('cloud-provisioning.organization.pair'),
                    //'get_all_orgs' => route('cloud-provisioning.organization.all'),
                    //'get_api_token' => route('cloud-provisioning.token.get'),
                    //'update_api_token' => route('cloud-provisioning.token.update'),
                    'item_options' => route('cloud-provisioning.item.options'),
                ]
            ]
        );
    }

    public function getData($paginate = 50)
    {

        // Check if search parameter is present and not empty
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'domain_name'); // Default to 'voicemail_id'
        $this->sortOrder = request()->get('sortOrder', 'asc'); // Default to descending

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
        }

        // Add `ztp_status` dynamically
        $data->each(function ($domain) {
            $domain->ztp_status = $domain->settings()
                ->where('domain_setting_category', 'cloud provision')
                ->where('domain_setting_subcategory', 'polycom_ztp_profile_id')
                ->where('domain_setting_enabled', true)
                ->exists() ? 'true' : 'false';
        });

        return $data;
    }

    /**
     * @param  array  $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function builder(array $filters = [])
    {
        $data =  $this->model::query();
        // Get all domains with 'domain_enabled' set to 'true' and eager load settings
        $data->where('domain_enabled', 'true')
            ->with(['settings' => function ($query) {
                $query->select('domain_uuid', 'domain_setting_uuid', 'domain_setting_category', 'domain_setting_subcategory', 'domain_setting_value')
                    ->where('domain_setting_category', 'cloud provision')
                    ->where('domain_setting_subcategory', 'polycom_ztp_profile_id')
                    ->where('domain_setting_enabled', true);
            }]);

        $data->select(
            'domain_uuid',
            'domain_name',
            'domain_description',
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
                if (strpos($field, '.') !== false) {
                    // Nested field (e.g., 'extension.name_formatted')
                    [$relation, $nestedField] = explode('.', $field, 2);

                    $query->orWhereHas($relation, function ($query) use ($nestedField, $value) {
                        $query->where($nestedField, 'ilike', '%' . $value . '%');
                    });
                } else {
                    // Direct field
                    $query->orWhere($field, 'ilike', '%' . $value . '%');
                }
            }
        });
    }

    public function getItemOptions(/*RingotelApiService $ringotelApiService*/)
    {
        //$this->ztpApiService = $ringotelApiService;
        logger('test');
        try {
            $item_uuid = request('item_uuid'); // Retrieve item_uuid from the request

            $navigation = [
                [
                    'name' => 'Organization',
                    'icon' => 'BuildingOfficeIcon',
                    'slug' => 'organization',
                ],
                [
                    'name' => 'Connections',
                    'icon' => 'SyncAltIcon',
                    'slug' => 'connections',
                ],
            ];

            $conn_navigation = [
                [
                    'name' => 'Settings',
                    'icon' => 'Cog6ToothIcon',
                    'slug' => 'settings',
                ],
                [
                    'name' => 'Features',
                    'icon' => 'AdjustmentsHorizontalIcon',
                    'slug' => 'features',
                ],
                [
                    'name' => 'PBX Features',
                    'icon' => 'SettingsApplications',
                    'slug' => 'pbx_features',
                ],
                // [
                //     'name' => 'SMS Settings',
                //     'icon' => 'AdjustmentsHorizontalIcon',
                //     'slug' => 'sms_settings',
                // ],
                // [
                //     'name' => 'Screen Pop-ups',
                //     'icon' => 'AdjustmentsHorizontalIcon',
                //     'slug' => 'screen_popups',
                // ],
                // [
                //     'name' => 'Visual Call Park',
                //     'icon' => 'AdjustmentsHorizontalIcon',
                //     'slug' => 'screen_popups',
                // ],
                // [
                //     'name' => 'Speed Dial',
                //     'icon' => 'AdjustmentsHorizontalIcon',
                //     'slug' => 'speed_dial',
                // ],
                // [
                //     'name' => 'BLFs',
                //     'icon' => 'AdjustmentsHorizontalIcon',
                //     'slug' => 'blfs',
                // ],
                // [
                //     'name' => 'Web Pages',
                //     'icon' => 'AdjustmentsHorizontalIcon',
                //     'slug' => 'web_pages',
                // ],
            ];


            $routes = [
                'create_connection' => route('apps.connection.create'),
                'update_connection' => route('apps.connection.update'),
                'delete_connection' => route('apps.connection.destroy'),
                'sync_users' => route('apps.users.sync'),
            ];

            /*$regions = $this->getRegions();

            $packages = [
                ['value' => '1', 'name' => 'Essentials Package'],
                ['value' => '2', 'name' => 'Pro Package'],
            ];

            $protocols = [
                ['value' => 'sip', 'name' => 'UDP'],
                ['value' => 'sip-tcp', 'name' => 'TCP'],
                ['value' => 'sips', 'name' => 'TLS'],
                ['value' => 'DNS-NAPTR', 'name' => 'DNS-NAPTR'],
            ];*/

            // Check if item_uuid exists to find an existing model
            if ($item_uuid) {
                // Find existing model by item_uuid
                $model = $this->model
                    ->select(
                        'domain_uuid',
                        'domain_name',
                        'domain_description',
                    )
                    ->with(['settings' => function ($query) {
                        $query->select('domain_uuid', 'domain_setting_uuid', 'domain_setting_category', 'domain_setting_subcategory', 'domain_setting_value')
                            ->where('domain_setting_category', 'app shell')
                            ->where('domain_setting_subcategory', 'org_id')
                            ->where('domain_setting_enabled', true);
                    }])->where($this->model->getKeyName(), $item_uuid)->first();

                if ($model) {
                    // Transform settings into org_id
                    $model->org_id = $model->settings->first()->domain_setting_value ?? null;
                    unset($model->settings); // Remove settings relationship if not needed
                }

                $model->ztp_status = $model->settings()
                    ->where('domain_setting_category', 'cloud provision')
                    ->where('domain_setting_subcategory', 'polycom_ztp_profile_id')
                    ->where('domain_setting_enabled', true)
                    ->exists() ? 'true' : 'false';
                logger($model);

                // If model doesn't exist throw an error
                if (!$model) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }

                // Add additional navigation item if ringotel status is true
                /*if($model->ringotel_status == 'true'){
                    $navigation[] = [
                        'name' => 'Users',
                        'icon' => 'UsersIcon',
                        'slug' => 'users',
                    ];
                }*/

                $routes = array_merge($routes, []);
            }

            $permissions = $this->getUserPermissions();

            // Get all app settings from Default Settings and overrride with settings saved in Domain Settings
            //$appSettings = $this->getAppSettings($model->domain_uuid ?? null);
            $appSettings = [];
            $appSettings['suggested_ringotel_domain'] = strtolower(str_replace(' ', '', $model->domain_description ?? ''));
            $appSettings['suggested_connection_name'] = 'Primary SIP Profile';

            // Check if `connection_port` is empty and fall back to `line_sip_port`
            if (empty($appSettings['connection_port'])) {
                $appSettings['connection_port'] = get_domain_setting('line_sip_port', $model->domain_uuid ?? null)  ?? null;
            }

            if (!$model->org_id) {
                $connections = [];
            } else {
                //$organization = $this->ringotelApiService->getOrganization($model->org_id);
                //$connections = $this->ringotelApiService->getConnections($model->org_id);
            }

            // Construct the itemOptions object
            $itemOptions = [
                'navigation' => $navigation,
                'conn_navigation' => $conn_navigation,
                'model' => $model ?? null,
                'organization' => $organization ?? null,
                'orgId' => $organization->id ?? null,
                //'regions' => $regions,
                //'packages' => $packages,
                //'protocols' => $protocols,
                'permissions' => $permissions,
                'routes' => $routes,
                'settings' => $appSettings,
                'connections' => $connections,

                // Define options for other fields as needed
            ];

            return $itemOptions;
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to fetch item details'], 'server2' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function getUserPermissions(): array
    {
        //$permissions = [];
        return [];
    }

    /**
     * Submit API request to ZTP to create a new organization
     *
     * @param  StoreZtpOrganizationRequest  $request
     * @param  PolycomZtpProvider  $polycomZtpProvider
     * @return JsonResponse
     */
    public function createOrganization(StoreZtpOrganizationRequest $request, PolycomZtpProvider $polycomZtpProvider): JsonResponse
    {
        $this->polycomZtpProvider = $polycomZtpProvider;

        $inputs = $request->validated();

        try {
            // Send API request to create organization
            $organization = $this->polycomZtpProvider->createOrganization($inputs);

            // Check for existing records
            $existingSetting = DomainSettings::where('domain_uuid', $inputs['domain_uuid'])
                ->where('domain_setting_category', 'app shell')
                ->where('domain_setting_subcategory', 'org_id')
                ->first();

            if ($existingSetting) {
                // Delete the existing record
                $existingSetting->delete();
            }

            // Save the new record
            $domainSetting = DomainSettings::create([
                'domain_uuid' => $inputs['domain_uuid'],
                'domain_setting_category' => 'app shell',
                'domain_setting_subcategory' => 'org_id',
                'domain_setting_name' => 'text',
                'domain_setting_value' => $organization['id'],
                'domain_setting_enabled' => true,
            ]);

            /*
            // Check for existing records
            $existingSetting = DomainSettings::where('domain_uuid', $inputs['domain_uuid'])
                ->where('domain_setting_category', 'mobile_apps')
                ->where('domain_setting_subcategory', 'dont_send_user_credentials')
                ->first();

            if ($existingSetting) {
                $existingSetting->delete();
            }

            $domainSetting = DomainSettings::create([
                'domain_uuid' => $inputs['domain_uuid'],
                'domain_setting_category' => 'mobile_apps',
                'domain_setting_subcategory' => 'dont_send_user_credentials',
                'domain_setting_name' => 'boolean',
                'domain_setting_value' => $inputs['dont_send_user_credentials'],
                'domain_setting_enabled' => true,
                'domain_setting_description' => "Don't include user credentials in the welcome email"
            ]);*/

            // Return a JSON response indicating success
            return response()->json([
                'org_id' => $organization['id'],
                'messages' => ['success' => ['Organization successfully activated']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to activate organization. Check logs for more details'], 'server2' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Submit API request to ZTP to create a new organization
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateOrganization(UpdateZtpOrganizationRequest $request, PolycomZtpProvider $polycomZtpProvider): JsonResponse
    {
        $this->polycomZtpProvider = $polycomZtpProvider;

        $inputs = $request->validated();

        try {
            // Send API request to update organization
            $organization = $this->polycomZtpProvider->updateOrganization($inputs);

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Organization successfully updated']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to update organization. Check logs for more details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Retrieves the status of devices based on the provided request items.
     *
     * @return JsonResponse Returns a JSON response indicating the success or failure of the status retrieval process.
     * The response contains the status, the device data with their provisioning status, any errors encountered,
     * and appropriate HTTP status codes.
     */
    public function status(): JsonResponse
    {
        try {
            $requestedItems = request('items');
            $items = $this->model::whereIn($this->model->getKeyName(), $requestedItems)->get();
            $supportedProviders = [];
            $localStatuses = [];

            // Group devices by their providers
            foreach ($items as $item) {
                /** @var Devices $item */
                if ($item->hasSupportedCloudProvider()) {
                    $provider = get_class($item->getCloudProvider());
                    $supportedProviders[$provider][] = $item->device_address;
                    $localStatus = $item->cloudProvisioningStatus()->first();
                    if($localStatus) {
                        $localStatuses[$provider][$item->device_address] = [
                            'status' => $localStatus->status,
                            'error' => $localStatus->error
                        ];
                    } else {
                        $localStatuses[$provider][$item->device_address] = null;
                    }
                }
            }

            $devicesData = [];

            // Handle each provider
            foreach ($supportedProviders as $providerClass => $ids) {
                try {
                    // Initializing provider instance
                    /** @var ZtpProviderInterface $providerInstance */
                    $providerInstance = new $providerClass();
                    $cloudDevicesData = $providerInstance->listDevices($ids);

                    foreach ($items as $item) {
                        $cloudDeviceData = $cloudDevicesData[$item->device_address] ?? null;
                        $provisioned = $cloudDeviceData && !empty($cloudDeviceData['profileid']);
                        if($provisioned) {
                            $devicesData[] = [
                                'device_uuid' => $item->device_uuid,
                                'status' => 'provisioned',
                                'error' => null,
                                'data' => $cloudDeviceData
                            ];
                        } else {
                            $devicesData[] = [
                                'device_uuid' => $item->device_uuid,
                                'status' => $localStatuses[$providerClass][$item->device_address]['status'] ?? 'not_provisioned',
                                'error' => $localStatuses[$providerClass][$item->device_address]['error'] ?? null,
                                'data' => $cloudDeviceData
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    logger($e);

                    foreach ($ids as $id) {
                        $matchedItem = $items->firstWhere('device_address', $id);
                        $devicesData[] = [
                            'device_uuid' => $matchedItem ? $matchedItem->device_uuid : null,
                            'status' => false,
                            'error' => null, //$e->getMessage(),
                            'data' => null,
                        ];
                    }
                }
            }

            return response()->json([
                'status' => true,
                'devicesData' => $devicesData,
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json([
                'error' => $e->getMessage(),
                'deviceData' => null,
            ], 500);
        }
    }

    /**
     * Registers devices based on the provided request items.
     *
     * @return JsonResponse Returns a JSON response indicating the success or failure of the registration process.
     * The response contains the status, the device data with their respective errors (if any),
     * and appropriate HTTP status codes.
     */
    public function register(): JsonResponse
    {
        try {
            //$cloudProvisioningService = new CloudProvisioningService();
            //Get items info as a collection
            $items = $this->model::whereIn($this->model->getKeyName(), request('items'))->get();
            $devicesData = [];
            foreach ($items as $item) {
                /** @var Devices $item */
                if ($item->hasSupportedCloudProvider()) {
                    try {
                        $cloudProvider = $item->getCloudProvider();
                        $cloudProvider->createDevice(
                            $item->device_address,
                            $item->getCloudProviderOrganisationId()
                        );
                        $provisioned = true;
                        $error = null;
                    } catch (\Exception $e) {
                        logger($e);
                        $provisioned = false;
                        $error = $e->getMessage();
                    }
                } else {
                    $provisioned = false;
                    $error = 'Unsupported provider';
                }
                $devicesData[] = [
                    'device_uuid' => $item->device_uuid,
                    'provisioned' => $provisioned,
                    'error' => $error,
                ];
            }

            // Return a JSON response indicating success
            return response()->json([
                'status' => true,
                'devicesData' => $devicesData,
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage());
            // Handle any other exception that may occur
            return response()->json([
                'error' => $e->getMessage(),
                'deviceData' => null
            ], 500);
        }
    }

    /**
     * De-registers devices based on the provided request items.
     *
     * @return JsonResponse Returns a JSON response indicating the success or failure of the de-registration process.
     * The response contains the status, the device data with their respective errors (if any),
     * and appropriate HTTP status codes.
     */
    public function deregister(): JsonResponse
    {
        try {
            //$cloudProvisioningService = new CloudProvisioningService();
            //Get items info as a collection
            $items = $this->model::whereIn($this->model->getKeyName(), request('items'))->get();
            $devicesData = [];
            foreach ($items as $item) {
                /** @var Devices $item */
                if ($item->hasSupportedCloudProvider()) {
                    try {
                        $cloudProvider = $item->getCloudProvider();
                        $cloudProvider->deleteDevice($item->device_address);
                        $error = null;
                    } catch (\Exception $e) {
                        logger($e);
                        $error = $e->getMessage();
                    }
                } else {
                    $error = 'Unsupported provider';
                }
                $devicesData[] = [
                    'device_uuid' => $item->device_uuid,
                    'provisioned' => false,
                    'error' => $error
                ];
            }

            // Return a JSON response indicating success
            return response()->json([
                'status' => true,
                'devicesData' => $devicesData,
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage());
            // Handle any other exception that may occur
            return response()->json([
                'error' => $e->getMessage(),
                'deviceData' => null
            ], 500);
        }
    }
}
