<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\Messages;
use App\Models\Extensions;
use App\Models\DomainSettings;
use App\Models\SmsDestinations;
use App\Jobs\DeliverTelnyxInboundSMS;
use App\Jobs\DeliverTelnyxSMSToEmail;
use Illuminate\Support\Facades\Redis;
use libphonenumber\PhoneNumberFormat;
use App\Jobs\SendSmsNotificationToSlack;
use Spatie\WebhookClient\Models\WebhookCall;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessTelnyxWebhookJob extends SpatieProcessWebhookJob
{

    protected $messageConfig;
    protected $domain_uuid;
    protected $message;
    protected $media;
    protected $extension_uuid;
    protected $source;
    protected $destinations;
    protected $curentDestination;
    protected $email;
    protected $type;
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

                logger('ProcessTelnyxWebhookJob Processing ID: ' . $this->webhookCall->id);

                logger($this->webhookCall->payload['data']);

                // Telnyx payloads are wrapped in a 'data' object.
                // We verify structure before proceeding.
                if (!isset($this->webhookCall->payload['data'])) {
                    logger('Invalid Telnyx Payload format');
                    return;
                }

                $this->handleIncomingMessageType();
                return true;
            } catch (\Exception $e) {
                logger('ProcessBandwidthWebhook@handle error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                return $this->handleError($e);
            }
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(15);
        });
    }

    private function handleIncomingMessageType()
    {
        // Telnyx sends the event info in data -> event_type
        $data = $this->webhookCall->payload['data'];
        $eventType = $data['event_type'] ?? '';
        $payload = $data['payload'] ?? [];

        switch ($eventType) {
            case 'message.received':
                $this->processMessage($payload);
                break;

            case 'message.finalized':
                // Finalized covers sent, delivered, failed, etc.
                // We check the internal status to determine if it's a success or failure
                $this->determineDeliveryStatus($payload);
                break;

            default:
                // Log ignored event types (like message.sent which is intermediate)
                logger("Telnyx Event Ignored: " . $eventType);
                break;
        }
    }

    private function processMessage($payload)
    {
        // logger($payload);
        // Telnyx Structure: payload -> from -> phone_number
        $fromNumber = $payload['from']['phone_number'] ?? null;

        // Telnyx Structure: payload -> to -> array of objects
        $toNumbers = [];
        if (isset($payload['to']) && is_array($payload['to'])) {
            foreach ($payload['to'] as $recipient) {
                if (isset($recipient['phone_number'])) {
                    $toNumbers[] = $recipient['phone_number'];
                }
            }
        }

        if (isset($payload['to']) && !is_array($payload['to'])) {
            $toNumbers[] = $payload['to'];
        }

        // Convert Source
        $this->source = formatPhoneNumber($fromNumber, 'US', PhoneNumberFormat::E164);
        $this->destinations = $toNumbers;

        foreach ($this->destinations as $destination) {
            $this->curentDestination = formatPhoneNumber($destination, 'US', PhoneNumberFormat::E164);

            $this->message = $payload['text'] ?? '';

            // Telnyx media is an array of objects: [{url: "...", content_type: "..."}]
            $this->media = $payload['media'] ?? [];

            // Decide type based on media presence
            if (!empty($this->media) && is_array($this->media) && count($this->media) > 0) {
                $type = 'mms';
            } else {
                $type = 'sms';
            }

            $this->type = $type;

            // Find configuration for the destination number (Your Number)
            $this->messageConfig = $this->getPhoneNumberSmsConfig($this->curentDestination);

            $this->handleSms();
        }
    }

    private function handleSms()
    {
        $this->domain_uuid = $this->messageConfig->domain_uuid;
        $this->extension_uuid = $this->getExtensionUuid();

        if (!$this->extension_uuid && (is_null($this->messageConfig->email) || $this->messageConfig->email == "")) {
            throw new \Exception('Phone number *' . $this->curentDestination . '* doesnt have an assigned extension or email');
        }

        $this->email = $this->messageConfig->email ?? "";
        $this->ext = $this->messageConfig->chatplan_detail_data ?? "";

        $message = $this->storeMessage('queued');

        // Dispatch to Extension (Websocket/Push)
        if ($this->ext != "") {
            // Using your existing Job class
            DeliverTelnyxInboundSMS::dispatch([
                'org_id' => $this->fetchOrgId(),
                'message_uuid' => $message->message_uuid,
                'extension' => $this->ext,
            ])->onQueue('messages');
        }

        // Dispatch to Email
        if ($this->email != "") {
            // Using your existing Job class
            DeliverTelnyxSMSToEmail::dispatch([
                'org_id' => $this->fetchOrgId(),
                'message_uuid' => $message->message_uuid,
                'email' => $this->email,
            ])->onQueue('emails');
        }

        return true;
    }

    /**
     * Telnyx sends 'message.finalized'. We need to look inside the payload
     * to see if it was Delivered or Failed.
     */
    public function determineDeliveryStatus($payload)
    {
        // Status is typically found in the first recipient object for 1:1 messages
        // payload -> to[0] -> status
        $status = null;
        $recipientData = $payload['to'][0] ?? null;

        if ($recipientData) {
            $status = $recipientData['status'] ?? null;
        }

        // Check for failure statuses
        $failureStatuses = ['sending_failed', 'delivery_failed', 'delivery_unconfirmed'];

        if (in_array($status, $failureStatuses)) {
            $this->processFailedMessage($payload, $status);
        } elseif ($status === 'delivered') {
            $this->handleDeliveryStatusUpdate($payload);
        }
    }

    public function handleDeliveryStatusUpdate($payload)
    {
        // Telnyx ID matches the reference_id stored when sending
        $message = Messages::where('reference_id', $payload['id'])->first();

        if ($message) {
            $message->status = 'delivered';
            $message->save();
        }
    }

    private function processFailedMessage($payload, $status)
    {
        $referenceId = $payload['id'] ?? null;

        // Extract error details if available
        $errors = $payload['errors'] ?? [];
        $errorDescription = $status; // Default to status
        $errorCode = 'N/A';

        if (!empty($errors)) {
            $firstError = $errors[0];
            $errorDescription = $firstError['detail'] ?? $firstError['title'] ?? $status;
            $errorCode = $firstError['code'] ?? 'N/A';
        }

        // Update local DB
        $messageModel = Messages::where('reference_id', $referenceId)->first();

        if ($messageModel) {
            $messageModel->status = $errorDescription; 
            $messageModel->save();
        }

        // Prepare Slack Notification info
        $from = $payload['from']['phone_number'] ?? 'Unknown';
        $to = $payload['to'][0]['phone_number'] ?? 'Unknown';

        $errorMsg = "*Telnyx SMS Failed*: From: {$from} To: {$to} [Ref: {$referenceId}] Error: {$errorDescription} (Code: {$errorCode})";

        SendSmsNotificationToSlack::dispatch($errorMsg)->onQueue('messages');
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
        // We attempt to store the message even if it failed processing (if we have enough data)
        if ($this->source && $this->curentDestination) {
            $this->storeMessage('failed: ' . substr($e->getMessage(), 0, 20));
        }

        $error = "*Telnyx Inbound SMS Failed*: From: " . ($this->source ?? 'Unknown') . " To: " . ($this->curentDestination ?? 'Unknown') . "\n" . $e->getMessage();

        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');

        return response()->json(['error' => $e->getMessage()], 400);
    }

    private function storeMessage($status)
    {
        $messageModel = new Messages;
        $messageModel->extension_uuid = $this->extension_uuid ?? null;
        $messageModel->domain_uuid = $this->domain_uuid ?? null;
        $messageModel->source = $this->source ?? "";
        $messageModel->destination = $this->curentDestination ?? "";
        $messageModel->message = $this->message;
        $messageModel->media = is_array($this->media) ? json_encode($this->media) : $this->media;
        $messageModel->direction = "in";
        $messageModel->type = $this->type;
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
            // Warning only, don't stop flow if Org ID is just for UI
            logger("Org ID not found for domain " . $this->domain_uuid);
            return null;
        }

        return $setting;
    }

    private function getExtensionUuid()
    {
        $extension = Extensions::where('domain_uuid', $this->domain_uuid)
            ->where('extension', $this->messageConfig->chatplan_detail_data)
            ->select('extension_uuid')
            ->first();

        return $extension ? $extension->extension_uuid : null;
    }
}
