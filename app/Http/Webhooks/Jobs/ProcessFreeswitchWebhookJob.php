<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\Messages;
use App\Models\Voicemails;
use App\Models\SmsDestinations;
use App\Models\VoicemailMessages;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use App\Services\SinchMessageProvider;
use App\Services\CommioMessageProvider;
use App\Jobs\SendSmsNotificationToSlack;
use App\Services\BandwidthMessageProvider;
use Spatie\WebhookClient\Models\WebhookCall;
use App\Jobs\SendNewVoicemailNotificationByEmail;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessFreeswitchWebhookJob extends SpatieProcessWebhookJob
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
    protected $deliveryReceipt;

    public function __construct(WebhookCall $webhookCall)
    {
        $this->webhookCall = $webhookCall;
        $event = $this->webhookCall->payload['event'];

        $event = data_get($webhookCall->payload, 'event');

        $this->queue = match ($event) {
            'send_vm_sms_notification'   => 'messages',
            'send_vm_email_notification' => 'emails',
            default                      => 'default',
        };
    }

    public function handle()
    {
        // $this->webhookCall // contains an instance of `WebhookCall`

        // Allow only 2 tasks every 1 second
        Redis::throttle('messages')->allow(2)->every(1)->then(function () {

            try {
                $payload = $this->webhookCall->payload;

                $event = $payload['event'] ?? null;
                $timestamp = $payload['timestamp'] ?? null;
                $data = $payload['data'] ?? [];

                switch ($event) {
                    case 'send_vm_sms_notification':
                        $response = $this->sendSystemSms($data);
                        break;

                    case 'send_vm_email_notification':
                        SendNewVoicemailNotificationByEmail::dispatch($data);
                        break;
                    // Add more event types as needed

                    default:
                        Log::warning("[Webhook] Unknown event type: $event", $payload);
                        break;
                }

                return true;
            } catch (\Exception $e) {
                return $this->handleError($e);
            }
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(15);
        });
    }

    private function sendSystemSms($data)
    {
        // logger($data);

        $voicemail = Voicemails::where('domain_uuid', $data['domain_uuid'])
            ->where('voicemail_id', $data['voicemail_id'])
            ->select([
                'voicemail_uuid',
                'voicemail_sms_to',
            ])
            ->firstOrFail();

        if (empty($voicemail->voicemail_sms_to)) {
            return response();
        }

        $payload['source'] = get_domain_setting('sms_notification_from_number');
        $payload['destination'] = $voicemail->voicemail_sms_to;
        $payload['domain_uuid'] = $data['domain_uuid'];

        $data['message_date'] = \Carbon\Carbon::createFromTimestamp($data['message_date'])
            ->setTimezone(get_local_time_zone($data['domain_uuid']))
            ->format('Y-m-d H:i');

        //Get message config
        $phoneNumberSmsConfig = $this->getPhoneNumberSmsConfig($payload['source']);

        $payload['domain_uuid'] = $phoneNumberSmsConfig->domain_uuid;
        $payload['carrier'] = $phoneNumberSmsConfig->carrier;
        //Determine message provider
        $this->messageProvider = $this->getMessageProvider($payload['carrier']);

        $text = get_domain_setting('sms_notification_text');
        // $payload['destination'] =  $phoneNumberSmsConfig->destination;

        $payload['message'] = preg_replace_callback('/\$\{([a-zA-Z0-9_]+)\}/', function ($matches) use ($data) {
            return $data[$matches[1]] ?? '';
        }, $text);

        $payload['status'] = "queued";

        // logger($payload);
        //Store message in the log database
        $message = $this->storeMessage($payload);

        // Send message
        $this->messageProvider->send($message->message_uuid);

        return response()->json(['status' => 'Message sent']);
    }


    private function getPhoneNumberSmsConfig($from)
    {
        $phoneNumberSmsConfig = SmsDestinations::where('destination', $from)
            ->first();

        if (!$phoneNumberSmsConfig) {
            throw new \Exception("SMS configuration not found for phone number " . $from);
        }

        return $phoneNumberSmsConfig;
    }

    private function getMessageProvider($carrier)
    {
        switch ($carrier) {
            case 'thinq':
                return new CommioMessageProvider();
            case 'sinch':
                return new SinchMessageProvider();
            case 'bandwidth':
                return new BandwidthMessageProvider();
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
        $error = "*Voicemail SMS notification Failed*: From: " . $this->source . " To: " . $this->destination . "\n" . $e->getMessage();

        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');

        return response()->json(['error' => $e->getMessage()], 400);
    }

    private function storeMessage($payload)
    {
        $messageModel = new Messages;
        $messageModel->extension_uuid = null;
        $messageModel->domain_uuid = $payload['domain_uuid'] ?? null;
        $messageModel->source =  $payload['source'] ?? null;
        $messageModel->destination =  $payload['destination'] ?? null;
        $messageModel->message = $payload['message'] ?? null;
        $messageModel->direction = "out";
        $messageModel->type = 'sms';
        $messageModel->status = $payload['status'] ?? null;

        // logger($messageModel);
        $messageModel->save();

        return $messageModel;
    }
}
