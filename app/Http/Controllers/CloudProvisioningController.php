<?php

namespace App\Http\Controllers;

use App\Http\Requests\PairZtpOrganizationRequest;
use App\Http\Requests\StoreZtpOrganizationRequest;
use App\Http\Requests\UpdatePolycomApiTokenRequest;
use App\Http\Requests\UpdateZtpOrganizationRequest;
use App\Models\DefaultSettings;
use App\Models\Devices;
use App\Models\Domain;
use App\Models\DomainSettings;
use App\Services\Interfaces\ZtpProviderInterface;
use App\Services\PolycomZtpProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
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

    public function index(): void
    {

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
        $data = $this->model::query();
        // Get all domains with 'domain_enabled' set to 'true' and eager load settings
        $data->where('domain_enabled', 'true')
            ->with([
                'settings' => function ($query) {
                    $query->select('domain_uuid', 'domain_setting_uuid', 'domain_setting_category',
                        'domain_setting_subcategory', 'domain_setting_value')
                        ->where('domain_setting_category', 'cloud provision')
                        ->where('domain_setting_subcategory', 'polycom_ztp_profile_id')
                        ->where('domain_setting_enabled', true);
                }
            ]);

        $data->select(
            'domain_uuid',
            'domain_name',
            'domain_description',
        );

        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter".ucfirst($field))) {
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
                        $query->where($nestedField, 'ilike', '%'.$value.'%');
                    });
                } else {
                    // Direct field
                    $query->orWhere($field, 'ilike', '%'.$value.'%');
                }
            }
        });
    }

    public function getItemOptions(PolycomZtpProvider $polycomZtpProvider)
    {
        $this->polycomZtpProvider = $polycomZtpProvider;
        try {
            $item_uuid = request('item_uuid'); // Retrieve item_uuid from the request

            $navigation = [
                [
                    'name' => 'Organization',
                    'icon' => 'BuildingOfficeIcon',
                    'slug' => 'organization',
                ],
                [
                    'name' => 'Provisioning',
                    'icon' => 'SyncAltIcon',
                    'slug' => 'provisioning',
                ],
            ];

            $dhcpOption60TypeList = [
                ['value' => 'ASCII', 'name' => 'ASCII'],
                ['value' => 'BINARY', 'name' => 'BINARY'],
            ];

            $dhcpBootServerOptionList = [
                ['value' => 'OPTION66', 'name' => 'OPTION66'],
                ['value' => 'CUSTOM', 'name' => 'CUSTOM'],
                ['value' => 'STATIC', 'name' => 'STATIC'],
                ['value' => 'CUSTOM_OPTION66', 'name' => 'CUSTOM_OPTION66'],
            ];

            $locales = [
                ['value' => 'Chinese_China', 'name' => 'Chinese_China'],
                ['value' => 'Chinese_Taiwan', 'name' => 'Chinese_Taiwan'],
                ['value' => 'Danish_Denmark', 'name' => 'Danish_Denmark'],
                ['value' => 'Dutch_Netherlands', 'name' => 'Dutch_Netherlands'],
                ['value' => 'English_Canada', 'name' => 'English_Canada'],
                ['value' => 'English_United_Kingdom', 'name' => 'English_United_Kingdom'],
                ['value' => 'English_United_States', 'name' => 'English_United_States'],
                ['value' => 'French_France', 'name' => 'French_France'],
                ['value' => 'German_Germany', 'name' => 'German_Germany'],
                ['value' => 'Italian_Italy', 'name' => 'Italian_Italy'],
                ['value' => 'Japanese_Japan', 'name' => 'Japanese_Japan'],
                ['value' => 'Korean_Korea', 'name' => 'Korean_Korea'],
                ['value' => 'Norwegian_Norway', 'name' => 'Norwegian_Norway'],
                ['value' => 'Polish_Poland', 'name' => 'Polish_Poland'],
                ['value' => 'Portuguese_Portugal', 'name' => 'Portuguese_Portugal'],
                ['value' => 'Russian_Russia', 'name' => 'Russian_Russia'],
                ['value' => 'Slovenian_Slovenia', 'name' => 'Slovenian_Slovenia'],
                ['value' => 'Spanish_Spain', 'name' => 'Spanish_Spain'],
                ['value' => 'Swedish_Sweden', 'name' => 'Swedish_Sweden'],
            ];

            // Check if item_uuid exists to find an existing model
            if ($item_uuid) {
                // Find existing model by item_uuid
                $model = $this->model
                    ->select(
                        'domain_uuid',
                        'domain_name',
                        'domain_description',
                    )
                    ->with([
                        'settings' => function ($query) {
                            $query->select('domain_uuid', 'domain_setting_uuid', 'domain_setting_category',
                                'domain_setting_subcategory', 'domain_setting_value')
                                ->where('domain_setting_category', 'cloud provision')
                                ->where('domain_setting_subcategory', 'polycom_ztp_profile_id')
                                ->where('domain_setting_enabled', true);
                        }
                    ])->where($this->model->getKeyName(), $item_uuid)->first();

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

                // If model doesn't exist throw an error
                if (!$model) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }
            } else {
                $model = null;
            }

            $settings = $this->getProvisioningSettings($model->domain_uuid ?? null);

            $permissions = $this->getUserPermissions();

            if ($model && $model->org_id) {
                $organization = $this->polycomZtpProvider->getOrganization($model->org_id);
            }

            // We have to remove the password from the response if the user isn't permitted to see it
            if (!$permissions['manage_cloud_provisioning_show_password']) {
                $settings['http_auth_password'] = null;
            }

            // Construct the itemOptions object
            return [
                'navigation' => $navigation,
                'model' => $model ?? null,
                'organization' => $organization ?? null,
                'orgId' => $organization->id ?? null,
                'dhcp_option_60_type_list' => $dhcpOption60TypeList,
                'dhcp_boot_server_option_list' => $dhcpBootServerOptionList,
                'settings' => $settings,
                'locales' => $locales,
                'permissions' => $permissions,
                'tenants' => $this->getData()
            ];
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage()." at ".$e->getFile().":".$e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to fetch item details'], 'server2' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Retrieve the Polucom API token from DefaultSettings.
     *
     * @return JsonResponse
     */
    public function getToken(): JsonResponse
    {
        try {
            // Retrieve the API token from DefaultSettings
            $token = DefaultSettings::where([
                ['default_setting_category', '=', 'cloud provision'],
                ['default_setting_subcategory', '=', 'polycom_api_token'],
                ['default_setting_enabled', '=', 'true'],
            ])->value('default_setting_value');

            return response()->json([
                'success' => true,
                'token' => $token,
            ]); // 200 OK with the token value
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to retrieve API Token. Check logs for more details']],
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    public function show(Domain $domain)
    {
        //
    }


    /**
     * Update or create the Polycom API token in DefaultSettings.
     *
     * @param UpdatePolycomApiTokenRequest $request
     * @return JsonResponse
     */
    public function updateToken(UpdatePolycomApiTokenRequest $request): JsonResponse
    {
        $inputs = $request->validated();

        try {
            // Update or create the Polycom API token in DefaultSettings
            DefaultSettings::updateOrCreate(
                [
                    'default_setting_category' => 'cloud provision',
                    'default_setting_subcategory' => 'polycom_api_token',
                ],
                [
                    'default_setting_name' => 'text',
                    'default_setting_value' => $inputs['token'], // Use the validated token input
                    'default_setting_enabled' => 'true', // Ensure the setting is enabled
                ]
            );

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['API Token was successfully updated']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to update API Token. Check logs for more details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function getUserPermissions(): array
    {
        $permissions = [];
        $permissions['manage_cloud_provisioning_show_password'] = userCheckPermission('cloud_provisioning_show_password');
        return $permissions;
    }

    /**
     * Submit API request to ZTP to create a new organization
     *
     * @param  StoreZtpOrganizationRequest  $request
     * @param  PolycomZtpProvider  $polycomZtpProvider
     * @return JsonResponse
     */
    public function createOrganization(
        StoreZtpOrganizationRequest $request,
        PolycomZtpProvider $polycomZtpProvider
    ): JsonResponse {
        $this->polycomZtpProvider = $polycomZtpProvider;

        $inputs = $request->validated();

        $inputs['enabled'] = true;

        try {
            // Fill up the password from default settings, if it's not provided within the request payload
            $defaultSettings = $this->getProvisioningSettings($inputs['domain_uuid']);
            if(!$inputs['provisioning_server_password']) {
                $inputs['provisioning_server_password'] = $defaultSettings['http_auth_password'];
            }

            // Send API request to create organization
            $organizationId = $this->polycomZtpProvider->createOrganization($inputs);

            // Check for existing records
            $existingSetting = DomainSettings::where('domain_uuid', $inputs['domain_uuid'])
                ->where('domain_setting_category', 'cloud provision')
                ->where('domain_setting_subcategory', 'polycom_ztp_profile_id')
                ->first();

            if ($existingSetting) {
                // Delete the existing record
                $existingSetting->delete();
            }

            // Save the new record
           DomainSettings::create([
                'domain_uuid' => $inputs['domain_uuid'],
                'domain_setting_category' => 'cloud provision',
                'domain_setting_subcategory' => 'polycom_ztp_profile_id',
                'domain_setting_name' => 'text',
                'domain_setting_value' => $organizationId,
                'domain_setting_enabled' => true,
            ]);

            // Return a JSON response indicating success
            return response()->json([
                'org_id' => $organizationId,
                'messages' => ['success' => ['Organization successfully activated']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage()." at ".$e->getFile().":".$e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => [
                    'server' => ['Unable to activate organization. Check logs for more details'],
                    'server2' => [$e->getMessage()]
                ]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function pairOrganization(PairZtpOrganizationRequest $request)
    {
        // Extract data from the request
        $orgId = $request->input('org_id');
        $domainUuid = $request->input('domain_uuid');

        try {
            // Store or update the domain setting record
            $domainSettings = DomainSettings::updateOrCreate(
                [
                    'domain_uuid' => $domainUuid,
                    'domain_setting_category' => 'cloud provision',
                    'domain_setting_subcategory' => 'polycom_ztp_profile_id',
                ],
                [
                    'domain_setting_name' => 'text',
                    'domain_setting_value' => $orgId,
                    'domain_setting_enabled' => true,
                ]
            );

            // Check if the record was saved successfully
            if (!$domainSettings) {
                throw new \Exception('Unable to connect this organization');
            }

            return response()->json([
                'messages' => ['success' => ['Connection updated successfully']]
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'status' => 500,
                'error' => [
                    'message' => 'An unexpected error occurred. Please try again later.',
                ],
            ]);
        }
    }

    /**
     * Submit API request to ZTP to create a new organization
     *
     * @param  UpdateZtpOrganizationRequest  $request
     * @param  PolycomZtpProvider  $polycomZtpProvider
     * @return JsonResponse
     */
    public function updateOrganization(
        UpdateZtpOrganizationRequest $request,
        PolycomZtpProvider $polycomZtpProvider
    ): JsonResponse {
        $this->polycomZtpProvider = $polycomZtpProvider;

        $inputs = $request->validated();

        $inputs['enabled'] = true;

        try {
            // Fill up the password from default settings, if it's not provided within the request payload
            $defaultSettings = $this->getProvisioningSettings($inputs['domain_uuid']);
            if(!$inputs['provisioning_server_password']) {
                $inputs['provisioning_server_password'] = $defaultSettings['http_auth_password'];
            }

            // Send API request to update organization
            $this->polycomZtpProvider->updateOrganization($inputs['organization_id'], $inputs);

            // Return a JSON response indicating success
            return response()->json([
                'org_id' => $inputs['organization_id'],
                'messages' => ['success' => ['Organization successfully updated']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage()." at ".$e->getFile().":".$e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to update organization. Check logs for more details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Submit request to destroy organization to ZTP
     *
     * @return JsonResponse
     */
    public function destroyOrganization(PolycomZtpProvider $polycomZtpProvider)
    {
        $this->polycomZtpProvider = $polycomZtpProvider;

        try {
            // Get Org ID from database
            $domain_uuid = request('domain_uuid');
            $org_id = $this->polycomZtpProvider->getOrgIdByDomainUuid($domain_uuid);

            // Remove local references from the database
            DomainSettings::where('domain_uuid', $domain_uuid)
                ->where('domain_setting_category', 'cloud provision')
                ->where('domain_setting_subcategory', 'polycom_ztp_profile_id')
                ->delete();

            if (!$org_id) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['Organization ID not found for the given domain.']]
                ], 404); // 404 Not Found
            }

            // Delete the organization
            $deleteResponse = $this->polycomZtpProvider->deleteOrganization($org_id);

            if ($deleteResponse) {
                return response()->json([
                    'messages' => ['success' => ['Organization was successfully deleted.']]
                ], 200); // 200 OK
            }

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to delete the organization.']]
            ], 500); // 500 Internal Server Error

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500); // 500 Internal Server Error
        }
    }

    /**
     * @param  PolycomZtpProvider  $polycomZtpProvider
     * @return JsonResponse|Collection
     */
    public function getOrganizations(PolycomZtpProvider $polycomZtpProvider): JsonResponse|Collection
    {
        $this->polycomZtpProvider = $polycomZtpProvider;

        try {
            $organizations = $this->polycomZtpProvider->getOrganizations();

            return collect($organizations)->map(function ($org) {
                return [
                    'name' => "{$org->name} (id: {$org->id})",
                    'value' => $org->id,
                ];
            });
        } catch (\Exception $e) {
            logger($e->getMessage()." at ".$e->getFile().":".$e->getLine());
            return response()->json([
                'error' => [
                    'message' => $e->getMessage(),
                ],
            ], 404);
        }
    }

    /**
     * Retrieves the status of devices including their provisioning status, errors, and corresponding cloud data.
     *
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        try {
            $requestedItems = request('items');
            $model = new Devices();
            $items = $model::whereIn($model->getKeyName(), $requestedItems)->get();
            $supportedProviders = [];
            $localStatuses = [];

            // Group devices by their providers
            foreach ($items as $item) {
                /** @var Devices $item */
                if ($item->hasSupportedCloudProvider()) {
                    $provider = get_class($item->getCloudProvider());
                    $supportedProviders[$provider][] = $item->device_address;
                    $localStatus = $item->cloudProvisioningStatus()->first();
                    if ($localStatus) {
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
                    // Get device list from ZTP
                    $cloudDevicesData = $providerInstance->getDevices();

                    foreach ($items as $item) {
                        // Retrieve cloud device data for the current device
                        $cloudDeviceData = $cloudDevicesData[$item->device_address] ?? null;

                        // Determine if the device is provisioned
                        $provisioned = $cloudDeviceData && !empty($cloudDeviceData['profileid']);

                        if ($provisioned) {
                            // If provisioned, add provisioned status and data without errors
                            $devicesData[] = [
                                'device_uuid' => $item->device_uuid,
                                'status' => 'provisioned',
                                'error' => null,
                                'data' => $cloudDeviceData,
                            ];
                        } else {
                            // If not provisioned, retrieve status and error from localStatuses
                            $devicesData[] = [
                                'device_uuid' => $item->device_uuid,
                                'status' => $localStatuses[$providerClass][$item->device_address]['status'] ?? 'not_provisioned',
                                'error' => $localStatuses[$providerClass][$item->device_address]['error'] ?? null,
                                'data' => $cloudDeviceData,
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
                            $item->getCloudProviderOrganizationId()
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

    /**
     * Get provisioning default settings
     *
     * @param  string  $domain_uuid  Unique identifier for the domain to fetch provisioning settings.
     * @return array  An array of provisioning settings, prioritizing domain-level values over defaults.
     */
    private function getProvisioningSettings($domain_uuid): array
    {
        // Fetch all domain settings for the given domain_uuid
        $domainSettings = DomainSettings::where('domain_uuid', $domain_uuid)
            ->where('domain_setting_category', 'provision')
            ->where('domain_setting_enabled', true)
            ->whereIn('domain_setting_subcategory', ['http_auth_username', 'http_auth_password', 'polycom_provision_url'])
            ->pluck('domain_setting_value', 'domain_setting_subcategory');


        // Fetch all default settings
        $defaultSettings = DefaultSettings::where('default_setting_enabled', true)
            ->where('default_setting_category', 'provision')
            ->whereIn('default_setting_subcategory', ['http_auth_username', 'http_auth_password', 'polycom_provision_url'])
            ->pluck('default_setting_value', 'default_setting_subcategory');

        // Merge settings, prioritizing domain-level settings
        return $defaultSettings->merge($domainSettings)->toArray();
    }
}
