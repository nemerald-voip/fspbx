<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Services\Interfaces\ZtpProviderInterface;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class CloudProvisioningController extends Controller
{
    public Devices $model;
    //public array $filters = [];
    //public string $sortField;
    //public string $sortOrder;
    protected string $viewName = 'CloudProvisioning';
    //protected array $searchable = ['source', 'destination', 'message'];

    public function __construct()
    {
        $this->model = new Devices();
    }

    public function index()
    {
        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return []; //$this->getData();
                },

                'routes' => [
                    /*'current_page' => route('apps.index'),
                    'create_organization' => route('apps.organization.create'),
                    'update_organization' => route('apps.organization.update'),
                    'destroy_organization' => route('apps.organization.destroy'),
                    'pair_organization' => route('apps.organization.pair'),
                    'get_all_orgs' => route('apps.organization.all'),
                    'get_api_token' => route('apps.token.get'),
                    'update_api_token' => route('apps.token.update'),
                    'item_options' => route('apps.item.options'),*/
                ]
            ]
        );
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
