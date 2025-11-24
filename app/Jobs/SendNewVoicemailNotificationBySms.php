<?php

namespace App\Jobs;

use App\Models\Messages;
use App\Models\Voicemails;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use App\Models\SmsDestinations;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Factories\MessageProviderFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendNewVoicemailNotificationBySms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;

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
    public $maxExceptions = 3;

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
    public $backoff = [30, 60, 120, 300];

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    // private string $logId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $data)
    {
        // Raw payload from the webhook (data block)
        $this->data = $data;

        // Optional: force this job to the "messages" queue if you want
        $this->onQueue('messages');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Allow only 2 tasks every 1 second
        Redis::throttle('messages')->allow(2)->every(1)->then(function () {

            $data = $this->data;

            // 1. Find the voicemail
            $voicemail = Voicemails::where('domain_uuid', $data['domain_uuid'] ?? null)
                ->where('voicemail_id', $data['voicemail_id'] ?? null)
                ->select(['voicemail_uuid', 'voicemail_sms_to'])
                ->firstOrFail();


            if (empty($voicemail->voicemail_sms_to)) {
                // No SMS destination configured, nothing to do
                return;
            }

            // 2. Prepare base payload
            $payload = [
                'source'      => get_domain_setting('sms_notification_from_number'),
                'destination' => $voicemail->voicemail_sms_to,
                'domain_uuid' => $data['domain_uuid'] ?? null,
                'status'      => 'queued',
            ];

            // 3. Normalize/format message_date for template
            if (!empty($data['message_date'])) {
                $data['message_date'] = Carbon::createFromTimestamp($data['message_date'])
                    ->setTimezone(get_local_time_zone($data['domain_uuid'] ?? null))
                    ->format('Y-m-d H:i');
            }

            // 4. Load SMS config for the "from" number
            $phoneNumberSmsConfig = $this->getPhoneNumberSmsConfig($payload['source']);

            $payload['domain_uuid'] = $phoneNumberSmsConfig->domain_uuid;
            $payload['carrier'] = $phoneNumberSmsConfig->carrier;

            // 5. Determine message provider
            $messageProvider = MessageProviderFactory::make($payload['carrier']);

            // 6. Build message body from template
            $textTemplate = get_domain_setting('sms_notification_text');

            // Optionally append transcription block
            if (!empty($data['transcription'])) {
                $textTemplate .= "\n\nTranscript: " . $data['transcription'];
            }

            $payload['message'] = preg_replace_callback(
                '/\$\{([a-zA-Z0-9_]+)\}/',
                function ($matches) use ($data) {
                    return $data[$matches[1]] ?? '';
                },
                $textTemplate
            );

            // 7. Store message in DB
            $message = $this->storeMessage($payload);

            // 8. Send message via provider
            $messageProvider->send($message->message_uuid);
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(15);
        });
    }

    // public function failed(\Throwable $e): void
    // {
    //     // Best effort: if the row exists, mark permanent failure and store the last error.
    //     EmailLog::where('uuid', $this->logId)->update([
    //         'status'      => 'permanent_failed',   // distinct from interim 'failed' if you want
    //         'sent_debug_info'  => $e->getMessage(),

    //     ]);
    // }

    private function getPhoneNumberSmsConfig(string $from): SmsDestinations
    {
        $phoneNumberSmsConfig = SmsDestinations::where('destination', $from)->first();

        if (!$phoneNumberSmsConfig) {
            throw new \Exception("SMS configuration not found for phone number " . $from);
        }

        return $phoneNumberSmsConfig;
    }

    private function storeMessage(array $payload): Messages
    {
        $messageModel = new Messages();
        $messageModel->extension_uuid = null;
        $messageModel->domain_uuid    = $payload['domain_uuid'] ?? null;
        $messageModel->source         = $payload['source'] ?? null;
        $messageModel->destination    = $payload['destination'] ?? null;
        $messageModel->message        = $payload['message'] ?? null;
        $messageModel->direction      = "out";
        $messageModel->type           = 'sms';
        $messageModel->status         = $payload['status'] ?? null;

        $messageModel->save();

        return $messageModel;
    }
}
