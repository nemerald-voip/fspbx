<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Models\Domain;
use App\Services\Interfaces\ZtpProviderInterface;
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
