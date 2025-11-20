<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\Messages;
use App\Models\Extensions;
use App\Models\DomainSettings;
use App\Models\SmsDestinations;
use Illuminate\Support\Facades\Redis;
use libphonenumber\PhoneNumberFormat;
use App\Jobs\DeliverClickSendInboundSMS; 
use App\Jobs\DeliverClickSendSMSToEmail; 
use App\Jobs\SendSmsNotificationToSlack;
use Spatie\WebhookClient\Models\WebhookCall;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessClickSendWebhookJob extends SpatieProcessWebhookJob
{
    protected $messageConfig;
    protected $domain_uuid;
    protected $message;
    protected $media;
    protected $extension_uuid;
    protected $source;
    protected $destination; 
    protected $email;
    protected $type;
    protected $ext;

    /**
     * The number of times the job may be attempted.
     * @var int
     */
    public $tries = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     * @var int
     */
    public $maxExceptions = 5;

    /**
     * The number of seconds the job can run before timing out.
     * @var int
     */
    public $timeout = 120;

    /**
     * Indicate if the job should be marked as failed on timeout.
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     * @var int
     */
    public $backoff = 15;

    /**
     * Delete the job if its models no longer exist.
     * @var bool
     */
    public $deleteWhenMissingModels = true;


    public function __construct(WebhookCall $webhookCall)
    {
        $this->queue = 'messages';
        $this->webhookCall = $webhookCall;
    }

    public function handle()
    {
        // Allow only 2 tasks every 1 second
        Redis::throttle('messages')->allow(2)->every(1)->then(function () {
            try {
                $this->handleIncomingPayload();
                return true;
            } catch (\Exception $e) {
                logger('ProcessClickSendWebhook@handle error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                return $this->handleError($e);
            }
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(5);
        });
    }

    private function handleIncomingPayload()
    {
        $payload = $this->webhookCall->payload;

        // ClickSend payloads are flat arrays. 
        // We determine the type based on the presence of specific keys.
        
        // Check if this is a Delivery Receipt (DLR)
        // ClickSend DLRs usually contain 'status_code' or 'status_text' and 'message_id'
        if (isset($payload['status_code']) || isset($payload['status'])) {
            $this->handleDeliveryStatusUpdate($payload);
            return;
        }

        // Check if this is an Inbound Message (based on your provided payload)
        if (isset($payload['body']) && isset($payload['from']) && isset($payload['to'])) {
            $this->processInboundMessage($payload);
            return;
        }
        
        // Fallback or Unknown
        logger('Unknown ClickSend payload type: ' . json_encode($payload));
    }

    private function processInboundMessage($payload)
    {
        // 1. Format Source (Sender)
        $this->source = formatPhoneNumber($payload['from'], 'US', PhoneNumberFormat::E164);

        // 2. Format Destination (Receiver / Your Number)
        $this->destination = formatPhoneNumber($payload['to'], 'US', PhoneNumberFormat::E164);

        // 3. Extract Message Body
        $this->message = isset($payload['body']) ? $payload['body'] : '';

        // 4. Extract Media (MMS)
        // ClickSend often sends media links within the body or as 'media_file'
        // Based on your payload, it looks like a standard SMS, but we initialize media as empty array
        $this->media = []; 
        
        // Note: If ClickSend sends an attachment, it might appear in 'media_file' or similar keys.
        // You can add logic here: if (isset($payload['media_file'])) { $this->media[] = $payload['media_file']; }

        // 5. Determine Type
        if (!empty($this->media) && count($this->media) > 0) {
            $this->type = 'mms';
        } else {
            $this->type = 'sms';
        }

        // 6. Get Config
        $this->messageConfig = $this->getPhoneNumberSmsConfig($this->destination);

        // 7. Handle Logic
        $this->handleSms();
    }

    private function handleSms()
    {
        $this->domain_uuid = $this->messageConfig->domain_uuid;

        $this->extension_uuid = $this->getExtensionUuid();

        if (!$this->extension_uuid && (is_null($this->messageConfig->email) || $this->messageConfig->email == "")) {
            throw new \Exception('Phone number *' . $this->destination . '* doesnt have an assigned extension or email');
        }

        if (!is_null($this->messageConfig->email) && $this->messageConfig->email != "") {
            $this->email = $this->messageConfig->email;
        } else {
            $this->email = "";
        }

        if (!is_null($this->messageConfig->chatplan_detail_data) && $this->messageConfig->chatplan_detail_data != "") {
            $this->ext = $this->messageConfig->chatplan_detail_data;
        } else {
            $this->ext = "";
        }

        $message = $this->storeMessage('queued');

        if ($this->ext != "") {
            // Assuming you haven't renamed your existing Job classes yet
            DeliverClickSendInboundSMS::dispatch([
                'org_id' => $this->fetchOrgId(),
                'message_uuid' => $message->message_uuid,
                'extension' => $this->ext,
            ])->onQueue('messages');
        }

        if ($this->email != "") {
            // Assuming you haven't renamed your existing Job classes yet
            DeliverClickSendSMSToEmail::dispatch([
                'org_id' => $this->fetchOrgId(),
                'message_uuid' => $message->message_uuid,
                'email' => $this->email,
            ])->onQueue('emails');
        }

        return true;
    }

    public function handleDeliveryStatusUpdate($payload)
    {
        // ClickSend uses 'message_id' to reference the sent message
        $messageId = $payload['message_id'] ?? null;
        
        if (!$messageId) return;

        $message = Messages::where('reference_id', $messageId)->first();

        if ($message) {
            // Map ClickSend status to your system status
            // Example: payload['status'] might be 'Delivered', 'Undelivered', etc.
            if (isset($payload['status'])) {
                $status = strtolower($payload['status']);
                
                if ($status === 'delivered') {
                    $message->status = 'delivered';
                } elseif ($status === 'failed' || $status === 'undelivered') {
                    $message->status = 'failed';
                    // Determine error reason if available
                    $errorReason = $payload['status_text'] ?? 'Unknown Error';
                    $this->processFailedMessage($payload, $message, $errorReason);
                } else {
                    $message->status = $status;
                }
                
                $message->save();
            }
        }
    }

    private function processFailedMessage($payload, $messageModel, $errorReason)
    {
        // Notify Slack or log error
        $from = $messageModel->source;
        $to = $messageModel->destination;
        $referenceId = $messageModel->reference_id;

        $error = "*ClickSend SMS Failed*: From: {$from} To: {$to} [Ref: {$referenceId}] Error: {$errorReason}";
        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');
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
        
        // Save the failure to DB if we have enough info, otherwise just log/slack
        if(isset($this->source) && isset($this->destination)) {
            $this->storeMessage($e->getMessage());
        }

        $errorSource = $this->source ?? 'Unknown';
        $errorDest = $this->destination ?? 'Unknown';

        $error = "*ClickSend Inbound SMS Failed*: From: " . $errorSource . " To: " . $errorDest . "\n" . $e->getMessage();

        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');

        return response()->json(['error' => $e->getMessage()], 400);
    }

    private function storeMessage($status)
    {
        $messageModel = new Messages;
        $messageModel->extension_uuid = (isset($this->extension_uuid)) ? $this->extension_uuid : null;
        $messageModel->domain_uuid = (isset($this->domain_uuid)) ? $this->domain_uuid : null;
        $messageModel->source = (isset($this->source)) ? $this->source : "";
        $messageModel->destination = (isset($this->destination)) ? $this->destination : "";
        $messageModel->message = $this->message;
        $messageModel->media = is_array($this->media) ? json_encode($this->media) : $this->media;
        $messageModel->direction = "in";
        $messageModel->type = $this->type;
        $messageModel->status = $status;
        // Store the ClickSend message ID as reference if available
        $messageModel->reference_id = $this->webhookCall->payload['message_id'] ?? null;
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
            throw new \Exception("From: " . $this->source . " To: " . $this->destination . " \n Org ID not found");
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
            return null;
        }
    }
}