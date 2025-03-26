<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Domain;
use App\Models\Extensions;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use App\Models\MobileAppUsers;
use App\Models\DefaultSettings;
use App\Jobs\SendAppCredentials;
use App\Services\RingotelApiService;
use Illuminate\Support\Facades\Cache;
use App\Models\MobileAppPasswordResetLinks;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Http\Requests\UpdateRingotelApiTokenRequest;
use App\Http\Requests\StoreRingotelConnectionRequest;
use App\Http\Requests\PairRingotelOrganizationRequest;
use App\Http\Requests\UpdateRingotelConnectionRequest;
use App\Http\Requests\StoreRingotelOrganizationRequest;
use App\Http\Requests\UpdateRingotelOrganizationRequest;

class AppsController extends Controller
{
    protected $ringotelApiService;

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'RingotelAppSettings';
    protected $searchable = ['domain_name', 'domain_description'];

    public function __construct()
    {
        $this->model = new Domain();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index()
    {

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },

                'routes' => [
                    'current_page' => route('apps.index'),
                    'create_organization' => route('apps.organization.create'),
                    'update_organization' => route('apps.organization.update'),
                    'destroy_organization' => route('apps.organization.destroy'),
                    'pair_organization' => route('apps.organization.pair'),
                    'get_all_orgs' => route('apps.organization.all'),
                    'get_api_token' => route('apps.token.get'),
                    'update_api_token' => route('apps.token.update'),
                    'item_options' => route('apps.item.options'),
                ]
            ]
        );
    }

    /**
     *  Get data
     */
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

        // Add `ringotel_status` dynamically
        $data->each(function ($domain) {
            $domain->ringotel_status = $domain->settings()
                ->where('domain_setting_category', 'app shell')
                ->where('domain_setting_subcategory', 'org_id')
                ->where('domain_setting_enabled', true)
                ->exists() ? 'true' : 'false';
        });

        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {
        $data =  $this->model::query();
        // Get all domains with 'domain_enabled' set to 'true' and eager load settings
        $data->where('domain_enabled', 'true')
            ->with(['settings' => function ($query) {
                $query->select('domain_uuid', 'domain_setting_uuid', 'domain_setting_category', 'domain_setting_subcategory', 'domain_setting_value')
                    ->where('domain_setting_category', 'app shell')
                    ->where('domain_setting_subcategory', 'org_id')
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

    public function getItemOptions(RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;

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

            $regions = $this->getRegions();

            $packages = [
                ['value' => '1', 'name' => 'Essentials Package'],
                ['value' => '2', 'name' => 'Pro Package'],
            ];

            $protocols = [
                ['value' => 'sip', 'name' => 'UDP'],
                ['value' => 'sip-tcp', 'name' => 'TCP'],
                ['value' => 'sips', 'name' => 'TLS'],
                ['value' => 'DNS-NAPTR', 'name' => 'DNS-NAPTR'],
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

                $model->ringotel_status = $model->settings()
                    ->where('domain_setting_category', 'app shell')
                    ->where('domain_setting_subcategory', 'org_id')
                    ->where('domain_setting_enabled', true)
                    ->exists() ? 'true' : 'false';
                // logger($model);

                // If model doesn't exist throw an error
                if (!$model) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }

                // Add additional navigation item if ringotel status is true
                if($model->ringotel_status == 'true'){
                    $navigation[] = [
                        'name' => 'Users',
                        'icon' => 'UsersIcon',
                        'slug' => 'users',
                    ];
                }

                $routes = array_merge($routes, []);
            }

            $permissions = $this->getUserPermissions();

            // Get all app settings from Default Settings and overrride with settings saved in Domain Settings
            $appSettings = $this->getAppSettings($model->domain_uuid ?? null);
            $appSettings['suggested_ringotel_domain'] = strtolower(str_replace(' ', '', $model->domain_description ?? ''));
            $appSettings['suggested_connection_name'] = 'Primary SIP Profile';

            // Check if `connection_port` is empty and fall back to `line_sip_port`
            if (empty($appSettings['connection_port'])) {
                $appSettings['connection_port'] = get_domain_setting('line_sip_port', $model->domain_uuid ?? null)  ?? null;
            }

            if (!$model->org_id) {
                $connections = [];
            } else {
                $organization = $this->ringotelApiService->getOrganization($model->org_id);
                $connections = $this->ringotelApiService->getConnections($model->org_id);
            }

            // Construct the itemOptions object
            $itemOptions = [
                'navigation' => $navigation,
                'conn_navigation' => $conn_navigation,
                'model' => $model ?? null,
                'organization' => $organization ?? null,
                'orgId' => $organization->id ?? null,
                'regions' => $regions,
                'packages' => $packages,
                'protocols' => $protocols,
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

    function getAppSettings($domain_uuid)
    {
        // Fetch all domain settings for the given domain_uuid
        $domainSettings = DomainSettings::where('domain_uuid', $domain_uuid)
            ->where('domain_setting_category', 'mobile_apps')
            ->where('domain_setting_enabled', true)
            ->pluck('domain_setting_value', 'domain_setting_subcategory');


        // Fetch all default settings
        $defaultSettings = DefaultSettings::where('default_setting_enabled', true)
            ->where('default_setting_category', 'mobile_apps')
            ->pluck('default_setting_value', 'default_setting_subcategory');

        // Merge settings, prioritizing domain-level settings
        $allSettings = $defaultSettings->merge($domainSettings);

        return $allSettings;
    }


    public function getUserPermissions()
    {
        $permissions = [];
        return $permissions;
    }

    /**
     * Submit API request to Ringotel to create a new organization
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createOrganization(StoreRingotelOrganizationRequest $request, RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;

        $inputs = $request->validated();

        try {
            // Send API request to create organization
            $organization = $this->ringotelApiService->createOrganization($inputs);

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
            ]);

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
     * Submit API request to Ringotel to create a new organization
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function UpdateOrganization(UpdateRingotelOrganizationRequest $request, RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;

        $inputs = $request->validated();

        try {
            // Send API request to update organization
            $organization = $this->ringotelApiService->updateOrganization($inputs);

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
     * Submit request to destroy organization to Ringotel
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyOrganization(RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;

        try {
            // Get Org ID from database
            $domain_uuid = request('domain_uuid');
            $org_id = $this->ringotelApiService->getOrgIdByDomainUuid($domain_uuid);

            // Remove local references from the database
            DomainSettings::where('domain_uuid', $domain_uuid)
                ->where('domain_setting_category', 'app shell')
                ->where('domain_setting_subcategory', 'org_id')
                ->delete();

            if (!$org_id) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['Organization ID not found for the given domain.']]
                ], 404); // 404 Not Found
            }

            // Retrieve all connections for the organization
            $connections = $this->ringotelApiService->getConnections($org_id);

            // Delete each connection
            foreach ($connections as $connection) {
                $this->ringotelApiService->deleteConnection([
                    'conn_id' => $connection->id,
                    'org_id' => $org_id,
                ]);
            }

            // Delete the organization
            $deleteResponse = $this->ringotelApiService->deleteOrganization($org_id);

            if ($deleteResponse) {
                return response()->json([
                    'messages' => ['success' => ['Organization and its connections were successfully deleted.']]
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
     * Submit API request to Ringotel to create a new connection
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createConnection(StoreRingotelConnectionRequest $request, RingotelApiService $ringotelApiService)
    {

        $this->ringotelApiService = $ringotelApiService;

        $inputs = $request->validated();

        try {
            // Send API request to create connection
            $connection = $this->ringotelApiService->createConnection($inputs);

            // Return a JSON response indicating success
            return response()->json([
                'org_id' => $inputs['org_id'],
                'conn_id' => $connection['id'],
                'connection_name' => $inputs['connection_name'],
                'domain' => $inputs['domain'] . ":" . $inputs['port'],
                'messages' => ['success' => ['Connection created successfully']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to add connection. Check logs for more details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }


    /**
     * Submit API request to Ringotel to delete specified connection
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyConnection(RingotelApiService $ringotelApiService)
    {

        $this->ringotelApiService = $ringotelApiService;

        try {
            // Send API request to delete connection
            $connection = $this->ringotelApiService->deleteConnection(request()->all());

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Connection deleted successfully']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to delete connection. Check logs for more details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Submit API request to update connection
     *
     * @return \Illuminate\Http\Response
     */
    public function updateConnection(UpdateRingotelConnectionRequest $request, RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;

        $inputs = $request->validated();

        try {
            // Send API request to create connection
            $connection = $this->ringotelApiService->updateConnection($inputs);

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Connection updated successfully']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to update connection. Check logs for more details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Submit getOrganizations request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrganizations(RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;

        try {
            $organizations = $this->ringotelApiService->getOrganizations();
            $formattedOrganizations = collect($organizations)->map(function ($org) {
                return [
                    'name' => "{$org->name} (id: {$org->id})",
                    'value' => $org->id,
                ];
            });
            return $formattedOrganizations;
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'error' => [
                    'message' => $e->getMessage(),
                ],
            ], 404);
        }
    }


    /**
     * Submit getUsers request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsersByOrgId(RingotelApiService $ringotelApiService, $orgId)
    {

        $this->ringotelApiService = $ringotelApiService;
        try {
            $users = $this->ringotelApiService->getUsersByOrgId($orgId);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json([
                'error' => [
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }


        return response()->json([
            'users' => $users,
            'status' => 200,
            'success' => [
                'message' => 'The request processed successfully'
            ]
        ]);
    }


    /**
     * Connect existing Ringotel organization to selected domain
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pairOrganization(PairRingotelOrganizationRequest $request)
    {
        // Extract data from the request
        $orgId = $request->input('org_id');
        $domainUuid = $request->input('domain_uuid');

        try {
            // Store or update the domain setting record
            $domainSettings = DomainSettings::updateOrCreate(
                [
                    'domain_uuid' => $domainUuid,
                    'domain_setting_category' => 'app shell',
                    'domain_setting_subcategory' => 'org_id',
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
     * Sync Ringotel app users from the cloud
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncUsers(RingotelApiService $ringotelApiService)
    {
        logger(request()->all());
        $this->ringotelApiService = $ringotelApiService;

        try {
            // Get all connections
            $connections = $this->ringotelApiService->getConnections(request('org_id'));
            $org_id = request('org_id');
            $domain_uuid = request('domain_uuid');

            // Retrieve all extensions in a single query
            $extensions = Extensions::where('domain_uuid', $domain_uuid)->get();
            $extensionMap = $extensions->keyBy('extension'); // Map extensions by 'extension' for quick lookups

            $mobileAppUsersData = []; // Array to hold bulk insert data

            foreach ($connections as $connection) {
                // Get all users for this connection
                $users = $this->ringotelApiService->getUsers($org_id, $connection->id);

                foreach ($users as $user) {
                    // Check if the extension exists in the map
                    $extension = $extensionMap->get($user->extension);

                    if ($extension) {
                        // Prepare data for bulk insert
                        $mobileAppUsersData[] = [
                            'extension_uuid' => $extension->extension_uuid,
                            'domain_uuid' => $extension->domain_uuid,
                            'org_id' => $org_id,
                            'conn_id' => $connection->id,
                            'user_id' => $user->id,
                            'status' => $user->status,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            // Perform bulk insert for MobileAppUsers
            if (!empty($mobileAppUsersData)) {
                MobileAppUsers::insert($mobileAppUsersData);
            }

            return response()->json([
                'messages' => ['success' => ['User are successfully synced']]
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
     * Return Ringotel app user settings
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function mobileAppUserSettings(Request $request, Extensions $extension)
    {
        // Check if the user already exists
        $mobile_app = $extension->mobile_app;
        if ($mobile_app) {
            return response()->json([
                'mobile_app' => $mobile_app,
                'extension' => $extension->extension,
                'name' => $extension->effective_caller_id_name,
                'status' => '200',
            ]);
        }

        // If the user doesn't exist prepare to create a new one
        $org_id = appsGetOrganizationDetails($extension->domain_uuid);

        // If Organization isn't set up return
        if (!isset($org_id)) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => "Mobile apps are not activated. Please, contact your administrator",
                ],
            ]);
        }

        // Get all connections for this organization
        $response = appsGetConnections($org_id);

        // If successful continue
        if (isset($response['result'])) {
            $connections = $response['result'];
            $app_domain = $response['result'][0]['domain'];

            // Otherwise return failed status
        } elseif (isset($response['error'])) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => $response['error']['message'],
                ],
            ]);
        } else {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => 'Unknown error',
                ],
            ]);
        }

        // If success return response with values
        return response()->json([
            'app_domain' => $app_domain,
            'connections' => $connections,
            'org_id' => $org_id,
            'extension_uuid' => $extension->extension_uuid,
            'status' => 'success',
        ]);
    }


    /**
     * Submit new user request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createUser(Request $request)
    {
        $extension = Extensions::find($request->extension_uuid);

        // We don't show the password and QR code for the organisations that has dont_send_user_credentials=true
        $hidePassInEmail = get_domain_setting('dont_send_user_credentials', $extension->domain()->first()->domain_uuid);
        if ($hidePassInEmail === null) {
            $hidePassInEmail = 'false';
        }

        $mobile_app = [
            'org_id' => $request->org_id,
            'conn_id' => $request->connection,
            'name' => $extension->effective_caller_id_name,
            'email' => ($extension->voicemail) ? $extension->voicemail->voicemail_mail_to : "",
            'ext' => $extension->extension,
            'username' => $extension->extension,
            'domain' => $request->app_domain,
            'authname' => $extension->extension,
            'password' => $extension->password,
            'status' => ($request->activate == 'on') ? 1 : -1,
            'no_email' => $hidePassInEmail == 'true'
        ];

        // Send request to create user
        $response = appsCreateUser($mobile_app);

        //If there is an error return failed status
        if (isset($response['error'])) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => $response['error']['message'],
                ],
            ])->getData(true);
        } elseif (!isset($response['result'])) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => "An unknown error has occurred",
                ],
            ])->getData(true);
        }

        // If success and user is activated send user email with credentials
        if ($response['result']['status'] == 1) {
            if ($hidePassInEmail == 'true' && $request->activate == 'on') {
                // Include get-password link and remove password value
                $passwordToken = Str::random(40);
                MobileAppPasswordResetLinks::where('extension_uuid', $extension->extension_uuid)->delete();
                $appCredentials = new MobileAppPasswordResetLinks();
                $appCredentials->token = $passwordToken;
                $appCredentials->extension_uuid = $extension->extension_uuid;
                $appCredentials->domain = $response['result']['domain'];
                $appCredentials->save();

                $passwordUrlShow = userCheckPermission('mobile_apps_password_url_show') ?? 'false';
                $includePasswordUrl = $passwordUrlShow == 'true' ? route('appsGetPasswordByToken', $passwordToken) : null;
                $response['result']['password_url'] = $includePasswordUrl;
            }
            if (isset($extension->voicemail->voicemail_mail_to)) {
                SendAppCredentials::dispatch($response['result'])->onQueue('emails');
            }
        }

        // Delete any prior info from database
        MobileAppUsers::where('extension_uuid', $extension->extension_uuid)->delete();

        // Save returned user info in database
        $appUser = new MobileAppUsers();
        $appUser->extension_uuid = $extension->extension_uuid;
        $appUser->domain_uuid = $extension->domain_uuid;
        $appUser->org_id = $request->org_id;
        $appUser->conn_id = $request->connection;
        $appUser->user_id = $response['result']['id'];
        $appUser->status = $response['result']['status'];
        $appUser->save();
        // Log::info($response);

        $qrcode = "";
        if ($hidePassInEmail == 'false') {
            if ($request->activate == 'on') {
                // Generate QR code
                $qrcode = QrCode::format('png')->generate('{"domain":"' . $response['result']['domain'] .
                    '","username":"' . $response['result']['username'] . '","password":"' .  $response['result']['password'] . '"}');
            }
        } else {
            $response['result']['password'] = null;
        }

        return response()->json([
            'user' => $response['result'],
            'qrcode' => ($qrcode != "") ? base64_encode($qrcode) : null,
            'status' => 'success',
            'message' => 'The user has been successfully created'
        ]);
    }

    /**
     * Submit delete user request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUser(Request $request, Extensions $extension)
    {

        $mobile_app = $request->mobile_app;

        // Delete any prior info from database
        $appUser = $extension->mobile_app;
        if ($appUser) $appUser->delete();

        // Send request to delеte user
        $response = appsDeleteUser($mobile_app['org_id'], $mobile_app['user_id']);

        //If there is an error return failed status
        if (isset($response['error'])) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => $response['error']['message'],
                ],
            ])->getData(true);
        } elseif (!isset($response['result'])) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => "An unknown error has occurred",
                ],
            ])->getData(true);
        }

        return response()->json([
            'user' => $response['result'],
            'status' => 200,
            'success' => [
                'message' => 'The mobile app user has been successfully deleted'
            ]
        ]);
    }


    /**
     * Submit password reset request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request, Extensions $extension)
    {

        $mobile_app = $request->mobile_app;

        // We don't show the password and QR code for the organisations that has dont_send_user_credentials=true
        $hidePassInEmail = get_domain_setting('dont_send_user_credentials', $extension->domain()->first()->domain_uuid);
        if ($hidePassInEmail === null) {
            $hidePassInEmail = 'false';
        }

        // Send request to reset password
        $response = appsResetPassword($mobile_app['org_id'], $mobile_app['user_id'], $hidePassInEmail == 'true');

        //If there is an error return failed status
        if (isset($response['error'])) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => $response['error']['message'],
                ],
            ])->getData(true);
        } elseif (!isset($response['result'])) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => "An unknown error has occurred",
                ],
            ])->getData(true);
        }

        // If success and user is activated send user email with credentials
        if ($hidePassInEmail == 'true') {
            // Include get-password link and remove password value
            $passwordToken = Str::random(40);
            MobileAppPasswordResetLinks::where('extension_uuid', $extension->extension_uuid)->delete();
            $appCredentials = new MobileAppPasswordResetLinks();
            $appCredentials->token = $passwordToken;
            $appCredentials->extension_uuid = $extension->extension_uuid;
            $appCredentials->domain = $response['result']['domain'];
            $appCredentials->save();
            $passwordUrlShow = userCheckPermission('mobile_apps_password_url_show') ?? 'false';
            $includePasswordUrl = $passwordUrlShow == 'true' ? route('appsGetPasswordByToken', $passwordToken) : null;
            $response['result']['password_url'] = $includePasswordUrl;
        }
        if (isset($extension->voicemail->voicemail_mail_to)) {
            SendAppCredentials::dispatch($response['result'])->onQueue('emails');
        }

        $qrcode = "";
        if ($hidePassInEmail == 'false') {
            // Generate QR code
            $qrcode = QrCode::format('png')->generate('{"domain":"' . $response['result']['domain'] .
                '","username":"' . $response['result']['username'] . '","password":"' .  $response['result']['password'] . '"}');
        } else {
            $response['result']['password'] = null;
        }

        return response()->json([
            'user' => $response['result'],
            'qrcode' => ($qrcode != "") ? base64_encode($qrcode) : null,
            'status' => 200,
            'success' => [
                'message' => 'The mobile app password was successfully reset'
            ]
        ]);
    }


    /**
     * Submit set status request to Ringotel API
     *
     * @return \Illuminate\Http\Response
     */
    public function setStatus(Request $request, Extensions $extension)
    {
        // We don't show the password and QR code for the organisations that has dont_send_user_credentials=true
        $hidePassInEmail = get_domain_setting('dont_send_user_credentials', $extension->domain()->first()->domain_uuid);
        if ($hidePassInEmail === null) {
            $hidePassInEmail = 'false';
        }

        $mobile_app = $request->mobile_app;
        $mobile_app['status'] = (int)$mobile_app['status'];

        $mobile_app['name'] = $extension['effective_caller_id_name'];
        $mobile_app['email'] = ($extension->voicemail['voicemail_mail_to']) ? $extension->voicemail['voicemail_mail_to'] : "";
        $mobile_app['ext'] = $extension['extension'];
        $mobile_app['password'] = $extension->password;
        $mobile_app['no_email'] = $hidePassInEmail == 'true';

        $appUser = $extension->mobile_app;

        if ($mobile_app['status'] == 1) {
            // Send request to update user settings
            $response = appsUpdateUser($mobile_app);

            //If there is an error return failed status
            if (isset($response['error'])) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => $response['error']['message'],
                    ],
                ])->getData(true);
            } elseif (!isset($response['result'])) {
                return response()->json([
                    'status' => 401,
                    'result' => $response,
                    'error' => [
                        'message' => "An unknown error has occurred",
                    ],
                ])->getData(true);
            }

            // Update user info in database
            if ($appUser) {
                $appUser->status = $mobile_app['status'];
                $appUser->save();
            }
        } else if ($mobile_app['status'] == -1) {

            // Send request to delete user first and then recreate it
            $response = appsDeleteUser($mobile_app['org_id'], $mobile_app['user_id']);

            //If there is an error return failed status
            if (isset($response['error'])) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => $response['error']['message'],
                    ],
                ])->getData(true);
            } elseif (!isset($response['result'])) {
                return response()->json([
                    'status' => 401,
                    'result' => $response,
                    'error' => [
                        'message' => "An unknown error has occurred",
                    ],
                ])->getData(true);
            }

            // Delete any prior info from database
            if ($appUser) $appUser->delete();

            // Send request to get org details
            $response = appsGetOrganization($mobile_app['org_id']);

            //If there is an error return failed status
            if (isset($response['error'])) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => $response['error']['message'],
                    ],
                ])->getData(true);
            } elseif (!isset($response['result'])) {
                return response()->json([
                    'status' => 401,
                    'result' => $response,
                    'error' => [
                        'message' => "An unknown error has occurred",
                    ],
                ])->getData(true);
            }

            // Send request to create a new deactivated user
            $mobile_app['username'] = $extension->extension;
            $mobile_app['authname'] = $extension->extension;
            $mobile_app['domain'] = $response['result']['domain'];
            $response = appsCreateUser($mobile_app);

            //If there is an error return failed status
            if (isset($response['error'])) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => $response['error']['message'],
                    ],
                ])->getData(true);
            } elseif (!isset($response['result'])) {
                return response()->json([
                    'status' => 401,
                    'result' => $response,
                    'error' => [
                        'message' => "An unknown error has occurred",
                    ],
                ])->getData(true);
            }

            // Save returned user info in database
            $appUser = new MobileAppUsers();
            $appUser->extension_uuid = $extension->extension_uuid;
            $appUser->domain_uuid = $extension->domain_uuid;
            $appUser->org_id = $mobile_app['org_id'];
            $appUser->conn_id = $mobile_app['conn_id'];
            $appUser->user_id = $response['result']['id'];
            $appUser->status = $response['result']['status'];
            $appUser->save();
        }


        $message = ($mobile_app['status'] == 1) ? 'The mobile app has been activated successfully' : "The mobile app has been deactivated";
        return response()->json([
            //'user' => $response['result'],
            'status' => 200,
            'success' => [
                'message' => $message,
            ]
        ]);
    }




    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function emailUser()
    {
        SendAppCredentials::dispatch()->onQueue('emails');

        //Log::info('Dispatched email ');
        return 'Dispatched email ';
    }

    /**
     * Retrieve the Ringotel API token from DefaultSettings.
     *
     * @param UpdateRingotelApiTokenRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getToken()
    {
        try {
            // Retrieve the API token from DefaultSettings
            $token = DefaultSettings::where([
                ['default_setting_category', '=', 'mobile_apps'],
                ['default_setting_subcategory', '=', 'ringotel_api_token'],
                ['default_setting_enabled', '=', 'true'],
            ])->value('default_setting_value');

            return response()->json([
                'success' => true,
                'token' => $token,
            ], 200); // 200 OK with the token value
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to retrieve API Token. Check logs for more details']],
            ], 500); // 500 Internal Server Error for any other errors
        }
    }


    /**
     * Update or create the Ringotel API token in DefaultSettings.
     *
     * @param UpdateRingotelApiTokenRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateToken(UpdateRingotelApiTokenRequest $request)
    {
        $inputs = $request->validated();

        try {
            // Update or create the Ringotel API token in DefaultSettings
            DefaultSettings::updateOrCreate(
                [
                    'default_setting_category' => 'mobile_apps',
                    'default_setting_subcategory' => 'ringotel_api_token',
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

    public function getRegions()
    {
        $cacheKey = 'ringotel_regions';
        $cacheDuration = now()->addDay(); // Cache for 1 day

        $regions = Cache::remember($cacheKey, $cacheDuration, function () {
            $regions = $this->ringotelApiService->getRegions();

            return $regions->map(function ($region) {
                return [
                    'value' => $region->id,
                    'name' => $region->name,
                ];
            })
                ->sortBy('value') // Sort the collection by the 'value' field
                ->values() // Reset the keys after sorting
                ->toArray();
        });

        return $regions;
    }
}
