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
     * Display a listing of the resource.
     *
     * @param  array  $filters
     * @param $query
     * @param $value
     * @return \Illuminate\Http\Response
     *
     * public function index()
     * {
     *
     * return Inertia::render(
     * $this->viewName,
     * [
     * 'data' => function () {
     * return $this->getData();
     * },
     * 'showGlobal' => function () {
     * return request('filterData.showGlobal') === 'true';
     * },
     *
     * 'routes' => [
     * 'current_page' => route('cloud-provisioning.index'),
     * // 'store' => route('messages.store'),
     * // 'select_all' => route('messages.select.all'),
     * // 'bulk_delete' => route('messages.bulk.delete'),
     * // 'bulk_update' => route('messages.bulk.update'),
     * // 'retry' => route('messages.retry'),
     * ]
     * ]
     * );
     * }
     *
     *
     * /**
     *  Get data
     *
     * public function getData($paginate = 50)
     * {
     *
     * // Check if search parameter is present and not empty
     * if (!empty(request('filterData.search'))) {
     * $this->filters['search'] = request('filterData.search');
     * }
     *
     * // Check if showGlobal parameter is present and not empty
     * if (!empty(request('filterData.showGlobal'))) {
     * $this->filters['showGlobal'] = request('filterData.showGlobal') === 'true';
     * } else {
     * $this->filters['showGlobal'] = null;
     * }
     *
     * // Add sorting criteria
     * $this->sortField = request()->get('sortField', 'domain_setting_uuid'); // Default to 'created_at'
     * $this->sortOrder = request()->get('sortOrder', 'asc'); // Default to descending
     *
     * $data = $this->builder($this->filters);
     *
     * // Apply pagination if requested
     * if ($paginate) {
     * $data = $data->paginate($paginate);
     * } else {
     * $data = $data->get(); // This will return a collection
     * }
     *
     * if (isset($this->filters['showGlobal']) && $this->filters['showGlobal']) {
     * // Access domains through the session and filter extensions by those domains
     * $domainUuids = Session::get('domains')->pluck('domain_uuid');
     * // $extensions = Extensions::whereIn('domain_uuid', $domainUuids)
     * //     ->get(['domain_uuid', 'extension', 'effective_caller_id_name']);
     * } else {
     * // get extensions for session domain
     * // $extensions = Extensions::where('domain_uuid', session('domain_uuid'))
     * //     ->get(['domain_uuid', 'extension', 'effective_caller_id_name']);
     * }
     *
     * // logger($data);
     *
     * return $data;
     * }
     *
     * /**
     * @return Builder
     *
     * public function builder(array $filters = [])
     * {
     * $data =  $this->model::query();
     *
     * if (isset($filters['showGlobal']) and $filters['showGlobal']) {
     * $data->with(['domain' => function ($query) {
     * $query->select('domain_uuid', 'domain_name', 'domain_description'); // Specify the fields you need
     * }]);
     * // Access domains through the session and filter devices by those domains
     * $domainUuids = Session::get('domains')->pluck('domain_uuid');
     * $data->whereHas('domain', function ($query) use ($domainUuids) {
     * $query->whereIn($this->model->getTable() . '.domain_uuid', $domainUuids);
     * });
     * } else {
     * // Directly filter devices by the session's domain_uuid
     * $domainUuid = Session::get('domain_uuid');
     * $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid);
     * }
     *
     *
     * $data->select(
     * 'domain_setting_uuid',
     * 'domain_uuid',
     * 'domain_setting_subcategory',
     * 'domain_setting_value',
     *
     * );
     *
     * if (is_array($filters)) {
     * foreach ($filters as $field => $value) {
     * if (method_exists($this, $method = "filter" . ucfirst($field))) {
     * $this->$method($data, $value);
     * }
     * }
     * }
     *
     * // Apply sorting
     * $data->orderBy($this->sortField, $this->sortOrder);
     *
     * return $data;
     * }
     *
     * /**
     * @return void
     *
     * protected function filterSearch($query, $value)
     * {
     * $searchable = $this->searchable;
     * // Case-insensitive partial string search in the specified fields
     * $query->where(function ($query) use ($value, $searchable) {
     * foreach ($searchable as $field) {
     * $query->orWhere($field, 'ilike', '%' . $value . '%');
     * }
     * });
     * }
     *
     *
     * public function retry()
     * {
     * try {
     * //Get items info as a collection
     * $items = $this->model::whereIn($this->model->getKeyName(), request('items'))
     * ->get();
     *
     * foreach ($items as $item) {
     * // get originating extension
     * $extension = Extensions::find($item->extension_uuid);
     *
     * // check if there is an email destination
     * $messageSettings = MessageSetting::where('domain_uuid', $item->domain_uuid)
     * ->where('destination', $item->destination)
     * ->first();
     *
     * if (!$extension && !$messageSettings && !$messageSettings->email) {
     * throw new Exception('No assigned destination found.');
     * }
     *
     *
     * if ($item->direction == "out") {
     *
     * //Get message config
     * $phoneNumberSmsConfig = $this->getPhoneNumberSmsConfig($extension->extension, $item->domain_uuid);
     * $carrier =  $phoneNumberSmsConfig->carrier;
     * // logger($carrier);
     *
     * //Determine message provider
     * $messageProvider = $this->getMessageProvider($carrier);
     *
     * //Store message in the log database
     * $item->status = "Queued";
     * $item->save();
     *
     * // Send message
     * $messageProvider->send($item->message_uuid);
     * }
     *
     * if ($item->direction == "in") {
     * $org_id = DomainSettings::where('domain_uuid', $item->domain_uuid)
     * ->where('domain_setting_category', 'app shell')
     * ->where('domain_setting_subcategory', 'org_id')
     * ->value('domain_setting_value');
     *
     * if (is_null($org_id)) {
     * throw new \Exception("From: " . $item->source . " To: " . $item->destination . " \n Org ID not found");
     * }
     *
     * if ($extension) {
     * // Logic to deliver the SMS message using a third-party Ringotel API,
     * try {
     * $response = Http::ringotel_api()
     * ->withBody(json_encode([
     * 'method' => 'message',
     * 'params' => [
     * 'orgid' => $org_id,
     * 'from' => $item->source,
     * 'to' => $extension->extension,
     * 'content' => $item->message
     * ]
     * ]), 'application/json')
     * ->post('/')
     * ->throw()
     * ->json();
     *
     * $this->updateMessageStatus($item, $response);
     * } catch (\Throwable $e) {
     * logger("Error delivering SMS to Ringotel: {$e->getMessage()}");
     * SendSmsNotificationToSlack::dispatch("*Inbound SMS Failed*. From: " . $item->source . " To: " . $item->extension . "\nError delivering SMS to Ringotel")->onQueue('messages');
     * return false;
     * }
     * }
     *
     * if ($messageSettings && $messageSettings->email) {
     * $attributes['orgid'] = $org_id;
     * $attributes['from'] = $item->source;
     * $attributes['email_to'] = $messageSettings->email;
     * $attributes['message'] = $item->message;
     * $attributes['email_subject'] = 'SMS Notification: New Message from ' . $item->source;
     * // $attributes['smtp_from'] = config('mail.from.address');
     *
     * // Logic to deliver the SMS message using email
     * // This method should return a boolean indicating whether the message was sent successfully.
     * Mail::to($messageSettings->email)->send(new SmsToEmail($attributes));
     *
     * if ($item->status = "queued") {
     * $item->status = 'emailed';
     * }
     * $item->save();
     * }
     *
     * }
     * }
     *
     * // Return a JSON response indicating success
     * return response()->json([
     * 'messages' => ['success' => ['Selected message(s) scheduled for sending']]
     * ], 201);
     * } catch (\Exception $e) {
     * logger($e->getMessage() . PHP_EOL);
     * return response()->json([
     * 'success' => false,
     * 'errors' => ['server' => [$e->getMessage()]]
     * ], 500); // 500 Internal Server Error for any other errors
     * }
     * }
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

    public function register(): JsonResponse
    {
        try {
            //Get items info as a collection
            $items = $this->model::whereIn($this->model->getKeyName(), request('items'))->get();
            $devicesData = [];
            foreach ($items as $item) {
                $cloudProvider = $this->getCloudProvider($item->device_vendor);
                try {
                    $cloudProvider->createDevice($item->device_address,
                        $this->getCloudProviderOrganisationId($item->device_vendor));
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

    private function getCloudProviderOrganisationId(string $provider): string
    {
        $domainSettings = DomainSettings::where('domain_uuid', Session::get('domain_uuid'))
            ->where('domain_setting_category', 'cloud provision');

        return match ($provider) {
            'polycom' => $domainSettings->where('domain_setting_subcategory',
                'polycom_ztp_profile_id')->value('domain_setting_value'),
            //'yealink' => $domainSettings->where('domain_setting_subcategory', 'yealink_ztp_profile_id')->value('domain_setting_value'),
            default => throw new \Exception("Organisation ID not found"),
        };
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
