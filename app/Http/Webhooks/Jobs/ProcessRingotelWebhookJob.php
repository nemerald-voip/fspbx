<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\Messages;
use App\Models\Extensions;
use App\Jobs\ProcessCommioSMS;
use App\Models\DomainSettings;
use App\Models\SmsDestinations;
use Illuminate\Support\Facades\Log;
use libphonenumber\PhoneNumberUtil;
use App\Jobs\ProcessCommioSMSToEmail;
use Illuminate\Support\Facades\Redis;
use libphonenumber\PhoneNumberFormat;
use App\Services\SynchMessageProvider;
use App\Services\CommioMessageProvider;
use App\Jobs\SendSmsNotificationToSlack;
use libphonenumber\NumberParseException;
use Spatie\WebhookClient\Models\WebhookCall;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessRingotelWebhookJob extends SpatieProcessWebhookJob
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

            $this->message = $this->webhookCall->payload;
            try {
                $response = $this->processOutgoingMessage();
                return $response;
            } catch (\Exception $e) {
                return $this->handleError($e);
            }

            
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(5);
        });
    }

    private function processOutgoingMessage()
    {
        $this->validateMessage();

        $this->mobileAppDomainConfig = $this->getMobileAppDomainConfig($this->message['params']['orgid']);
        $this->domain_uuid = $this->mobileAppDomainConfig->domain_uuid;
        $this->extension_uuid = $this->getExtensionUuid();

        //Get message config
        $phoneNumberSmsConfig = $this->getPhoneNumberSmsConfig($this->message['params']['from'], $this->domain_uuid);
        $this->carrier =  $phoneNumberSmsConfig->carrier;

        //Determine message provider
        $this->messageProvider = $this->getMessageProvider($this->carrier);

        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($phoneNumberSmsConfig->destination, 'US');

            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                $this->source = $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164);
            } else {
                $this->source = $phoneNumberSmsConfig->destination;
                throw new \Exception("Phone number *" . $phoneNumberSmsConfig->destination . "* assigned to extension *" . $this->message['params']['from'] . "* is not a valid US number");
            }
        } catch (NumberParseException $e) {
            $this->source = $phoneNumberSmsConfig->destination;
            throw new \Exception("Phone number *" . $phoneNumberSmsConfig->destination . "* assigned to extension *" . $this->message['params']['from'] . "* is not a valid US number");
        }

        //Store message in the log database
        $message = $this->storeMessage("queued");

        // Send message
        $this->messageProvider->send($message->message_uuid);

        return response()->json(['status' => 'Message sent']);
    }

    private function validateMessage()
    {

        if (!isset($this->message['params']['to'])) {
            throw new \Exception("Missing destination number");
        }

        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($this->message['params']['to'], 'US');

            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                $this->currentDestination = $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164);
            } else {
                $this->currentDestination = $this->message['params']['to'];
                throw new \Exception("Destination phone number *" . $this->message['params']['to'] . "* is not a valid US number");
            }
        } catch (NumberParseException $e) {
            $this->currentDestination = $this->message['params']['to'];
            throw new \Exception("Destination phone number *" . $this->message['params']['to'] . "* is not a valid US number");
        }
    }

    private function getMobileAppDomainConfig($orgId)
    {
        $mobileAppDomainConfig = DomainSettings::where('domain_setting_subcategory', 'org_id')
            ->where('domain_setting_value', $orgId)
            ->with('domain')
            ->first();

        if (!$mobileAppDomainConfig) {
            throw new \Exception("Domain not found");
        }

        return $mobileAppDomainConfig;
    }

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

    private function getExtensionUuid()
    {
        $extension_uuid = Extensions::where('domain_uuid', $this->domain_uuid)
            ->where('extension', $this->message['params']['from'])
            ->select('extension_uuid')
            ->first()
            ->extension_uuid;
        if (!$extension_uuid) {
            throw new \Exception("Extension " . $this->message['params']['from'] . " not found");
        }

        return $extension_uuid;
    }

    private function getMessageProvider($carrier)
    {
        switch ($carrier) {
            case 'thinq':
                return new CommioMessageProvider();
            case 'synch':
                return new SynchMessageProvider();
                // Add cases for other carriers
            default:
                throw new \Exception("Unsupported carrier");
        }
    }

    private function handleError(\Exception $e)
    {

        logger($e->getMessage());
        $this->storeMessage($e->getMessage());
        // Log the error or send it to Slack
        $error = isset($this->mobileAppDomainConfig) && isset($this->mobileAppDomainConfig->domain) ?
            "*Outbound SMS Failed*: From: " . $this->message['params']['from'] . " in " . $this->mobileAppDomainConfig->domain->domain_description . " To: " . $this->message['params']['to'] . "\n" . $e->getMessage() :
            "*Outbound SMS Failed*: From: " . $this->message['params']['from'] . " To: " . $this->message['params']['to'] . "\n" . $e->getMessage();

        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');

        return response()->json(['error' => $e->getMessage()], 400);
    }

    private function storeMessage($status)
    {
        $messageModel = new Messages;
        $messageModel->extension_uuid = (isset($this->extension_uuid)) ? $this->extension_uuid : null;
        $messageModel->domain_uuid = (isset($this->domain_uuid)) ? $this->domain_uuid : null;
        $messageModel->source =  (isset($this->source)) ? $this->source : "";
        $messageModel->destination =  (isset($this->currentDestination)) ? $this->currentDestination : "";
        $messageModel->message = $this->message['params']['content'];
        $messageModel->direction = "out";
        $messageModel->type = 'sms';
        $messageModel->status = $status;
        $messageModel->save();

        return $messageModel;
    }

}
