<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Services\CloudProvisioningService;
use Illuminate\Http\JsonResponse;

class CloudProvisioningController extends Controller
{
    public Devices $model;
    //public array $filters = [];
    //public string $sortField;
    //public string $sortOrder;
    //protected string $viewName = 'CloudProvisioning';
    //protected array $searchable = ['source', 'destination', 'message'];

    public function __construct()
    {
        $this->model = new Devices();
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
            //$cloudProvisioningService = new CloudProvisioningService();
            //Get items info as a collection
            $items = $this->model::whereIn($this->model->getKeyName(), request('items'))->get();
            $devicesData = [];
            foreach ($items as $item) {
                /** @var Devices $item */
                if ($item->hasSupportedCloudProvider()) {
                    try {
                        $cloudProvider = $item->getCloudProvider();
                        $cloudDeviceData = $cloudProvider->getDevice($item->device_address);
                        $provisioned = (bool) $cloudDeviceData['profileid'];
                        $error = null;
                    } catch (\Exception $e) {
                        logger($e);
                        $cloudDeviceData = null;
                        $provisioned = false;
                        $error = $e->getMessage();
                    }
                } else {
                    $provisioned = false;
                    $cloudDeviceData = null;
                    $error = 'Unsupported provider';
                }
                $devicesData[] = [
                    'device_uuid' => $item->device_uuid,
                    'provisioned' => $provisioned,
                    'error' => $error,
                    'data' => $cloudDeviceData
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
