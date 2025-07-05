<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Devices;
use App\Data\DomainData;
use App\Models\DomainSettings;
use App\Models\DefaultSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\QueryBuilder;
use App\Services\PolycomCloudProvider;
use App\Models\CloudProvisioningStatus;
use App\Models\DeviceCloudProvisioning;
use App\Services\CloudProviderSelector;
use Illuminate\Support\Facades\Session;
use App\Services\DeviceCloudProvisioningService;
use App\Http\Requests\PairZtpOrganizationRequest;
use App\Http\Requests\StoreZtpOrganizationRequest;
use App\Http\Requests\UpdatePolycomApiTokenRequest;
use App\Http\Requests\UpdateZtpOrganizationRequest;

class DeviceCloudProvisioningController extends Controller
{
    public Domain $model;
    public array $filters = [];
    public string $sortField;
    public string $sortOrder;
    protected string $viewName = 'CloudProvisioning';
    protected PolycomCloudProvider $PolycomCloudProvider;

    //protected array $searchable = ['source', 'destination', 'message'];

    public function __construct()
    {
        $this->model = new Domain();
    }

    public function index(): void {}

    public function getData()
    {
        // TODO: When we have support for other ZTPs, we will need to refine this function to accept a ZTP key and return the status of provisioning
        $domain = $this->builder()->first();
        $domain->ztp_status = $domain->settings()
            ->where('domain_setting_category', 'cloud provision')
            ->where('domain_setting_subcategory', 'polycom_ztp_profile_id')
            ->where('domain_setting_enabled', true)
            ->exists() ? 'true' : 'false';
        return $domain;
    }

    /**
     * @param  array  $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function builder()
    {
        $data = $this->model::query();
        // Get all domains with 'domain_enabled' set to 'true' and eager load settings
        $data->where('domain_enabled', 'true')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->with([
                'settings' => function ($query) {
                    $query->select(
                        'domain_uuid',
                        'domain_setting_uuid',
                        'domain_setting_category',
                        'domain_setting_subcategory',
                        'domain_setting_value'
                    )
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

        return $data;
    }

    public function getItemOptions()
    {

        try {
            $domain_uuid = session('domain_uuid');

            $cloudProviderSelector = app()->make(CloudProviderSelector::class);
            $cloudProvider = $cloudProviderSelector->getCloudProvider(request('provider'));

            if (!$cloudProvider) {
                throw new \Exception('There was an issue retrieving requested data.');
            }

            $providerSettings = $cloudProvider::getSettings();

            if ($domain_uuid) {
                $organization_id = $cloudProvider::getOrgIdByDomainUuid($domain_uuid);
            }

            if ($organization_id) {
                $organization = $cloudProvider->getOrganization($organization_id);
            }

            $routes = [
                'cloud_provisioning_create_organization' => route('cloud-provisioning.organization.create'),
                'cloud_provisioning_update_organization' => route('cloud-provisioning.organization.update'),
                'cloud_provisioning_destroy_organization' => route('cloud-provisioning.organization.destroy'),
                'cloud_provisioning_pair_organization' => route('cloud-provisioning.organization.pair'),
                'cloud_provisioning_get_api_token' => route('cloud-provisioning.token.get'),
                'cloud_provisioning_sync_devices' => route('cloud-provisioning.devices.sync'),
                'cloud_provisioning_get_all_orgs' => route('cloud-provisioning.organization.all'),
            ];




            // if ($model) {
            //     // Transform settings into org_id
            //     $model->org_id = $model->settings->first()->domain_setting_value ?? null;
            //     unset($model->settings); // Remove settings relationship if not needed
            // }

            // $model->ztp_status = $model->settings()
            //     ->where('domain_setting_category', 'cloud provision')
            //     ->where('domain_setting_subcategory', 'polycom_ztp_profile_id')
            //     ->where('domain_setting_enabled', true)
            //     ->exists() ? 'true' : 'false';


            // $settings = $this->getProvisioningSettings($model->domain_uuid ?? null);

            // $permissions = $this->getUserPermissions();

            // if ($model && $model->org_id) {
            //     $organization = $this->PolycomCloudProvider->getOrganization($model->org_id);
            // }

            // Construct the itemOptions object
            return [
                'organization' => $organization ?? null,
                'organization_id' => $organization_id ?? null,
                'provider_settings' => $providerSettings ?? null,
                // 'permissions' => $permissions,
                'routes' => $routes,
            ];
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
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
            $cloudProviderSelector = app()->make(CloudProviderSelector::class);
            $cloudProvider = $cloudProviderSelector->getCloudProvider(request('provider'));

            $token = $cloudProvider->getApiToken();

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

        /**
     * Update or create the Polycom API token in DefaultSettings.
     *
     * @param UpdatePolycomApiTokenRequest $request
     * @return JsonResponse
     */
    public function updateToken(UpdatePolycomApiTokenRequest $request)
    {
        $data = $request->validated();

        try {
            $cloudProviderSelector = app()->make(CloudProviderSelector::class);
            $cloudProvider = $cloudProviderSelector->getCloudProvider($data['provider']);

            $cloudProvider->setApiToken($data['token']);

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

    public function show(Domain $domain)
    {
        //
    }




    public function getUserPermissions(): array
    {
        $permissions = [];
        $permissions['manage_cloud_provisioning_show_credentials'] = userCheckPermission('cloud_provisioning_show_credentials');
        $permissions['manage_polycom_api_token_edit'] = userCheckPermission('polycom_api_token_edit');
        return $permissions;
    }

    /**
     * Submit API request to ZTP to create a new organization
     *
     * @param  StoreZtpOrganizationRequest  $request
     * @param  PolycomCloudProvider  $PolycomCloudProvider
     * @return JsonResponse
     */
    public function createOrganization(
        StoreZtpOrganizationRequest $request,
        PolycomCloudProvider $PolycomCloudProvider
    ): JsonResponse {
        $this->PolycomCloudProvider = $PolycomCloudProvider;

        $inputs = $request->validated();

        $inputs['enabled'] = true;

        try {
            // Populate the credentials from default settings, if it's not provided within the request payload
            $defaultSettings = $this->getProvisioningSettings($inputs['domain_uuid']);
            if (!$inputs['provisioning_server_password']) {
                $inputs['provisioning_server_password'] = $defaultSettings['http_auth_password'];
            }

            if (!$inputs['provisioning_server_username']) {
                $inputs['provisioning_server_username'] = $defaultSettings['http_auth_username'];
            }

            // Send API request to create organization
            $organizationId = $this->PolycomCloudProvider->createOrganization($inputs);

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
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
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
        $domainUuid = session('domain_uuid');

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
                'messages' => ['success' => ['Organizations has been succesfully registered']]
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
     * @param  PolycomCloudProvider  $PolycomCloudProvider
     * @return JsonResponse
     */
    public function updateOrganization(
        UpdateZtpOrganizationRequest $request,
        PolycomCloudProvider $PolycomCloudProvider
    ): JsonResponse {
        $this->PolycomCloudProvider = $PolycomCloudProvider;

        $inputs = $request->validated();

        $inputs['enabled'] = true;

        try {
            // Populate the credentials from default settings, if it's not provided within the request payload
            $defaultSettings = $this->getProvisioningSettings($inputs['domain_uuid']);
            if (!$inputs['provisioning_server_password']) {
                $inputs['provisioning_server_password'] = $defaultSettings['http_auth_password'];
            }

            if (!$inputs['provisioning_server_username']) {
                $inputs['provisioning_server_username'] = $defaultSettings['http_auth_username'];
            }

            // Send API request to update organization
            $this->PolycomCloudProvider->updateOrganization($inputs['organization_id'], $inputs);

            // Return a JSON response indicating success
            return response()->json([
                'org_id' => $inputs['organization_id'],
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
     * Submit request to destroy organization to ZTP
     *
     * @return JsonResponse
     */
    public function destroyOrganization(PolycomCloudProvider $PolycomCloudProvider)
    {
        $this->PolycomCloudProvider = $PolycomCloudProvider;

        try {
            // Get Org ID from database
            $domain_uuid = request('domain_uuid');
            $org_id = $this->PolycomCloudProvider->getOrgIdByDomainUuid($domain_uuid);

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
            $deleteResponse = $this->PolycomCloudProvider->deleteOrganization($org_id);
            if ($deleteResponse) {
                $devices = Devices::where('domain_uuid', $domain_uuid)
                    ->select(
                        'device_uuid',
                        'domain_uuid',
                        'device_address',
                        'device_vendor',
                    )
                    ->get();

                foreach ($devices as $device) {

                    $params = [
                        'device_uuid' => $device->device_uuid,
                        'domain_uuid' => $device->domain_uuid,
                        'device_vendor' => $device->device_vendor,
                        'device_address' => $device->device_address,
                    ];

                    $job = (new DeviceCloudProvisioningService)->deregister($params);
                    if ($job) {
                        dispatch($job);
                    }
                }

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
     * 
     * @return JsonResponse|Collection
     */
    public function getOrganizations()
    {
        $cloudProviderSelector = app()->make(CloudProviderSelector::class);
        $cloudProvider = $cloudProviderSelector->getCloudProvider(request('provider'));


        try {
            $organizations = $cloudProvider->getOrganizations();

            return collect($organizations)->map(function ($org) {
                return [
                    'name' => "{$org->name} (id: {$org->id})",
                    'value' => $org->id,
                ];
            });
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500); // 500 Internal Server Error
        }
    }

    /**
     * Retrieves the cloud provisioning status for specified device
     *
     * @param  string  $device_uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function status($device_uuid)
    {
        $currentDomain = session('domain_uuid');
        try {
            $provisioning = QueryBuilder::for(\App\Models\DeviceCloudProvisioning::query())
                ->where('device_uuid', $device_uuid)
                ->where('domain_uuid', $currentDomain)
                ->first();

            return response()->json([
                'success' => true,
                'data'    => $provisioning,
            ]);
        } catch (\Throwable $e) {
            logger('DeviceCloudProvisioningController@status error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success'  => false,
                'messages' => ['error' => [$e->getMessage()]],
                'data'     => [],
            ], 500);
        }
    }

    /**
     * Deletes the local cloud provisioning entry for the specified device.
     *
     * @param  string  $device_uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset($device_uuid)
    {
        $currentDomain = session('domain_uuid');
        try {
            $provisioning = QueryBuilder::for(\App\Models\DeviceCloudProvisioning::query())
                ->where('device_uuid', $device_uuid)
                ->where('domain_uuid', $currentDomain)
                ->first();

            if (!$provisioning) {
                return response()->json([
                    'success' => false,
                    'messages' => ['error' => ['Provisioning entry not found.']],
                    'data' => [],
                ], 404);
            }

            $provisioning->delete();

            return response()->json([
                'success' => true,
                'messages' => ['success' => ['Provisioning entry has been reset (deleted).']],
                'data' => [],
            ]);
        } catch (\Throwable $e) {
            logger('DeviceCloudProvisioningController@reset error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success'  => false,
                'messages' => ['error' => [$e->getMessage()]],
                'data'     => [],
            ], 500);
        }
    }


    public function syncDevices(): JsonResponse
    {
        // Hardcode the provider for now
        try {
            $domain_uuid = session('domain_uuid');

            $cloudProviderSelector = app()->make(CloudProviderSelector::class);
            $cloudProvider = $cloudProviderSelector->getCloudProvider(request('provider'));

            // 1. Get local devices (mac => uuid)
            $localDevices = Devices::where('domain_uuid', $domain_uuid)
                ->pluck('device_uuid', 'device_address') // device_address is MAC
                ->toArray();

            // 2. Remove all provisioning records for this domain
            DeviceCloudProvisioning::where('domain_uuid', $domain_uuid)->delete();

            $next = null; // Start with no next token
            $limit = 50;  // Define the batch size

            $insertRows = [];
            do {
                $response = $cloudProvider->getDevices($limit, $next);
                if (isset($response['data']['results']) && is_array($response['data']['results'])) {
                    foreach ($response['data']['results'] as $providerDevice) {
                        // Normalize MAC from provider (lowercase, remove any non-alphanum just in case)
                        $mac = strtolower(preg_replace('/[^a-z0-9]/i', '', $providerDevice['mac'] ?? $providerDevice['id']));
                        if (isset($localDevices[$mac])) {
                            $insertRows[] = [
                                'domain_uuid' => $domain_uuid,
                                'device_uuid' => $localDevices[$mac],
                                'provider' => request('provider'),
                                'last_action' => 'register',
                                'status' => 'success',
                                'error' => null,
                            ];
                        }
                    }
                }
                $next = $response['data']['next'] ?? null;
            } while ($next);


            if (!empty($insertRows)) {
                DeviceCloudProvisioning::insert($insertRows);
            }


            return response()->json([
                'messages' => ['success' => ['Devices are successfully synced']]
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
     * Registers devices based on the provided request items.
     *
     * @return JsonResponse Returns a JSON response indicating the success or failure of the registration process.
     * The response contains the status, the device data with their respective errors (if any),
     * and appropriate HTTP status codes.
     */
    public function register(): JsonResponse
    {
        try {
            //Get devices info as a collection
            $items = Devices::whereIn('device_uuid', request('items'))->get();

            foreach ($items as $device) {

                $params = [
                    'device_uuid' => $device->device_uuid,
                    'domain_uuid' => $device->domain_uuid,
                    'device_vendor' => $device->device_vendor,
                    'device_address' => $device->device_address,
                ];

                $job = (new DeviceCloudProvisioningService)->register($params);
                dispatch($job);
            }

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Request has been accepted for processing']],
            ], 201);
        } catch (\Exception $e) {
            logger('DeviceCloudProvisioningController@register error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'messages' => ['error' => [$e->getMessage()]],
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
            //Get devices info as a collection
            $items = Devices::whereIn('device_uuid', request('items'))->get();

            foreach ($items as $device) {
                $original = $device->getOriginal();

                $params = [
                    'device_uuid' => $device->device_uuid,
                    'domain_uuid' => $device->domain_uuid,
                    'device_vendor' => $original['device_vendor'],
                    'device_address' => $original['device_address'],
                ];

                $job = (new DeviceCloudProvisioningService)->deregister($params);
                dispatch($job);
            }

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Request has been accepted for processing']],
            ], 201);
        } catch (\Exception $e) {
            logger('DeviceCloudProvisioningController@deregister error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'messages' => ['error' => [$e->getMessage()]],
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
