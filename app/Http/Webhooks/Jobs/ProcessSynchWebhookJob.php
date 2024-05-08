<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\Messages;
use App\Models\Extensions;
use App\Jobs\ProcessSynchSMS;
use App\Models\DomainSettings;
use App\Models\SmsDestinations;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessSynchSMSToEmail;
use Illuminate\Support\Facades\Redis;
use libphonenumber\PhoneNumberFormat;
use App\Jobs\SendSmsNotificationToSlack;
use Spatie\WebhookClient\Models\WebhookCall;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessSynchWebhookJob extends SpatieProcessWebhookJob
{

    protected $messageConfig;
    protected $domain_uuid;
    protected $message;
    protected $extension_uuid;
    protected $source;
    protected $destinations;
    protected $curentDestination;
    protected $email;
    protected $ext;

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
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(5);
        });
    }

    private function handleIncomingMessageType()
    {
        logger($this->webhookCall->payload);

        if ($this->webhookCall->payload['deliveryReceipt']) {
            $this->handleDeliveryStatusUpdate($this->webhookCall->payload);
        } else {
            $this->processMessage($this->webhookCall->payload);
        } 
    }

    private function processMessage($payload)
    {
        //convert all numbers to e.164 format
        $this->source = formatPhoneNumber($payload['from'], 'US', PhoneNumberFormat::E164);
        $this->destinations = $payload['to'];

        foreach($this->destinations as $destination) {
            $this->curentDestination = formatPhoneNumber($destination, 'US', PhoneNumberFormat::E164);

            $this->message = $payload['text'];
            $this->messageConfig = $this->getPhoneNumberSmsConfig($this->curentDestination);
            $this->handleSms();
        }

    }

    private function handleSms()
    {
        $this->domain_uuid = $this->messageConfig->domain_uuid;

        $this->extension_uuid = $this->getExtensionUuid();

        if (!$this->extension_uuid && (is_null($this->messageConfig->email) ||  $this->messageConfig->email == "")) {
            throw new \Exception('Phone number *' . $this->curentDestination . '*  doesnt have an assigned extension or email');
        }

        if (!is_null($this->messageConfig->email) &&  $this->messageConfig->email != "") {
            $this->email = $this->messageConfig->email;
        } else {
            $this->email = "";
        }

        if (!is_null($this->messageConfig->chatplan_detail_data) &&  $this->messageConfig->chatplan_detail_data != "") {
            $this->ext = $this->messageConfig->chatplan_detail_data;
        } else {
            $this->ext = "";
        }

        $message = $this->storeMessage('queued');

        if ($this->ext != "") {
            ProcessSynchSMS::dispatch([
                'org_id' => $this->fetchOrgId(),
                'message_uuid' => $message->message_uuid,
                'extension' => $this->ext,
            ])->onQueue('messages');
        }

        if ($this->email != "") {
            ProcessSynchSMSToEmail::dispatch([
                'org_id' => $this->fetchOrgId(),
                'message_uuid' => $message->message_uuid,
                'email' => $this->email,
            ])->onQueue('emails');
        }

        return true;
    }

    public function handleDeliveryStatusUpdate()
    {
        $message = Messages::where('reference_id', $this->webhookCall->payload['guid'])
            ->first();

        if ($message) {
            $message->status = $this->webhookCall->payload['send_status'];
            $message->save();
        }
    }


    private function getPhoneNumberSmsConfig($destination)
    {
        $model = SmsDestinations::where('destination', $destination)->where('enabled', 'true')->first();
        if (!$model) {
            throw new \Exception("SMS configuration not found for phone number " . $destination);
        }
        return $model;
    }


    private function handleError(\Exception $e)
    {
        logger($e->getMessage());
        $this->storeMessage($e->getMessage());
        // Log the error or send it to Slack
        $error = "*Synch Inbound SMS Failed*: From: " . $this->source . " To: " . $this->curentDestination . "\n" . $e->getMessage();

        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');

        return response()->json(['error' => $e->getMessage()], 400);
    }

    private function storeMessage($status)
    {
        $messageModel = new Messages;
        $messageModel->extension_uuid = (isset($this->extension_uuid)) ? $this->extension_uuid : null;
        $messageModel->domain_uuid = (isset($this->domain_uuid)) ? $this->domain_uuid : null;
        $messageModel->source =  (isset($this->source)) ? $this->source : "";
        $messageModel->destination =  (isset($this->curentDestination)) ? $this->curentDestination : "";
        $messageModel->message = $this->message;
        $messageModel->direction = "in";
        $messageModel->type = 'sms';
        $messageModel->status = $status;
        $messageModel->save();

        return $messageModel;
    }

    protected function fetchOrgId()
    {
        $setting = DomainSettings::where('domain_uuid', $this->domain_uuid)
            ->where('domain_setting_category', 'app shell')
            ->where('domain_setting_subcategory', 'org_id')
            ->value('domain_setting_value');

        if (is_null($setting)) {
            throw new \Exception("From: " . $this->source . " To: " . $this->curentDestination . " \n Org ID not found");
        }

        return $setting;
    }

    private function getExtensionUuid()
    {
        $extension = Extensions::where('domain_uuid', $this->domain_uuid)
            ->where('extension', $this->messageConfig->chatplan_detail_data)
            ->select('extension_uuid')
            ->first();

        if ($extension) {
            return $extension->extension_uuid;
        } else {
            // Handle the case when no extension is found
            return null;
        }
    }
}
