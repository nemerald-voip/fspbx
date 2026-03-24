<?php

namespace App\Http\Webhooks\Jobs;

use App\Jobs\DeliverApidazeInboundSMS;
use App\Jobs\DeliverApidazeSMSToEmail;
use App\Jobs\SendSmsNotificationToSlack;
use App\Models\DomainSettings;
use App\Models\Extensions;
use App\Models\Messages;
use App\Models\SmsDestinations;
use App\Services\MessageMediaObjectStorageService;
use Illuminate\Support\Facades\Redis;
use libphonenumber\PhoneNumberFormat;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

class ProcessApidazeWebhookJob extends SpatieProcessWebhookJob
{
    protected $messageConfig;
    protected $domain_uuid;
    protected $message = '';
    protected $media = [];
    protected $extension_uuid;
    protected $source;
    protected $destination;
    protected $email = '';
    protected $type = 'sms';
    protected $ext = '';
    protected ?Messages $storedMessage = null;

    public $tries = 10;
    public $maxExceptions = 5;
    public $timeout = 120;
    public $failOnTimeout = true;
    public $backoff = 15;
    public $deleteWhenMissingModels = true;

    public function __construct(WebhookCall $webhookCall)
    {
        $this->queue = 'messages';
        $this->webhookCall = $webhookCall;
    }

    public function handle(MessageMediaObjectStorageService $mediaStorage): void
    {
        Redis::throttle('messages')->allow(2)->every(1)->then(function () use ($mediaStorage) {
            try {
                $this->handleIncomingPayload($mediaStorage);
            } catch (Throwable $e) {
                logger('Error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
                $this->handleError($e);

                // Uncomment if you want queue retries on failure:
                // throw $e;
            }
        }, function () {
            $this->release(5);
        });
    }

    private function handleIncomingPayload(MessageMediaObjectStorageService $mediaStorage): void
    {
        $payload = $this->webhookCall->payload;
        $type = $payload['type'] ?? null;

        if (!in_array($type, ['incomingWebhookSMS', 'incomingWebhookMMS'], true)) {
            logger('Unknown Apidaze payload type: ' . json_encode($this->summarizePayload($payload)));
            return;
        }

        $this->processInboundMessage($payload, $mediaStorage);
    }

    private function processInboundMessage(array $payload, MessageMediaObjectStorageService $mediaStorage): void
    {
        $rawSource = $payload['from'] ?? $payload['caller_id_number'] ?? null;
        $rawDestination = $payload['to'] ?? $payload['destination_number'] ?? null;

        $this->source = $this->normalizePhoneNumber($rawSource);
        $this->destination = $this->normalizePhoneNumber($rawDestination);

        if (empty($this->source) || empty($this->destination)) {
            throw new \Exception('Missing or invalid source/destination number.');
        }

        $this->message = trim((string) ($payload['text'] ?? ''));
        $this->media = [];

        $this->messageConfig = $this->getPhoneNumberSmsConfig($this->destination);
        $this->domain_uuid = $this->messageConfig->domain_uuid;
        $this->extension_uuid = $this->getExtensionUuid();

        if (($payload['type'] ?? null) === 'incomingWebhookMMS') {
            $this->type = 'mms';
            $this->media = $this->extractAndStoreMmsFiles($payload, $mediaStorage);
        } else {
            $this->type = 'sms';
        }

        $this->email = !empty($this->messageConfig->email)
            ? $this->messageConfig->email
            : '';

        $this->ext = !empty($this->messageConfig->chatplan_detail_data)
            ? $this->messageConfig->chatplan_detail_data
            : '';

        if (!$this->extension_uuid && $this->email === '') {
            throw new \Exception(
                'Phone number ' . $this->destination . ' does not have an assigned extension or email.'
            );
        }

        $this->storedMessage = $this->storeMessage('queued');
        $message = $this->storedMessage;

        // Now that we have the message UUID, attach stable app paths to each media item.
        $this->attachMediaAccessPaths($message);

        $orgId = $this->fetchOrgId();

        if ($this->ext !== '') {
            DeliverApidazeInboundSMS::dispatch([
                'org_id' => $orgId,
                'message_uuid' => $message->message_uuid,
                'extension' => $this->ext,
            ])->onQueue('messages');
        }

        if ($this->email !== '') {
            DeliverApidazeSMSToEmail::dispatch([
                'org_id' => $orgId,
                'message_uuid' => $message->message_uuid,
                'email' => $this->email,
            ])->onQueue('emails');
        }
    }

    private function normalizePhoneNumber(?string $number): ?string
    {
        if (empty($number)) {
            return null;
        }

        $countryCode = get_domain_setting('country', $domain_uuid = null) ?? 'US';

        try {
            return formatPhoneNumber(
                $number,
                $countryCode,
                PhoneNumberFormat::E164
            );
        } catch (\Throwable $e) {
            return preg_replace('/\D+/', '', $number);
        }
    }

    private function extractAndStoreMmsFiles(array $payload, MessageMediaObjectStorageService $mediaStorage): array
    {
        $filesField = $payload['files'] ?? null;

        if (empty($filesField) || !is_string($filesField)) {
            return [];
        }

        $parsedFiles = $this->parseApidazeFilesString($filesField);
        $storedFiles = [];

        foreach ($parsedFiles as $originalName => $base64Content) {
            $binary = base64_decode($base64Content, true);

            if ($binary === false) {
                logger('Failed to decode MMS attachment: ' . $originalName);
                continue;
            }

            $storedFiles[] = $mediaStorage->storeBinaryForDomain(
                domainUuid: $this->domain_uuid,
                binary: $binary,
                originalName: $originalName,
                provider: 'apidaze'
            );
        }

        return $storedFiles;
    }

    private function parseApidazeFilesString(string $files): array
    {
        $results = [];

        preg_match_all(
            "/'([^']+)'\\s*:\\s*'([\\s\\S]*?)'(?=,\\s*'[^']+'\\s*:|\\s*\\}$)/",
            trim($files),
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $filename = $match[1] ?? null;
            $base64 = $match[2] ?? null;

            if ($filename && $base64) {
                $results[$filename] = $base64;
            }
        }

        return $results;
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

        $message->media = json_encode(array_values($this->media));
        $message->save();
    }

    private function getPhoneNumberSmsConfig(string $destination): SmsDestinations
    {
        $model = SmsDestinations::where('destination', $destination)
            ->where('enabled', 'true')
            ->first();

        if (!$model) {
            throw new \Exception('SMS configuration not found for phone number ' . $destination);
        }

        return $model;
    }

    private function getExtensionUuid(): ?string
    {
        if (empty($this->messageConfig->chatplan_detail_data)) {
            return null;
        }

        $extension = Extensions::where('domain_uuid', $this->domain_uuid)
            ->where('extension', $this->messageConfig->chatplan_detail_data)
            ->select('extension_uuid')
            ->first();

        return $extension?->extension_uuid;
    }

    protected function fetchOrgId(): string
    {
        $setting = DomainSettings::where('domain_uuid', $this->domain_uuid)
            ->where('domain_setting_category', 'app shell')
            ->where('domain_setting_subcategory', 'org_id')
            ->value('domain_setting_value');

        if (is_null($setting)) {
            throw new \Exception(
                'From: ' . ($this->source ?? 'Unknown')
                    . ' To: ' . ($this->destination ?? 'Unknown')
                    . ' Org ID not found'
            );
        }

        return $setting;
    }

    private function storeMessage(string $status): Messages
    {
        $messageModel = new Messages();
        $messageModel->extension_uuid = $this->extension_uuid ?? null;
        $messageModel->domain_uuid = $this->domain_uuid ?? null;
        $messageModel->source = $this->source ?? '';
        $messageModel->destination = $this->destination ?? '';
        $messageModel->message = $this->message ?? '';
        $messageModel->media = json_encode($this->media);
        $messageModel->direction = 'in';
        $messageModel->type = $this->type;
        $messageModel->status = $status;
        $messageModel->reference_id = (string) $this->webhookCall->id;
        $messageModel->save();

        return $messageModel;
    }

    private function handleError(Throwable $e): void
    {
        if ($this->storedMessage) {
            $this->storedMessage->status = 'failed';
            $this->storedMessage->save();
        } elseif (!empty($this->source) && !empty($this->destination)) {
            try {
                $this->storedMessage = $this->storeMessage('failed');
            } catch (Throwable $inner) {
                logger('Error: ' . $inner->getMessage() . ' at ' . $inner->getFile() . ':' . $inner->getLine());
            }
        }

        $error = '*Apidaze Inbound Message Failed*'
            . "\nFrom: " . ($this->source ?? 'Unknown')
            . "\nTo: " . ($this->destination ?? 'Unknown')
            . "\nError: " . $e->getMessage();

        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');
    }

    private function summarizePayload(array $payload): array
    {
        $summary = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $summary[$key] = [
                    'type' => 'array',
                    'keys' => array_keys($value),
                    'count' => count($value),
                ];
                continue;
            }

            if (is_string($value)) {
                $summary[$key] = [
                    'type' => 'string',
                    'length' => strlen($value),
                    'preview' => strlen($value) > 150
                        ? substr($value, 0, 150) . '...'
                        : $value,
                ];
                continue;
            }

            $summary[$key] = [
                'type' => gettype($value),
                'value' => $value,
            ];
        }

        return $summary;
    }
}
