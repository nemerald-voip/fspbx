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
use Spatie\QueryBuilder\QueryBuilder;
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
                if ($model->ringotel_status == 'true') {
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
        // logger(request()->all());
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
    public function getMobileAppOptions(RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;

        try {

            $mobile_app = QueryBuilder::for(MobileAppUsers::query())
                ->select('mobile_app_user_uuid', 'org_id', 'conn_id', 'user_id', 'status')
                ->where('extension_uuid', request('extension_uuid'))
                ->first();


            $org_id = DomainSettings::where('domain_uuid', session('domain_uuid'))
                ->where('domain_setting_category', 'app shell')
                ->where('domain_setting_subcategory', 'org_id')
                ->where('domain_setting_enabled', true)
                ->value('domain_setting_value');

            if (empty($org_id)) {
                throw new \Exception("Contact your administrator to enable mobile apps.");
            }

            $connections = $this->ringotelApiService->getConnections($org_id);

            return response()->json([
                'mobile_app' => $mobile_app,
                'org_id' => $org_id,
                'connections' => $connections,
            ]);
        } catch (\Throwable $e) {
            logger('ExtensionsController@getMobileAppOptions error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success'  => false,
                'errors' => ['error' => [$e->getMessage()]],
                'data'     => [],
            ], 404);
        }
    }


    /**
     * Submit new user request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createUser(RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;
        try {
            $currentDomain = session('domain_uuid');

            $extension = QueryBuilder::for(Extensions::class)
                ->select([
                    'extension_uuid',
                    'domain_uuid',
                    'extension',
                    'password',
                    'effective_caller_id_name',
                    'effective_caller_id_number',

                ])
                ->with([
                    'voicemail' => function ($query) use ($currentDomain) {
                        $query->where('domain_uuid', $currentDomain)
                            ->select(
                                'voicemail_uuid',
                                'domain_uuid',
                                'voicemail_id',
                                'voicemail_mail_to',
                            );
                    },

                ])
                ->whereKey(request('extension_uuid'))
                ->firstOrFail();



            // We don't show the password and QR code for the organisations that has dont_send_user_credentials=true
            $hidePassInEmail = get_domain_setting('dont_send_user_credentials');
            if ($hidePassInEmail === null) {
                $hidePassInEmail = 'false';
            }

            $params = [
                'org_id' => request('org_id'),
                'conn_id' => request('connection'),
                'name' => $extension->effective_caller_id_name,
                'email' => $extension->email ? $extension->email : "",
                'ext' => $extension->extension,
                'username' => $extension->extension,
                // 'domain' => $request->app_domain,
                'authname' => $extension->extension,
                'password' => $extension->password,
                'status' => request('status'),
                'noemail' => true,
            ];

            // Send request to create user
            $user = $this->ringotelApiService->createUser($params);

            // If success and user is activated send user email with credentials
            if ($user) {
                if ($hidePassInEmail == 'true' && request('status') == 1) {
                    // Include get-password link and remove password value
                    $passwordToken = Str::random(40);
                    MobileAppPasswordResetLinks::where('extension_uuid', $extension->extension_uuid)->delete();
                    $appCredentials = new MobileAppPasswordResetLinks();
                    $appCredentials->token = $passwordToken;
                    $appCredentials->extension_uuid = $extension->extension_uuid;
                    $appCredentials->domain = $user['domain'];
                    $appCredentials->save();

                    $passwordUrlShow = userCheckPermission('mobile_apps_password_url_show') ?? 'false';
                    $includePasswordUrl = $passwordUrlShow == 'true' ? route('appsGetPasswordByToken', $passwordToken) : null;
                    $user['password_url'] = $includePasswordUrl;
                }
                if ($extension->email) {
                    SendAppCredentials::dispatch($user)->onQueue('emails');
                }
            }

            // Delete any prior info from database
            MobileAppUsers::where('extension_uuid', $extension->extension_uuid)->delete();

            // Save returned user info in database
            $appUser = new MobileAppUsers();
            $appUser->extension_uuid = $extension->extension_uuid;
            $appUser->domain_uuid = $extension->domain_uuid;
            $appUser->org_id = request('org_id');
            $appUser->conn_id = request('connection');
            $appUser->user_id = $user['id'];
            $appUser->status = $user['status'];
            $appUser->save();
            // Log::info($response);

            $qrcode = "";
            if ($hidePassInEmail == 'false') {
                if (request('status') == 1) {
                    // Generate QR code
                    $qrcode = QrCode::format('png')->generate('{"domain":"' . $user['domain'] .
                        '","username":"' . $user['username'] . '","password":"' .  $user['password'] . '"}');
                }
            } else {
                $user['password'] = null;
            }

            return response()->json([
                'user' => $user,
                'qrcode' => ($qrcode != "") ? base64_encode($qrcode) : null,
                'messages' => ['success' => ['Mobile app has been enabled']]
            ]);
        } catch (\Throwable $e) {
            logger('ExtensionsController@createUser error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success'  => false,
                'errors' => ['error' => [$e->getMessage()]],
                'data'     => [],
            ], 404);
        }
    }

    /**
     * Submit delete user request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUser(RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;
        try {

            MobileAppUsers::find(request('mobile_app_user_uuid'))->delete();

            $params['org_id'] = request('org_id');
            $params['user_id'] = request('user_id');

            // Send request to delеte user
            $response = $this->ringotelApiService->deleteUser($params);

            return response()->json([
                'messages' => ['success' => ['Mobile app has been removed']]
            ], 200);
        } catch (\Exception $e) {
            logger('ExtensionsController@deleteUser error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'status' => 500,
                'error' => [
                    'message' => 'An unexpected error occurred. Please try again later.',
                ],
            ]);
        }
    }


    /**
     * Submit password reset request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;
        try {

            $params = [
                'org_id' => request('org_id'),
                'user_id' => request('user_id'),
                'noemail' => true,
            ];

            // We don't show the password and QR code for the organisations that has dont_send_user_credentials=true
            $hidePassInEmail = get_domain_setting('dont_send_user_credentials');
            if ($hidePassInEmail === null) {
                $hidePassInEmail = 'false';
            }

            // Send request to reset password
            $user = $this->ringotelApiService->resetPassword($params);

            // If success and user is activated send user email with credentials
            if ($user) {
                if ($hidePassInEmail == 'true') {
                    // Include get-password link
                    $passwordToken = Str::random(40);
                    MobileAppPasswordResetLinks::where('extension_uuid', request('extension_uuid'))->delete();
                    $appCredentials = new MobileAppPasswordResetLinks();
                    $appCredentials->token = $passwordToken;
                    $appCredentials->extension_uuid = request('extension_uuid');
                    $appCredentials->domain = $user['domain'];
                    $appCredentials->save();

                    $passwordUrlShow = userCheckPermission('mobile_apps_password_url_show') ?? 'false';
                    $includePasswordUrl = $passwordUrlShow == 'true' ? route('appsGetPasswordByToken', $passwordToken) : null;
                    $user['password_url'] = $includePasswordUrl;
                }
                if (request('email')) {
                    SendAppCredentials::dispatch($user)->onQueue('emails');
                }
            }

            $qrcode = "";
            if ($hidePassInEmail == 'false') {
                // Generate QR code
                $qrcode = QrCode::format('png')->generate('{"domain":"' . $user['domain'] .
                    '","username":"' . $user['username'] . '","password":"' .  $user['password'] . '"}');
            } else {
                $user['password'] = null;
            }

            return response()->json([
                'user' => $user,
                'qrcode' => ($qrcode != "") ? base64_encode($qrcode) : null,
                'messages' => ['success' => ['Mobile app credentials have been reset']]
            ]);
        } catch (\Throwable $e) {
            logger('ExtensionsController@resetPassword error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success'  => false,
                'errors' => ['error' => [$e->getMessage()]],
            ], 404);
        }
    }


    /**
     * Submit activate user request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function activateUser(RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;
        try {


            $currentDomain = session('domain_uuid');

            $extension = QueryBuilder::for(Extensions::class)
                ->select([
                    'extension_uuid',
                    'domain_uuid',
                    'extension',
                    'password',
                    'effective_caller_id_name',
                    'effective_caller_id_number',

                ])
                ->with([
                    'voicemail' => function ($query) use ($currentDomain) {
                        $query->where('domain_uuid', $currentDomain)
                            ->select(
                                'voicemail_uuid',
                                'domain_uuid',
                                'voicemail_id',
                                'voicemail_mail_to',
                            );
                    },

                    'mobile_app' => function ($query) {
                        $query->select(
                            'mobile_app_user_uuid',
                            'extension_uuid',
                            'conn_id',
                        );
                    },

                ])
                ->whereKey(request('extension_uuid'))
                ->firstOrFail();

            $params = [
                'user_id'   => request('user_id'),
                'org_id'    => request('org_id'),
                'conn_id'   => $extension->mobile_app->conn_id,
                'status'    => 1,
                'no_email'  => true,
                'name'      => $extension->effective_caller_id_name,
                'email'     => $extension->email ?? '',
                'ext'       => $extension->extension,
                'password'  => $extension->password,
            ];

            $user = $ringotelApiService->updateUser($params);

            $extension->mobile_app->status = 1;
            $extension->mobile_app->save();


            // We don't show the password and QR code for the organisations that has dont_send_user_credentials=true
            $hidePassInEmail = get_domain_setting('dont_send_user_credentials');
            if ($hidePassInEmail === null) {
                $hidePassInEmail = 'false';
            }

            $qrcode = "";
            if ($hidePassInEmail == 'false') {
                    $qrcode = QrCode::format('png')->generate('{"domain":"' . $user['domain'] .
                        '","username":"' . $user['username'] . '","password":"' .  $user['password'] . '"}');
            } else {
                $user['password'] = null;
            }

            return response()->json([
                'user' => $user,
                'qrcode' => ($qrcode != "") ? base64_encode($qrcode) : null,
                'messages' => ['success' => ['Mobile app has been activated']]
            ], 200);
        } catch (\Exception $e) {
            logger('ExtensionsController@activateUser error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'status' => 500,
                'error' => [
                    'message' => 'An unexpected error occurred. Please try again later.',
                ],
            ]);
        }
    }

    /**
     * Submit deactivate user request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deactivateUser(RingotelApiService $ringotelApiService)
    {
        // logger(request()->all());
        $this->ringotelApiService = $ringotelApiService;
        try {

            $mobile_app = MobileAppUsers::find(request('mobile_app_user_uuid'));

            $params['org_id'] = request('org_id');
            $params['user_id'] = request('user_id');

            // Send request to deactivate user
            $response = $this->ringotelApiService->deactivateUser($params);

            $users = $ringotelApiService->getUsers(request('org_id'), request('conn_id'));

            $user = collect($users)->firstWhere('username', request('ext'));

            if ($user) {
                $mobile_app = MobileAppUsers::where('user_id', request('user_id'))->first();
                $mobile_app->user_id = $user->id;
                $mobile_app->status = -1;
                $mobile_app->save();
            }

            return response()->json([
                'messages' => ['success' => ['Mobile app has been deactivated']]
            ], 200);
        } catch (\Exception $e) {
            logger('ExtensionsController@deactivateUser error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'status' => 500,
                'error' => [
                    'message' => 'An unexpected error occurred. Please try again later.',
                ],
            ]);
        }
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
