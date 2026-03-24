<?php

namespace App\Http\Webhooks\Jobs;

use App\Jobs\DeliverSinchInboundSMS;
use App\Jobs\DeliverSinchSMSToEmail;
use App\Jobs\SendSmsNotificationToSlack;
use App\Models\DomainSettings;
use App\Models\Extensions;
use App\Models\Messages;
use App\Models\SmsDestinations;
use App\Services\MessageMediaObjectStorageService;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use libphonenumber\PhoneNumberFormat;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;
use Spatie\WebhookClient\Models\WebhookCall;

class ProcessSinchWebhookJob extends SpatieProcessWebhookJob
{
    protected $messageConfig;
    protected $domain_uuid;
    protected $message = '';
    protected $media = [];
    protected $extension_uuid;
    protected $source;
    protected $destinations = [];
    protected $curentDestination;
    protected $email = '';
    protected $type = 'sms';
    protected $ext = '';
    protected $reference_id;
    protected ?Messages $storedMessage = null;

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

    public function handle(MessageMediaObjectStorageService $mediaStorage)
    {
        Redis::throttle('messages')->allow(2)->every(1)->then(function () use ($mediaStorage) {
            try {
                $this->handleIncomingMessageType($mediaStorage);
                return true;
            } catch (\Exception $e) {
                logger('ProcessSinchWebhook@handle error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                return $this->handleError($e);
            }
        }, function () {
            return $this->release(5);
        });
    }

    private function handleIncomingMessageType(MessageMediaObjectStorageService $mediaStorage)
    {
        if (!empty($this->webhookCall->payload['deliveryReceipt'])) {
            $this->handleDeliveryStatusUpdate($this->webhookCall->payload);
        } else {
            $this->processMessage($this->webhookCall->payload, $mediaStorage);
        }
    }

    private function processMessage(array $payload, MessageMediaObjectStorageService $mediaStorage)
    {
        $this->source = formatPhoneNumber($payload['from'], 'US', PhoneNumberFormat::E164);
        $this->destinations = $payload['to'] ?? [];
        $this->reference_id = $payload['referenceId'] ?? null;

        foreach ($this->destinations as $destination) {
            $this->curentDestination = formatPhoneNumber($destination, 'US', PhoneNumberFormat::E164);

            $this->message = isset($payload['text']) ? $payload['text'] : '';

            $rawMediaUrls = isset($payload['mediaUrls']) && is_array($payload['mediaUrls'])
                ? $payload['mediaUrls']
                : [];

            $this->type = !empty($rawMediaUrls) ? 'mms' : 'sms';

            $this->messageConfig = $this->getPhoneNumberSmsConfig($this->curentDestination);
            $this->domain_uuid = $this->messageConfig->domain_uuid;

            $this->media = $this->type === 'mms'
                ? $this->extractAndStoreSinchMmsFiles($rawMediaUrls, $mediaStorage)
                : [];

            $this->handleSms();
        }
    }

    private function handleSms()
    {
        $this->extension_uuid = $this->getExtensionUuid();

        if (!$this->extension_uuid && (is_null($this->messageConfig->email) || $this->messageConfig->email == "")) {
            throw new \Exception('Phone number *' . $this->curentDestination . '* doesnt have an assigned extension or email');
        }

        $this->email = !is_null($this->messageConfig->email) && $this->messageConfig->email != ""
            ? $this->messageConfig->email
            : "";

        $this->ext = !is_null($this->messageConfig->chatplan_detail_data) && $this->messageConfig->chatplan_detail_data != ""
            ? $this->messageConfig->chatplan_detail_data
            : "";

        $message = $this->storeMessage('queued');
        $this->storedMessage = $message;

        if (!empty($this->media)) {
            $this->attachMediaAccessPaths($message);
        }

        if ($this->ext != "") {
            DeliverSinchInboundSMS::dispatch([
                'org_id' => $this->fetchOrgId(),
                'message_uuid' => $message->message_uuid,
                'extension' => $this->ext,
            ])->onQueue('messages');
        }

        if ($this->email != "") {
            DeliverSinchSMSToEmail::dispatch([
                'org_id' => $this->fetchOrgId(),
                'message_uuid' => $message->message_uuid,
                'email' => $this->email,
            ])->onQueue('emails');
        }

        return true;
    }

    public function handleDeliveryStatusUpdate(array $payload)
    {
        $message = Messages::where('reference_id', $payload['referenceId'] ?? null)->first();

        if ($message) {
            $text = $payload['text'] ?? '';
            preg_match('/stat:(\w+)/', $text, $matches);
            $status = $matches[1] ?? 'UNKNOWN';

            if ($status === 'DELIVRD') {
                $message->status = 'delivered';
                $message->save();
            }
        }
    }

    private function extractAndStoreSinchMmsFiles(array $mediaUrls, MessageMediaObjectStorageService $mediaStorage): array
    {
        $storedFiles = [];

        foreach ($mediaUrls as $mediaUrl) {
            if (!is_string($mediaUrl) || empty($mediaUrl)) {
                continue;
            }

            if ($this->shouldSkipSinchMediaUrl($mediaUrl)) {
                continue;
            }

            $response = Http::timeout(30)->get($mediaUrl);

            if (!$response->successful()) {
                logger('Failed to download Sinch MMS attachment: ' . $mediaUrl);
                continue;
            }

            $binary = $response->body();

            if ($binary === '' || $binary === null) {
                logger('Downloaded empty Sinch MMS attachment: ' . $mediaUrl);
                continue;
            }

            $path = parse_url($mediaUrl, PHP_URL_PATH);
            $originalName = $path ? basename($path) : 'attachment';

            $storedFiles[] = $mediaStorage->storeBinaryForDomain(
                domainUuid: $this->domain_uuid,
                binary: $binary,
                originalName: $originalName,
                provider: 'sinch'
            );
        }

        if (empty($storedFiles) && !empty($mediaUrls)) {
            throw new \Exception('Failed to download/store Sinch MMS attachments');
        }

        return $storedFiles;
    }

    private function shouldSkipSinchMediaUrl(string $mediaUrl): bool
    {
        $path = parse_url($mediaUrl, PHP_URL_PATH) ?? '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension === 'smil';
    }

    private function attachMediaAccessPaths(Messages $message): void
    {
        if (empty($this->media)) {
            return;
        }

        foreach ($this->media as $index => &$item) {
            $item['access_path'] = route('messages.media.show', [
                'message_uuid' => $message->message_uuid,
                'index' => $index,
                'file_name' => $item['stored_name'] ?? ('file_' . $index),
            ], false);
        }

        unset($item);

        $message->media = array_values($this->media);
        $message->save();
    }

    private function getPhoneNumberSmsConfig($destination)
    {
        $model = SmsDestinations::where('destination', $destination)
            ->where('enabled', 'true')
            ->first();

        if (!$model) {
            throw new \Exception("SMS configuration not found for phone number " . $destination);
        }

        return $model;
    }

    private function handleError(\Exception $e)
    {
        logger($e->getMessage());

        if ($this->storedMessage) {
            $this->storedMessage->status = mb_substr((string) $e->getMessage(), 0, 250);
            $this->storedMessage->save();
        } else {
            $this->storedMessage = $this->storeMessage($e->getMessage());
        }

        $label = strtoupper($this->type ?? 'sms');

        $error = "*Sinch Inbound {$label} Failed*: From: " . ($this->source ?? 'Unknown') .
            " To: " . ($this->curentDestination ?? 'Unknown') . "\n" . $e->getMessage();

        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');

        return response()->json(['error' => $e->getMessage()], 400);
    }

    private function storeMessage($status)
    {
        $messageModel = new Messages;
        $messageModel->extension_uuid = isset($this->extension_uuid) ? $this->extension_uuid : null;
        $messageModel->domain_uuid = isset($this->domain_uuid) ? $this->domain_uuid : null;
        $messageModel->source = isset($this->source) ? $this->source : "";
        $messageModel->destination = isset($this->curentDestination) ? $this->curentDestination : "";
        $messageModel->message = $this->message ?? '';
        $messageModel->media = $this->media;
        $messageModel->direction = "in";
        $messageModel->type = $this->type ?? 'sms';
        $messageModel->status = mb_substr((string) $status, 0, 250);
        $messageModel->reference_id = $this->reference_id ?? null;
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

        return $extension?->extension_uuid;
    }
}
