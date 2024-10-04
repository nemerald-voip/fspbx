<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Models\DomainSettings;
use App\Services\Interfaces\ZtpProviderInterface;
use App\Services\PolycomZtpProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Session;

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
            //Get items info as a collection
            $items = $this->model::whereIn($this->model->getKeyName(), request('items'))->get();

            $devicesData = [];
            foreach ($items as $item) {
                $cloudProvider = $this->getCloudProvider($item->device_vendor);
                try {
                    $cloudDeviceData = $cloudProvider->getDevice($item->device_address);
                    $provisioned = (bool) $cloudDeviceData['profileid'];
                    $error = null;
                } catch (\Exception $e) {
                    logger($e);
                    $cloudDeviceData = null;
                    $provisioned = false;
                    $error = $e->getMessage();
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
            //Get items info as a collection
            $items = $this->model::whereIn($this->model->getKeyName(), request('items'))->get();
            $devicesData = [];
            foreach ($items as $item) {
                $cloudProvider = $this->getCloudProvider($item->device_vendor);
                try {
                    $cloudProvider->createDevice(
                        $item->device_address,
                        $this->getCloudProviderOrganisationId($item->device_vendor)
                    );
                    $provisioned = true;
                    $error = null;
                } catch (\Exception $e) {
                    logger($e);
                    $provisioned = false;
                    $error = $e->getMessage();
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
            //Get items info as a collection
            $items = $this->model::whereIn($this->model->getKeyName(), request('items'))->get();
            $devicesData = [];
            foreach ($items as $item) {
                $cloudProvider = $this->getCloudProvider($item->device_vendor);
                try {
                    $cloudProvider->deleteDevice($item->device_address);
                    $error = null;
                } catch (\Exception $e) {
                    logger($e);
                    $error = $e->getMessage();
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

    /*
        private function getPhoneNumberSmsConfig($from, $domainUuid)
        {
            $phoneNumberSmsConfig = SmsDestinations::where('domain_uuid', $domainUuid)
                ->where('chatplan_detail_data', $from)
                ->first();

            if (!$phoneNumberSmsConfig) {
                throw new \Exception("SMS configuration not found for extension " . $from);
            }

            return $phoneNumberSmsConfig;
        }
    */
    private function getCloudProvider(string $provider): ZtpProviderInterface
    {
        return match ($provider) {
            'polycom' => new PolycomZtpProvider(),
            //'yealink' => new YealinkZTPApiProvider(),
            default => throw new \Exception("Unsupported provider"),
        };
    }

    /**
     * @string $provider
     * @throws \Exception
     */
    private function getCloudProviderOrganisationId(string $provider): string
    {
        $domainSettings = DomainSettings::where('domain_uuid', Session::get('domain_uuid'))
            ->where('domain_setting_category', 'cloud provision');

        $domainSettings = match ($provider) {
            'polycom' => $domainSettings->where('domain_setting_subcategory', 'polycom_ztp_profile_id'),
            //'yealink' => $domainSettings->where('domain_setting_subcategory', 'yealink_ztp_profile_id'),
            default => throw new \Exception("Organisation ID not found"),
        };

        if($domainSettings->count() == 0) {
            throw new \Exception("Organisation ID not found");
        }

        $orgId = $domainSettings->value('domain_setting_value');

        if(empty($orgId)) {
            throw new \Exception("Organisation ID is empty");
        }

        return $orgId;
    }

    /*
        private function updateMessageStatus($message, $response)
        {
            if (isset($response['result']) && !empty($response['result'])) {
                if (isset($response['result']['messageid'])) {
                    $message->status = 'success';
                    $message->reference_id = $response['result']['messageid'];
                } else {
                    $message->status = 'failed';
                    $errorDetail = json_encode($response['result']);
                    SendSmsNotificationToSlack::dispatch("*Commio Inbound SMS Failed*.From: " . $this->source . " To: " . $this->extension . "\nRingotel API Error: No message ID received. Details: " . $errorDetail)->onQueue('messages');
                }
            } else {
                $message->status = 'failed';
                $errorDetail = isset($response['error']) ? json_encode($response['error']) : 'Unknown error';
                SendSmsNotificationToSlack::dispatch("*Commio Inbound SMS Failed*.From: " . $this->source . " To: " . $this->extension . "\nRingotel API Failure: " . $errorDetail)->onQueue('messages');
            }
            $message->save();
        }
    */
}
