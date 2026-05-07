<?php

namespace App\Http\Webhooks\Jobs;

use App\Jobs\CreateVoicemailEscalationNotificationsJob;
use App\Jobs\HandleFaxTxEventJob;
use App\Jobs\HandleVoicemailEscalationAttemptEventJob;
use App\Jobs\ProcessFaxWebhookEventJob;
use App\Jobs\SendNewVoicemailNotificationByEmail;
use App\Jobs\SendNewVoicemailNotificationBySms;
use App\Jobs\TranscribeCdrJob;
use App\Models\VmNotifyProfile;
use App\Services\CallTranscription\CallTranscriptionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;
use Spatie\WebhookClient\Models\WebhookCall;

class ProcessFreeswitchWebhookJob extends SpatieProcessWebhookJob
{

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 2;

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
            // 'transcribe_call'            => 'transcriptions',
            'fax.received',
            'fax.sent',                  => 'faxes',
            'voicemail_created'          => 'voicemails',
            'vm_notify_attempt_event'    => 'voicemails',
            default                      => 'default',
        };
    }

    public function handle()
    {
        // $this->webhookCall // contains an instance of `WebhookCall`

        // Allow only 2 tasks every 1 second
        Redis::throttle('freeswitch-webhooks')->allow(2)->every(1)->then(function () {

            try {
                $payload = $this->webhookCall->payload;

                $event = $payload['event'] ?? null;
                $timestamp = $payload['timestamp'] ?? null;
                $data = $payload['data'] ?? [];

                switch ($event) {
                    case 'send_vm_sms_notification':
                        SendNewVoicemailNotificationBySms::dispatch($data);
                        break;

                    case 'send_vm_email_notification':
                        SendNewVoicemailNotificationByEmail::dispatch($data);
                        break;

                    case 'voicemail_created':
                        if (!$this->validateVoicemailCreatedPayload($data)) {
                            Log::warning('[Webhook] Invalid voicemail_created payload', $payload);
                            break;
                        }

                        if (!$this->hasEnabledVmNotifyProfile($data)) {
                            break;
                        }

                        CreateVoicemailEscalationNotificationsJob::dispatch($data);
                        break;

                    case 'vm_notify_attempt_event':
                        HandleVoicemailEscalationAttemptEventJob::dispatch($data)->onQueue('voicemails');
                        break;

                    case 'transcribe_call':
                        $response = $this->transcribeCall($data);
                        break;

                    case 'fax.sent':
                        // Outbound faxes originated by SendFaxJob carry an
                        // outbound_fax_uuid channel var; route those to the new
                        // handler. Anything else (legacy origination paths) falls
                        // through to ProcessFaxWebhookEventJob.
                        if (!empty($data['outbound_fax_uuid'])) {
                            fax_webhook_debug('ProcessFreeswitchWebhookJob: routing fax.sent to HandleFaxTxEventJob', [
                                'outbound_fax_uuid'         => $data['outbound_fax_uuid'],
                                'outbound_fax_attempt_uuid' => $data['outbound_fax_attempt_uuid'] ?? null,
                            ]);
                            HandleFaxTxEventJob::dispatch($data)->onQueue('faxes');
                            break;
                        }
                        // fall through to legacy handler
                    case 'fax.received':
                        if (!$this->validateFaxPayload($data)) {
                            Log::warning('[Webhook] Invalid fax payload', $payload);
                            break;
                        }

                        ProcessFaxWebhookEventJob::dispatch($event, $timestamp, $data);
                        break;

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

    private function validateVoicemailCreatedPayload(array $data): bool
    {
        return !empty($data['domain_uuid'])
            && !empty($data['voicemail_uuid'])
            && !empty($data['voicemail_id'])
            && !empty($data['voicemail_message_uuid']);
    }

    private function validateFaxPayload(array $data): bool
    {
        return !empty($data['uuid'])
            && !empty($data['domain_uuid']);
    }

    private function hasEnabledVmNotifyProfile(array $data): bool
    {
        $domainUuid = $data['domain_uuid'] ?? null;
        $voicemailUuid = $data['voicemail_uuid'] ?? null;

        if (!$domainUuid || !$voicemailUuid) {
            return false;
        }

        return VmNotifyProfile::query()
            ->where('domain_uuid', $domainUuid)
            ->where('voicemail_uuid', $voicemailUuid)
            ->where('enabled', true)
            ->exists();
    }

    private function transcribeCall($data)
    {
        // Is call transcription service enabled for this account
        $transcriptionService = app(CallTranscriptionService::class);
        $config = $transcriptionService->getCachedConfig($data['domain_uuid'] ?? null);
        $isCallTranscriptionServiceEnabled = (bool) ($config['enabled'] ?? false);

        if (!$isCallTranscriptionServiceEnabled) return;

        $shouldTranscribe = $transcriptionService->shouldAutoTranscribe($data['domain_uuid'] ?? null);

        if ($shouldTranscribe) {
            TranscribeCdrJob::dispatch(
                $data['uuid'],
                $data['domain_uuid'] ?? null,
                $data['options'] ?? []
            );
        }
    }

    private function handleError(\Exception $e)
    {

        logger('ProcessFreeswitchWebhookJob@handle error:' . $e->getMessage());

        return $this->release(15);
    }
}
