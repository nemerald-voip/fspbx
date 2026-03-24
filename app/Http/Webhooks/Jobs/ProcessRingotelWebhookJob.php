<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\Messages;
use App\Models\Extensions;
use App\Models\DomainSettings;
use App\Models\SmsDestinations;
use libphonenumber\PhoneNumberUtil;
use Illuminate\Support\Facades\Redis;
use libphonenumber\PhoneNumberFormat;
use App\Jobs\SendSmsNotificationToSlack;
use libphonenumber\NumberParseException;
use App\Factories\MessageProviderFactory;
use Spatie\WebhookClient\Models\WebhookCall;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use App\Services\MessageMediaObjectStorageService;
use Illuminate\Support\Facades\Http;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessRingotelWebhookJob extends SpatieProcessWebhookJob
{
    public $tries = 10;
    public $maxExceptions = 5;
    public $timeout = 120;
    public $failOnTimeout = true;
    public $backoff = 15;
    public $deleteWhenMissingModels = true;
    protected $media = [];
    protected ?Messages $storedMessage = null;

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
    protected $messageType = 'sms'; // sms | mms

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
            $this->message = $this->webhookCall->payload;

            try {
                $this->messageType = $this->resolveMessageType();

                if (($this->message['method'] ?? null) === 'delivered') {
                    $response = $this->handleDeliveryStatusUpdate();
                } else {
                    $response = $this->processOutgoingMessage($mediaStorage);
                }

                return $response;
            } catch (\Exception $e) {
                return $this->handleError($e);
            }
        }, function () {
            return $this->release(5);
        });
    }

    private function processOutgoingMessage(MessageMediaObjectStorageService $mediaStorage)
    {
        $this->validateMessage();

        $this->mobileAppDomainConfig = $this->getMobileAppDomainConfig($this->message['params']['orgid']);
        $this->domain_uuid = $this->mobileAppDomainConfig->domain_uuid;
        $this->extension_uuid = $this->getExtensionUuid();

        $phoneNumberSmsConfig = $this->getPhoneNumberSmsConfig($this->message['params']['from'], $this->domain_uuid);
        $this->carrier = $phoneNumberSmsConfig->carrier;

        $this->messageProvider = MessageProviderFactory::make($this->carrier);

        $countryCode = get_domain_setting('country', $this->domain_uuid) ?? 'US';
        if (!blank($phoneNumberSmsConfig->destination)) {
            $this->source = formatPhoneNumber(
                $phoneNumberSmsConfig->destination,
                $countryCode,
                PhoneNumberFormat::E164
            );
        }

        $this->media = [];

        if ($this->messageType === 'mms') {
            $this->media = $this->extractAndStoreRingotelMmsFiles($mediaStorage);
        }

        $this->storedMessage = $this->storeMessage('queued');

        if (!empty($this->media)) {
            $this->attachMediaAccessPaths($this->storedMessage);
        }

        $this->messageProvider->send($this->storedMessage->message_uuid);

        return response()->json([
            'status' => ucfirst($this->messageType) . ' sent'
        ]);
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

    private function extractAndStoreRingotelMmsFiles(MessageMediaObjectStorageService $mediaStorage): array
    {
        $sourceUrl = $this->message['params']['content'] ?? null;

        if (empty($sourceUrl) || !filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            throw new \Exception('Ringotel MMS content is missing or is not a valid URL');
        }

        $response = Http::timeout(30)->get($sourceUrl);

        if (!$response->successful()) {
            throw new \Exception('Failed to download MMS attachment from Ringotel');
        }

        $binary = $response->body();

        if ($binary === '' || $binary === null) {
            throw new \Exception('Downloaded Ringotel MMS attachment is empty');
        }

        $path = parse_url($sourceUrl, PHP_URL_PATH);
        $originalName = $path ? basename($path) : 'attachment';

        return [
            $mediaStorage->storeBinaryForDomain(
                domainUuid: $this->domain_uuid,
                binary: $binary,
                originalName: $originalName,
                provider: 'ringotel'
            )
        ];
    }


    public function handleDeliveryStatusUpdate()
    {
        $message = Messages::where('reference_id', $this->message['params']['messageid'])
            ->first();

        if ($message) {
            $message->status = 'delivered';
            $message->save();
        }

        return response()->json(['status' => 'Delivery status updated']);
    }

    private function validateMessage()
    {
        if (!isset($this->message['params']['to'])) {
            throw new \Exception("Missing destination number");
        }

        if (!isset($this->message['params']['content'])) {
            throw new \Exception("Missing message content");
        }

        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        try {
            $phoneNumberObject = $phoneNumberUtil->parse($this->message['params']['to'], 'US');

            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                $this->currentDestination = $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164);
            } else {
                $this->currentDestination = $this->message['params']['to'];
                throw new \Exception("Destination phone number *{$this->message['params']['to']}* is not a valid US number");
            }
        } catch (NumberParseException $e) {
            $this->currentDestination = $this->message['params']['to'];
            throw new \Exception("Destination phone number *{$this->message['params']['to']}* is not a valid US number");
        }

        if ($this->messageType === 'mms' && !$this->isValidMmsContent($this->message['params']['content'])) {
            throw new \Exception("MMS content must be a valid media URL");
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
            throw new \Exception("SMS/MMS configuration not found for extension " . $from);
        }

        return $phoneNumberSmsConfig;
    }

    private function getExtensionUuid()
    {
        $extension = Extensions::where('domain_uuid', $this->domain_uuid)
            ->where('extension', $this->message['params']['from'])
            ->select('extension_uuid')
            ->first();

        if (!$extension) {
            throw new \Exception("Extension " . $this->message['params']['from'] . " not found");
        }

        return $extension->extension_uuid;
    }

    private function handleError(\Exception $e)
    {
        logger($e->getMessage());

        if ($this->storedMessage) {
            $this->storedMessage->status = $e->getMessage();
            $this->storedMessage->save();
        } else {
            $this->storedMessage = $this->storeMessage($e->getMessage());
        }

        $label = strtoupper($this->messageType ?? 'sms');

        $error = isset($this->mobileAppDomainConfig) && isset($this->mobileAppDomainConfig->domain)
            ? "*Outbound {$label} Failed*: From: " . $this->message['params']['from'] . " in " . $this->mobileAppDomainConfig->domain->domain_description . " To: " . $this->message['params']['to'] . "\n" . $e->getMessage()
            : "*Outbound {$label} Failed*: From: " . $this->message['params']['from'] . " To: " . $this->message['params']['to'] . "\n" . $e->getMessage();

        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');

        return response()->json(['error' => $e->getMessage()], 400);
    }

    private function storeMessage($status)
    {
        $content = $this->message['params']['content'] ?? '';

        $messageModel = new Messages;
        $messageModel->extension_uuid = isset($this->extension_uuid) ? $this->extension_uuid : null;
        $messageModel->domain_uuid = isset($this->domain_uuid) ? $this->domain_uuid : null;
        $messageModel->source = isset($this->source) ? $this->source : "";
        $messageModel->destination = isset($this->currentDestination) ? $this->currentDestination : "";
        $messageModel->message = $this->messageType === 'mms' ? '' : $content;
        $messageModel->media = $this->media;
        $messageModel->direction = 'out';
        $messageModel->type = $this->messageType;
        $messageModel->status = $status;
        $messageModel->reference_id = $this->message['params']['messageid'] ?? null;
        $messageModel->save();

        return $messageModel;
    }

    private function resolveMessageType(): string
    {
        return match ((int) ($this->message['params']['type'] ?? 1)) {
            2 => 'mms',
            default => 'sms',
        };
    }

    private function isValidMmsContent(string $content): bool
    {
        return filter_var($content, FILTER_VALIDATE_URL) !== false;
    }
}
