<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\Messages;
use App\Models\Extensions;
use App\Models\DomainSettings;
use App\Models\SmsDestinations;
use App\Models\RingotelConversation;
use App\Models\RingotelMessageSync;
use App\Models\RingotelMessageDelivery;
use App\Services\Messaging\MessageParticipantService;
use App\Services\Messaging\RingotelConversationService;
use Illuminate\Support\Facades\Cache;
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
use App\Services\Messaging\Outbound\CreateOutboundMessageService;
use App\Services\Messaging\Outbound\Data\CreateOutboundMessageData;
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

    public function handle(
        MessageMediaObjectStorageService $mediaStorage,
        CreateOutboundMessageService $outbound
    ) {
        // The webhook client has already stored the authenticated payload.
        // Read-state events are observational until their semantics are verified;
        // never route them into carrier delivery or update personal read receipts.
        if (in_array($this->webhookCall->payload['method'] ?? null, ['read', 'unread'], true)) {
            return;
        }

        Redis::throttle('messages')->allow(2)->every(1)->then(function () use ($mediaStorage, $outbound) {
            $this->message = $this->webhookCall->payload;

            try {
                $this->messageType = $this->resolveMessageType();

                if (($this->message['method'] ?? null) === 'delivered') {
                    $response = $this->handleDeliveryStatusUpdate();
                } else {
                    $key = hash('sha256', json_encode([
                        $this->message['params']['orgid'] ?? null,
                        $this->message['params']['messageid'] ?? $this->webhookCall->id,
                    ]));
                    $response = Cache::store('redis')->lock('ringotel:webhook:'.$key, 180)->block(2,
                        fn () => $this->processOutgoingMessage($mediaStorage, $outbound));
                }

                return $response;
            } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
                return $this->release(5);
            } catch (\Exception $e) {
                return $this->handleError($e);
            }
        }, function () {
            return $this->release(5);
        });
    }

    private function processOutgoingMessage(
        MessageMediaObjectStorageService $mediaStorage,
        CreateOutboundMessageService $outbound
    ) {
        if ($this->alreadyProcessed()) {
            return response()->json(['status' => 'Already processed']);
        }
        $this->validateMessage();

        $this->mobileAppDomainConfig = $this->getMobileAppDomainConfig($this->message['params']['orgid']);
        $this->domain_uuid = $this->mobileAppDomainConfig->domain_uuid;
        $this->extension_uuid = $this->getExtensionUuid();

        $phoneNumberSmsConfig = $this->getPhoneNumberSmsConfig(
            $this->message['params']['from'],
            $this->domain_uuid
        );

        $this->carrier = $phoneNumberSmsConfig->carrier;

        $countryCode = get_domain_setting('country', $this->domain_uuid) ?? 'US';

        $source = formatPhoneNumber(
            $phoneNumberSmsConfig->destination,
            $countryCode,
            PhoneNumberFormat::E164
        );

        $destination = formatPhoneNumber(
            $this->message['params']['to'],
            $countryCode,
            PhoneNumberFormat::E164
        );

        $message = $outbound->create(CreateOutboundMessageData::from([
            'domainUuid' => $this->domain_uuid,
            'extensionUuid' => $this->extension_uuid,
            'source' => $source,
            'destination' => $destination,
            'message' => $this->messageType === 'mms'
                ? ''
                : (string) ($this->message['params']['content'] ?? ''),
            'origin' => 'ringotel',
            'carrier' => $this->carrier,
            'mediaRemoteUrls' => $this->messageType === 'mms'
                ? [(string) ($this->message['params']['content'] ?? '')]
                : [],
            'meta' => [
                'ringotel_orgid' => $this->message['params']['orgid'] ?? null,
                'ringotel_sessionid' => $this->message['params']['sessionid'] ?? null,
                'ringotel_userid' => $this->message['params']['userid'] ?? null,
                'ringotel_messageid' => $this->message['params']['messageid'] ?? null,
                'ringotel_remote_identifier' => $this->message['params']['to'] ?? null,
                'ringotel_received_at' => $this->webhookCall->created_at->toIso8601String(),
            ],
        ]));

        $this->storedMessage = $message;

        // Carrier delivery is already queued. Session tracking is local and best-effort;
        // the persisted message metadata also permits later recovery.
        try {
            app(RingotelConversationService::class)->remember($message);
        } catch (\Throwable $e) {
            logger()->warning('Unable to record Ringotel conversation session.', [
                'message_uuid' => $message->message_uuid, 'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status' => ucfirst($message->type) . ' queued',
            'message_uuid' => $message->message_uuid,
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
        // These are Ringotel receipts, not carrier delivery receipts. They remain in
        // the webhook audit log and must not overwrite the carrier status/reference.
        return response()->json(['status' => 'Ringotel receipt recorded']);
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
            ->where('domain_setting_category', 'app shell')
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
        $configs = app(MessageParticipantService::class)->routes($domainUuid, $this->extension_uuid);

        if ($configs->count() > 1) {
            $local = RingotelConversation::query()->where('domain_uuid', $domainUuid)
                ->where('extension_uuid', $this->extension_uuid)
                ->where('org_id', $this->message['params']['orgid'])
                ->where('user_id', $this->message['params']['userid'] ?? '')
                ->where('session_id', $this->message['params']['sessionid'] ?? '')
                ->pluck('local_number')->unique();
            if ($local->count() !== 1) {
                throw new \RuntimeException('Multiple SMS numbers are assigned to this extension and its Ringotel session does not identify one number.');
            }
            $country = get_domain_setting('country', $domainUuid) ?? 'US';
            $configs = $configs->filter(fn ($config) => formatPhoneNumber($config->destination, $country, PhoneNumberFormat::E164) === $local->first());
        }
        $phoneNumberSmsConfig = $configs->first();

        if (!$phoneNumberSmsConfig) {
            throw new \Exception("SMS/MMS configuration not found for extension " . $from);
        }

        return $phoneNumberSmsConfig;
    }

    private function getExtensionUuid()
    {
        $extension = Extensions::where('domain_uuid', $this->domain_uuid)
            ->where('extension', $this->message['params']['from'])
            ->without('advSettings')->with('mobile_app')
            ->first();

        if (!$extension) {
            throw new \Exception("Extension " . $this->message['params']['from'] . " not found");
        }

        if ((string) $extension->mobile_app?->user_id !== (string) ($this->message['params']['userid'] ?? '')) {
            throw new \RuntimeException('Ringotel sender does not match the assigned user for this extension.');
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

    private function alreadyProcessed(): bool
    {
        $orgId = $this->message['params']['orgid'] ?? null;
        $messageId = $this->message['params']['messageid'] ?? null;
        if (!$orgId || !$messageId) {
            return false;
        }

        if (Messages::query()->where('delivery_meta->outbound->meta->ringotel_orgid', $orgId)
            ->where('delivery_meta->outbound->meta->ringotel_messageid', $messageId)->exists()) {
            return true;
        }

        // Suppress known sync echoes rather than send the SMS through the carrier again.
        $syncs = RingotelMessageDelivery::query()
            ->whereIn('ringotel_conversation_uuid', RingotelConversation::query()->select('ringotel_conversation_uuid')
                ->where('org_id', $orgId))
            ->cursor();
        foreach ($syncs as $sync) {
            foreach ($sync->parts ?? [] as $part) {
                if (($part['message_id'] ?? null) === $messageId) {
                    return true;
                }
            }
        }
        return false;
    }

    private function isValidMmsContent(string $content): bool
    {
        return filter_var($content, FILTER_VALIDATE_URL) !== false;
    }
}
