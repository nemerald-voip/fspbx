<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\Domain;
use App\Models\Messages;
use App\Models\Extensions;
use App\Jobs\ProcessCommioSMS;
use App\Models\SmsDestinations;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessCommioSMSToEmail;
use Illuminate\Support\Facades\Redis;
use libphonenumber\PhoneNumberFormat;
use App\Jobs\SendSmsNotificationToSlack;
use Spatie\WebhookClient\Models\WebhookCall;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessCommioWebhookJob extends SpatieProcessWebhookJob
{

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 15;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;


    protected $mobileAppDomainConfig;
    protected $smsDestinationModel;
    protected $domain_uuid;
    protected $message;
    protected $extension_uuid;
    protected $source;
    protected $destination;
    protected $carrier;
    protected $messageProvider;
    protected $currentDestination;
    protected $deliveryReceipt;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [(new RateLimitedWithRedis('sms'))];
    }

    public function __construct(WebhookCall $webhookCall)
    {
        $this->queue = 'messages';
        $this->webhookCall = $webhookCall;
    }

    public function handle()
    {
        // $this->webhookCall // contains an instance of `WebhookCall`

        // Allow only 2 tasks every 1 second
        Redis::throttle('messages')->allow(2)->every(1)->then(function () {

            try {
                $this->handleIncomingMessageType();
                return true;
            } catch (\Exception $e) {
                return $this->handleError($e);
            }

            if ($this->webhookCall->payload['to'] != "") {
                ProcessCommioSMS::dispatch([
                    'org_id' => $this->webhookCall->payload['org_id'],
                    'message_uuid' => $this->webhookCall->payload['message_uuid'],
                    'to_did' => $this->webhookCall->payload['to'],
                    'from_did' => $this->webhookCall->payload['from'],
                    'message' => $this->webhookCall->payload['message']
                ])->onQueue('messages');
            }

            if ($this->webhookCall->payload['email_to'] != "") {
                ProcessCommioSMSToEmail::dispatch([
                    'org_id' => $this->webhookCall->payload['org_id'],
                    'message_uuid' => $this->webhookCall->payload['message_uuid'],
                    'email_to' => $this->webhookCall->payload['email_to'],
                    'from_did' => $this->webhookCall->payload['from'],
                    'message' => $this->webhookCall->payload['message']
                ])->onQueue('emails');
            }
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(5);
        });
    }

    private function parseRequest()
    {
        // $rawdata = file_get_contents("php://input");
        // return json_decode($rawdata, true);
    }

    private function handleIncomingMessageType()
    {

        if (isset($this->webhookCall->payload['send_status'])) {
            // $this->handleDeliveryUpdate();
        }

        if (isset($this->webhookCall->payload['type'])) {
            $this->destination = $this->webhookCall->payload['to'];
            $this->source = $this->webhookCall->payload['from'];
            $this->message = $this->webhookCall->payload['message'];
            $this->handleIncomingMessage();
        }

        throw new \Exception("Unsupported message type");
    }

    private function handleIncomingMessage()
    {
        $smsDestinationModel = $this->getPhoneNumberSmsConfig($this->destination);


        $slack_message = "*Commio Inbound SMS* From: " . $this->source . ", To:" . $this->destination . "\n";

        //convert all numbers to e.164 format
        $this->source = formatPhoneNumber($this->source, 'US', PhoneNumberFormat::E164);

        $this->destination = formatPhoneNumber($this->destination, 'US', PhoneNumberFormat::E164);

        $domainModel = Domain::find($smsDestinationModel->domain_uuid);

        if (!$domainModel) {
            throw new \Exception('Domain ' . $smsDestinationModel->domain_uuid . ' is not found');
        }

        $extensionModel = Extensions::where('domain_uuid', $smsDestinationModel->domain_uuid)
            ->where('extension', $smsDestinationModel->chatplan_detail_data)
            ->first();

        if (!$extensionModel && (is_null($smsDestinationModel->email) ||  $smsDestinationModel->email == "")) {
            throw new \Exception('Phone number *' . $this->destination . '*  doesnt have an assigned extension or email');
        }

        if (!is_null($smsDestinationModel->email) &&  $smsDestinationModel->email != "") {
            $email = $smsDestinationModel->email;
        } else {
            $email = "";
        }

        if (!is_null($smsDestinationModel->chatplan_detail_data) &&  $smsDestinationModel->chatplan_detail_data != "") {
            $ext = $smsDestinationModel->chatplan_detail_data;
        } else {
            $ext = "";
        }

        // Store message in database
        $messageModel = new Messages();
        $messageModel->extension_uuid = (isset($extensionModel->extension_uuid)) ? $extensionModel->extension_uuid : null;
        $messageModel->domain_uuid = (isset($smsDestinationModel->domain_uuid)) ? $smsDestinationModel->domain_uuid : null;
        $messageModel->source = $this->source;
        $messageModel->destination = $this->destination;
        $messageModel->message = $this->message;
        $messageModel->direction = 'in';
        $messageModel->type = 'sms';
        $messageModel->status = 'Queued';
        $messageModel->save();

        // $request['org_id'] = $setting->domain_setting_value;
        // $request['to'] = $ext;
        // $request['email_to'] = $email;
        // $request['message_uuid'] = $messageModel->message_uuid;
    }


    private function getPhoneNumberSmsConfig($destination)
    {
        $model = SmsDestinations::where('destination', $destination)->where('enabled', 'true')->first();
        if (!$model) {
            throw new \Exception("SMS configuration not found for extension " . $destination);
        }
        return $model;
    }
    

    private function handleError(\Exception $e)
    {
        logger($e->getMessage());
        $this->storeMessage($e->getMessage());
        // Log the error or send it to Slack
        $error = isset($this->mobileAppDomainConfig) && isset($this->mobileAppDomainConfig->domain) ?
            "*Commio Inbound SMS Failed*: From: " . $this->message['params']['from'] . " in " . $this->mobileAppDomainConfig->domain->domain_description . " To: " . $this->message['params']['to'] . "\n" . $e->getMessage() :
            "*Commio Inbound SMS Failed*: From: " . $this->source . " To: " . $this->destination . "\n" . $e->getMessage();

        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');

        return response()->json(['error' => $e->getMessage()], 400);
    }

    private function storeMessage($status)
    {
        $messageModel = new Messages;
        $messageModel->extension_uuid = (isset($this->extension_uuid)) ? $this->extension_uuid : null;
        $messageModel->domain_uuid = (isset($this->domain_uuid)) ? $this->domain_uuid : null;
        $messageModel->source =  (isset($this->source)) ? $this->source : "";
        $messageModel->destination =  (isset($this->destination)) ? $this->destination : "";
        $messageModel->message = $this->message;
        $messageModel->direction = "in";
        $messageModel->type = 'sms';
        $messageModel->status = $status;
        $messageModel->save();

        logger($messageModel);

        return $messageModel;
    }

    protected function fetchOrgId($destination)
    {
        $setting = DomainSettings::where('domain_uuid', $this->domain_uuid)
            ->where('domain_setting_category', 'app shell')
            ->where('domain_setting_subcategory', 'org_id')
            ->value('domain_setting_value');

        if (is_null($setting)) {
            throw new Exception("From: " . $this->source . " To: " . $destination . " \n Org ID not found");
        }

        return $setting;
    }
}
